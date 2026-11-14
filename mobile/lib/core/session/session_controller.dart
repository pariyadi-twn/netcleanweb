import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../auth/auth_session.dart';
import '../providers.dart';
import '../sync/command_queue.dart';

final sessionControllerProvider =
    AsyncNotifierProvider<SessionController, AuthSession?>(
      SessionController.new,
    );

class SessionController extends AsyncNotifier<AuthSession?> {
  @override
  Future<AuthSession?> build() {
    return ref.read(authRepositoryProvider).restore();
  }

  Future<AuthSession?> login({
    required String email,
    required String password,
  }) async {
    state = const AsyncLoading();
    final result = await AsyncValue.guard(
      () => ref
          .read(authRepositoryProvider)
          .login(email: email, password: password),
    );
    state = result;
    return result.asData?.value;
  }

  Future<void> logout() async {
    final session = state.asData?.value;
    if (session?.tenantId != null && session?.membershipId != null) {
      final scope = SyncScope(
        tenantId: session!.tenantId!,
        userId: session.membershipId!,
      );
      await ref
          .read(draftStoreProvider)
          .purgeScope(tenantId: scope.tenantId, userId: scope.userId);
      await ref.read(commandQueueProvider).purgeScope(scope);
    }
    await ref.read(authRepositoryProvider).logout();
    await ref.read(secureCacheProvider).purgeAll();
    state = const AsyncData(null);
  }
}
