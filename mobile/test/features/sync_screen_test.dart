import 'package:drift/drift.dart' show driftRuntimeOptions;
import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:netclean_staff/core/auth/secret_store.dart';
import 'package:netclean_staff/core/cache/app_database.dart';
import 'package:netclean_staff/core/cache/cache_key_store.dart';
import 'package:netclean_staff/core/cache/payload_cipher.dart';
import 'package:netclean_staff/core/providers.dart';
import 'package:netclean_staff/core/session/sync_scope_provider.dart';
import 'package:netclean_staff/core/sync/command_queue.dart';
import 'package:netclean_staff/features/sync/presentation/sync_screen.dart';

void main() {
  driftRuntimeOptions.dontWarnAboutMultipleDatabases = true;

  testWidgets(
    'sync screen displays pending, completed, conflict, and rejected states',
    (tester) async {
      final database = AppDatabase(NativeDatabase.memory());
      addTearDown(database.close);
      final cipher = PayloadCipher(CacheKeyStore(MemorySecretStore()));
      final queue = CommandQueue(database, cipher);
      const scope = SyncScope(tenantId: 'tenant-a', userId: 'member-a');

      final completed = await queue.enqueue(
        scope: scope,
        clientCommandId: '11111111-1111-4111-8111-111111111111',
        endpoint: '/orders',
        payload: <String, dynamic>{},
      );
      await queue.mark(completed, status: CommandStatus.completed);
      final conflict = await queue.enqueue(
        scope: scope,
        clientCommandId: '22222222-2222-4222-8222-222222222222',
        endpoint: '/orders/conflict',
        payload: <String, dynamic>{},
      );
      await queue.mark(
        conflict,
        status: CommandStatus.conflict,
        errorCode: 'VERSION_CONFLICT',
      );
      final rejected = await queue.enqueue(
        scope: scope,
        clientCommandId: '33333333-3333-4333-8333-333333333333',
        endpoint: '/orders/rejected',
        payload: <String, dynamic>{},
      );
      await queue.mark(
        rejected,
        status: CommandStatus.rejected,
        errorCode: 'VALIDATION_FAILED',
      );

      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            syncScopeProvider.overrideWithValue(scope),
            commandQueueProvider.overrideWithValue(queue),
          ],
          child: const MaterialApp(home: Scaffold(body: SyncScreen())),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.textContaining('completed'), findsAtLeastNWidgets(1));
      expect(find.textContaining('conflict'), findsAtLeastNWidgets(1));
      expect(find.textContaining('rejected'), findsAtLeastNWidgets(1));
      expect(tester.takeException(), isNull);
    },
  );
}
