import 'dart:convert';

import 'package:cryptography/cryptography.dart';
import 'package:drift/drift.dart';

import '../config/app_config.dart';
import 'app_database.dart';
import 'cache_key_store.dart';

class SecureCache {
  SecureCache(
    this.database,
    this.keys, {
    this.limit = AppConfig.offlineCacheLimit,
  });

  final AppDatabase database;
  final CacheKeyStore keys;
  final Duration limit;
  final AesGcm _algorithm = AesGcm.with256bits();

  Future<void> put({
    required String tenantId,
    required String userId,
    required String kind,
    required String remoteId,
    required Map<String, dynamic> payload,
  }) async {
    final key = await keys.loadOrCreate();
    final nonce = _algorithm.newNonce();
    final box = await _algorithm.encrypt(
      utf8.encode(jsonEncode(payload)),
      secretKey: key,
      nonce: nonce,
    );
    await database
        .into(database.cachedRecords)
        .insertOnConflictUpdate(
          CachedRecordsCompanion.insert(
            tenantId: tenantId,
            userId: userId,
            kind: kind,
            remoteId: remoteId,
            cipherText: base64Encode(box.cipherText),
            nonce: base64Encode(box.nonce),
            mac: base64Encode(box.mac.bytes),
            cachedAt: DateTime.now().toUtc(),
            lastAccessedAt: Value(DateTime.now().toUtc()),
          ),
        );
  }

  Future<Map<String, dynamic>?> get({
    required String tenantId,
    required String userId,
    required String kind,
    required String remoteId,
  }) async {
    final row = await _find(tenantId, userId, kind, remoteId);
    if (row == null) {
      return null;
    }
    if (DateTime.now().toUtc().difference(row.cachedAt.toUtc()) > limit) {
      await _delete(tenantId, userId, kind, remoteId);
      return null;
    }

    try {
      final key = await keys.loadOrCreate();
      final clear = await _algorithm.decrypt(
        SecretBox(
          base64Decode(row.cipherText),
          nonce: base64Decode(row.nonce),
          mac: Mac(base64Decode(row.mac)),
        ),
        secretKey: key,
      );
      await (database.update(database.cachedRecords)..where(
            (table) =>
                table.tenantId.equals(tenantId) &
                table.userId.equals(userId) &
                table.kind.equals(kind) &
                table.remoteId.equals(remoteId),
          ))
          .write(
            CachedRecordsCompanion(
              lastAccessedAt: Value(DateTime.now().toUtc()),
            ),
          );
      return jsonDecode(utf8.decode(clear)) as Map<String, dynamic>;
    } catch (_) {
      await _delete(tenantId, userId, kind, remoteId);
      return null;
    }
  }

  Future<void> purgeForUser({
    required String tenantId,
    required String userId,
  }) async {
    await (database.delete(database.cachedRecords)..where(
          (table) =>
              table.tenantId.equals(tenantId) & table.userId.equals(userId),
        ))
        .go();
  }

  Future<void> purgeAll() => database.delete(database.cachedRecords).go();

  Future<CachedRecord?> _find(
    String tenantId,
    String userId,
    String kind,
    String remoteId,
  ) {
    return (database.select(database.cachedRecords)..where(
          (table) =>
              table.tenantId.equals(tenantId) &
              table.userId.equals(userId) &
              table.kind.equals(kind) &
              table.remoteId.equals(remoteId),
        ))
        .getSingleOrNull();
  }

  Future<void> _delete(
    String tenantId,
    String userId,
    String kind,
    String remoteId,
  ) {
    return (database.delete(database.cachedRecords)..where(
          (table) =>
              table.tenantId.equals(tenantId) &
              table.userId.equals(userId) &
              table.kind.equals(kind) &
              table.remoteId.equals(remoteId),
        ))
        .go();
  }
}
