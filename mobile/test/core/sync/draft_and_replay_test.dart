import 'dart:async';
import 'dart:io';

import 'package:drift/drift.dart' show driftRuntimeOptions;
import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:netclean_staff/core/auth/secret_store.dart';
import 'package:netclean_staff/core/cache/app_database.dart';
import 'package:netclean_staff/core/cache/cache_key_store.dart';
import 'package:netclean_staff/core/cache/payload_cipher.dart';
import 'package:netclean_staff/core/network/api_exception.dart';
import 'package:netclean_staff/core/sync/command_queue.dart';
import 'package:netclean_staff/core/sync/command_replay_service.dart';
import 'package:netclean_staff/core/sync/draft_store.dart';
import 'package:path/path.dart' as p;

class _FakeSender implements CommandSender {
  _FakeSender(this.handler);

  final Future<Map<String, dynamic>> Function(QueuedCommand command) handler;
  final List<String> sentIds = [];

  @override
  Future<Map<String, dynamic>> send(QueuedCommand command) {
    sentIds.add(command.clientCommandId);
    return handler(command);
  }
}

void main() {
  driftRuntimeOptions.dontWarnAboutMultipleDatabases = true;
  late AppDatabase database;
  late DraftStore drafts;
  late CommandQueue queue;
  const scope = SyncScope(tenantId: 'tenant-a', userId: 'member-a');

  setUp(() {
    database = AppDatabase(NativeDatabase.memory());
    final secrets = MemorySecretStore();
    final cipher = PayloadCipher(CacheKeyStore(secrets));
    drafts = DraftStore(database, cipher);
    queue = CommandQueue(database, cipher);
  });

  tearDown(() => database.close());

  test(
    'draft versions, conflicts, photos, and tenant isolation are enforced',
    () async {
      final first = await drafts.save(
        tenantId: 'tenant-a',
        userId: 'member-a',
        draftId: 'draft-1',
        payload: {
          'lines': [
            {'service_id': 'service-1'},
          ],
        },
      );
      expect(first.version, 1);

      final second = await drafts.save(
        tenantId: 'tenant-a',
        userId: 'member-a',
        draftId: 'draft-1',
        payload: {
          'lines': [
            {'service_id': 'service-2'},
          ],
        },
        expectedVersion: 1,
      );
      expect(second.version, 2);
      expect(second.payload['lines'], [
        {'service_id': 'service-2'},
      ]);

      await expectLater(
        drafts.save(
          tenantId: 'tenant-a',
          userId: 'member-a',
          draftId: 'draft-1',
          payload: {'lines': []},
          expectedVersion: 1,
        ),
        throwsA(isA<DraftVersionConflict>()),
      );
      expect(
        await drafts.get(
          tenantId: 'tenant-b',
          userId: 'member-a',
          draftId: 'draft-1',
        ),
        isNull,
      );

      await drafts.enqueuePhoto(
        tenantId: 'tenant-a',
        userId: 'member-a',
        uploadId: 'upload-1',
        draftId: 'draft-1',
        localPath: '/private/photo.jpg',
        mimeType: 'image/jpeg',
        checksum: 'checksum',
      );
      expect(
        await drafts.photos(tenantId: 'tenant-a', userId: 'member-a'),
        hasLength(1),
      );
      expect(
        await drafts.photos(tenantId: 'tenant-b', userId: 'member-a'),
        isEmpty,
      );
    },
  );

  test(
    'commands replay in dependency order and preserve idempotency keys',
    () async {
      final sender = _FakeSender(
        (command) async => {
          'data': {'ok': true},
        },
      );
      final first = await queue.enqueue(
        scope: scope,
        clientCommandId: 'command-1',
        endpoint: '/orders',
        payload: {'client_command_id': 'command-1'},
      );
      await queue.enqueue(
        scope: scope,
        clientCommandId: 'command-2',
        endpoint: '/orders/child',
        payload: {'client_command_id': 'command-2'},
        dependencies: ['command-1'],
      );

      final report = await CommandReplayService(queue, sender).run(scope);

      expect(report.authRequired, isFalse);
      expect(report.scopeLocked, isFalse);
      expect(
        report.commands.every(
          (command) => command.status == CommandStatus.completed,
        ),
        isTrue,
      );
      expect(sender.sentIds, ['command-1', 'command-2']);
      expect(first.clientCommandId, 'command-1');
    },
  );

  test('dependency failure blocks downstream commands', () async {
    final sender = _FakeSender((command) async {
      if (command.clientCommandId == 'command-1') {
        throw const ApiException(
          code: 'VALIDATION_FAILED',
          message: 'bad',
          statusCode: 422,
        );
      }
      return {'data': {}};
    });
    await queue.enqueue(
      scope: scope,
      clientCommandId: 'command-1',
      endpoint: '/orders',
      payload: {'client_command_id': 'command-1'},
    );
    await queue.enqueue(
      scope: scope,
      clientCommandId: 'command-2',
      endpoint: '/child',
      payload: {'client_command_id': 'command-2'},
      dependencies: ['command-1'],
    );

    final report = await CommandReplayService(queue, sender).run(scope);
    final statuses = {
      for (final command in report.commands)
        command.clientCommandId: command.status,
    };

    expect(statuses['command-1'], CommandStatus.rejected);
    expect(statuses['command-2'], CommandStatus.blocked);
    expect(sender.sentIds, ['command-1']);
  });

  test(
    '401 stops replay, 403 locks scope, and 409 records a conflict',
    () async {
      await queue.enqueue(
        scope: scope,
        clientCommandId: 'unauthorized',
        endpoint: '/orders',
        payload: <String, dynamic>{},
      );
      final unauthorized = await CommandReplayService(
        queue,
        _FakeSender((command) async {
          throw const ApiException(
            code: 'UNAUTHENTICATED',
            message: 'login',
            statusCode: 401,
          );
        }),
      ).run(scope);
      expect(unauthorized.authRequired, isTrue);

      final fresh = AppDatabase(NativeDatabase.memory());
      final cipher = PayloadCipher(CacheKeyStore(MemorySecretStore()));
      final freshQueue = CommandQueue(fresh, cipher);
      await freshQueue.enqueue(
        scope: scope,
        clientCommandId: 'forbidden',
        endpoint: '/orders',
        payload: <String, dynamic>{},
      );
      final forbidden = await CommandReplayService(
        freshQueue,
        _FakeSender((command) async {
          throw const ApiException(
            code: 'FORBIDDEN',
            message: 'scope',
            statusCode: 403,
          );
        }),
      ).run(scope);
      expect(forbidden.scopeLocked, isTrue);
      expect(
        (await freshQueue.find(scope, 'forbidden'))!.status,
        CommandStatus.blocked,
      );

      await queue.enqueue(
        scope: scope,
        clientCommandId: 'conflict',
        endpoint: '/orders',
        payload: <String, dynamic>{},
      );
      final conflict = await CommandReplayService(
        queue,
        _FakeSender((command) async {
          throw const ApiException(
            code: 'VERSION_CONFLICT',
            message: 'stale',
            statusCode: 409,
          );
        }),
      ).run(scope);
      expect(
        (await queue.find(scope, 'conflict'))!.status,
        CommandStatus.conflict,
      );
      expect(conflict.commands, isNotEmpty);
      await fresh.close();
    },
  );

  test('timeout retries the same command id after backoff', () async {
    var attempts = 0;
    final sender = _FakeSender((command) async {
      attempts++;
      if (attempts == 1) {
        throw TimeoutException('timeout');
      }
      return {
        'data': {'ok': true},
      };
    });
    await queue.enqueue(
      scope: scope,
      clientCommandId: 'timeout-command',
      endpoint: '/orders',
      payload: <String, dynamic>{},
    );

    final first = await CommandReplayService(queue, sender).run(scope);
    final pending = first.commands.single;
    expect(pending.status, CommandStatus.pending);
    expect(pending.errorCode, 'TIMEOUT');
    expect(sender.sentIds, ['timeout-command']);

    await queue.mark(
      pending,
      status: CommandStatus.pending,
      nextRetryAt: DateTime.now().toUtc().subtract(const Duration(seconds: 1)),
    );
    final second = await CommandReplayService(queue, sender).run(scope);
    expect(second.commands.single.status, CommandStatus.completed);
    expect(sender.sentIds, ['timeout-command', 'timeout-command']);
  });

  test('draft and queued command survive database restart', () async {
    final directory = await Directory.systemTemp.createTemp('netclean-sync-');
    final file = File(p.join(directory.path, 'sync.sqlite'));
    final secrets = MemorySecretStore();
    final cipher = PayloadCipher(CacheKeyStore(secrets));
    final firstDb = AppDatabase(NativeDatabase(file));
    final firstDrafts = DraftStore(firstDb, cipher);
    final firstQueue = CommandQueue(firstDb, cipher);
    await firstDrafts.save(
      tenantId: 'tenant-a',
      userId: 'member-a',
      draftId: 'draft-restart',
      payload: {'note': 'survives'},
    );
    await firstQueue.enqueue(
      scope: scope,
      clientCommandId: 'restart-command',
      endpoint: '/orders',
      payload: {'client_command_id': 'restart-command'},
    );
    await firstDb.close();

    final secondDb = AppDatabase(NativeDatabase(file));
    final secondDrafts = DraftStore(
      secondDb,
      PayloadCipher(CacheKeyStore(secrets)),
    );
    final secondQueue = CommandQueue(
      secondDb,
      PayloadCipher(CacheKeyStore(secrets)),
    );
    expect(
      (await secondDrafts.get(
        tenantId: 'tenant-a',
        userId: 'member-a',
        draftId: 'draft-restart',
      ))?.payload,
      {'note': 'survives'},
    );
    expect(
      (await secondQueue.find(scope, 'restart-command'))?.clientCommandId,
      'restart-command',
    );
    await secondDb.close();
    await directory.delete(recursive: true);
  });
}
