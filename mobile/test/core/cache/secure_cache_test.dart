import 'dart:io';

import 'package:drift/drift.dart' show driftRuntimeOptions;
import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:netclean_staff/core/auth/secret_store.dart';
import 'package:netclean_staff/core/cache/app_database.dart';
import 'package:netclean_staff/core/cache/cache_key_store.dart';
import 'package:netclean_staff/core/cache/secure_cache.dart';
import 'package:path/path.dart' as p;
import 'package:sqlite3/sqlite3.dart';

void main() {
  driftRuntimeOptions.dontWarnAboutMultipleDatabases = true;
  late AppDatabase database;

  setUp(() {
    database = AppDatabase(NativeDatabase.memory());
  });

  tearDown(() async {
    await database.close();
  });

  test('cache is encrypted and isolated by tenant and user', () async {
    final secrets = MemorySecretStore();
    final cache = SecureCache(database, CacheKeyStore(secrets));

    await cache.put(
      tenantId: 'tenant-a',
      userId: 'user-1',
      kind: 'order',
      remoteId: 'order-1',
      payload: {'order_number': 'A-1'},
    );

    expect(
      await cache.get(
        tenantId: 'tenant-a',
        userId: 'user-1',
        kind: 'order',
        remoteId: 'order-1',
      ),
      {'order_number': 'A-1'},
    );
    expect(
      await cache.get(
        tenantId: 'tenant-b',
        userId: 'user-1',
        kind: 'order',
        remoteId: 'order-1',
      ),
      isNull,
    );
    expect(
      await cache.get(
        tenantId: 'tenant-a',
        userId: 'user-2',
        kind: 'order',
        remoteId: 'order-1',
      ),
      isNull,
    );

    final row = await database.select(database.cachedRecords).getSingle();
    expect(row.cipherText, isNot(contains('A-1')));
    expect(row.mac, isNotEmpty);
  });

  test(
    'missing or replaced key fails closed and removes unreadable cache',
    () async {
      final cache = SecureCache(database, CacheKeyStore(MemorySecretStore()));
      await cache.put(
        tenantId: 'tenant-a',
        userId: 'user-1',
        kind: 'order',
        remoteId: 'order-1',
        payload: {'order_number': 'A-1'},
      );

      final replacement = SecureCache(
        database,
        CacheKeyStore(MemorySecretStore()),
      );

      expect(
        await replacement.get(
          tenantId: 'tenant-a',
          userId: 'user-1',
          kind: 'order',
          remoteId: 'order-1',
        ),
        isNull,
      );
      expect(await database.select(database.cachedRecords).get(), isEmpty);
    },
  );

  test(
    'version one database upgrades without losing cached metadata',
    () async {
      final directory = await Directory.systemTemp.createTemp(
        'netclean-drift-',
      );
      final file = File(p.join(directory.path, 'cache.sqlite'));
      final legacy = sqlite3.open(file.path);
      legacy.execute('''
      CREATE TABLE cached_records (
        tenant_id TEXT NOT NULL,
        user_id TEXT NOT NULL,
        kind TEXT NOT NULL,
        remote_id TEXT NOT NULL,
        cipher_text TEXT NOT NULL,
        nonce TEXT NOT NULL,
        mac TEXT NOT NULL,
        cached_at INTEGER NOT NULL,
        PRIMARY KEY (tenant_id, user_id, kind, remote_id)
      )
    ''');
      legacy.execute('''
      INSERT INTO cached_records
        (tenant_id, user_id, kind, remote_id, cipher_text, nonce, mac, cached_at)
      VALUES ('tenant-a', 'user-1', 'order', 'order-1', 'cipher', 'nonce', 'mac', 0)
    ''');
      legacy.execute('PRAGMA user_version = 1');
      legacy.close();

      final upgraded = AppDatabase(NativeDatabase(file));
      final rows = await upgraded
          .customSelect('SELECT * FROM cached_records')
          .get();
      expect(rows, hasLength(1));
      expect(rows.single.data.containsKey('last_accessed_at'), isTrue);
      await upgraded.close();
      await directory.delete(recursive: true);
    },
  );
}
