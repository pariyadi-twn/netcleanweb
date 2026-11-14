import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../features/cash/data/cash_repository.dart';
import '../features/cash/domain/cash_models.dart';
import '../features/customers/data/customer_repository.dart';
import '../features/operations/data/operations_repository.dart';
import '../features/orders/data/order_repository.dart';
import 'auth/auth_repository.dart';
import 'auth/secret_store.dart';
import 'auth/token_store.dart';
import 'cache/app_database.dart';
import 'cache/cache_key_store.dart';
import 'cache/payload_cipher.dart';
import 'cache/secure_cache.dart';
import 'network/api_client.dart';
import 'session/session_controller.dart';
import 'sync/command_queue.dart';
import 'sync/command_replay_service.dart';
import 'sync/draft_store.dart';

final secretStoreProvider = Provider<SecretStore>(
  (ref) => FlutterSecretStore(),
);

final tokenStoreProvider = Provider<TokenStore>((ref) {
  return TokenStore(ref.watch(secretStoreProvider));
});

final apiClientProvider = Provider<ApiClient>((ref) {
  final tokens = ref.watch(tokenStoreProvider);
  return ApiClient(
    tokenProvider: () async => (await tokens.readSession())?.token,
  );
});

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  return AuthRepository(
    ref.watch(apiClientProvider),
    ref.watch(tokenStoreProvider),
  );
});

final databaseProvider = Provider<AppDatabase>((ref) {
  final database = AppDatabase.open();
  ref.onDispose(database.close);
  return database;
});

final cacheKeyStoreProvider = Provider<CacheKeyStore>((ref) {
  return CacheKeyStore(ref.watch(secretStoreProvider));
});

final secureCacheProvider = Provider<SecureCache>((ref) {
  return SecureCache(
    ref.watch(databaseProvider),
    ref.watch(cacheKeyStoreProvider),
  );
});

final payloadCipherProvider = Provider<PayloadCipher>((ref) {
  return PayloadCipher(ref.watch(cacheKeyStoreProvider));
});

final draftStoreProvider = Provider<DraftStore>((ref) {
  return DraftStore(
    ref.watch(databaseProvider),
    ref.watch(payloadCipherProvider),
  );
});

final commandQueueProvider = Provider<CommandQueue>((ref) {
  return CommandQueue(
    ref.watch(databaseProvider),
    ref.watch(payloadCipherProvider),
  );
});

final commandSenderProvider = Provider<CommandSender>((ref) {
  return ApiCommandSender(ref.watch(apiClientProvider));
});

final commandReplayServiceProvider = Provider<CommandReplayService>((ref) {
  return CommandReplayService(
    ref.watch(commandQueueProvider),
    ref.watch(commandSenderProvider),
  );
});

final customerRepositoryProvider = Provider<CustomerRepository>((ref) {
  return CustomerRepository(ref.watch(apiClientProvider));
});

final orderRepositoryProvider = Provider<OrderRepository>((ref) {
  return OrderRepository(ref.watch(apiClientProvider));
});

final cashRepositoryProvider = Provider<CashRepository>((ref) {
  return CashRepository(ref.watch(apiClientProvider));
});

final cashRegistersProvider = FutureProvider<List<CashRegisterSummary>>((
  ref,
) async {
  final session = ref.watch(sessionControllerProvider).asData?.value;
  final tenantId = session?.tenantId;
  if (tenantId == null || (session?.branchIds.isEmpty ?? true)) {
    return const [];
  }
  return ref
      .watch(cashRepositoryProvider)
      .registers(tenantId: tenantId, branchId: session!.branchIds.first);
});

final activeCashSessionProvider = FutureProvider<CashSessionSummary?>((
  ref,
) async {
  final registers = await ref.watch(cashRegistersProvider.future);
  for (final register in registers) {
    if (register.currentSession?.isOpen ?? false) {
      return register.currentSession;
    }
  }
  return null;
});

final operationsRepositoryProvider = Provider<OperationsRepository>((ref) {
  return OperationsRepository(ref.watch(apiClientProvider));
});
