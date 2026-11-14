import 'package:drift/drift.dart';

import '../cache/app_database.dart';
import '../cache/payload_cipher.dart';

class DraftVersionConflict implements Exception {
  const DraftVersionConflict();
}

class DraftOrder {
  const DraftOrder({
    required this.tenantId,
    required this.userId,
    required this.draftId,
    required this.payload,
    required this.version,
    required this.createdAt,
    required this.updatedAt,
  });

  final String tenantId;
  final String userId;
  final String draftId;
  final Map<String, dynamic> payload;
  final int version;
  final DateTime createdAt;
  final DateTime updatedAt;
}

class PhotoUpload {
  const PhotoUpload({
    required this.uploadId,
    required this.draftId,
    required this.localPath,
    required this.mimeType,
    required this.checksum,
    required this.status,
    required this.attempts,
  });

  final String uploadId;
  final String draftId;
  final String localPath;
  final String mimeType;
  final String checksum;
  final String status;
  final int attempts;
}

class DraftStore {
  DraftStore(this.database, this.cipher);

  final AppDatabase database;
  final PayloadCipher cipher;

  Future<DraftOrder> save({
    required String tenantId,
    required String userId,
    required String draftId,
    required Map<String, dynamic> payload,
    int? expectedVersion,
  }) async {
    final existing = await _find(tenantId, userId, draftId);
    if (expectedVersion != null &&
        (existing?.version ?? 0) != expectedVersion) {
      throw const DraftVersionConflict();
    }

    final encrypted = await cipher.encrypt(payload);
    final now = DateTime.now().toUtc();
    final version = (existing?.version ?? 0) + 1;
    await database
        .into(database.draftOrders)
        .insertOnConflictUpdate(
          DraftOrdersCompanion.insert(
            tenantId: tenantId,
            userId: userId,
            draftId: draftId,
            payloadCipher: encrypted.cipherText,
            nonce: encrypted.nonce,
            mac: encrypted.mac,
            version: Value(version),
            createdAt: existing?.createdAt ?? now,
            updatedAt: now,
          ),
        );

    return DraftOrder(
      tenantId: tenantId,
      userId: userId,
      draftId: draftId,
      payload: payload,
      version: version,
      createdAt: existing?.createdAt ?? now,
      updatedAt: now,
    );
  }

  Future<DraftOrder?> get({
    required String tenantId,
    required String userId,
    required String draftId,
  }) {
    return _find(tenantId, userId, draftId);
  }

  Future<List<DraftOrder>> list({
    required String tenantId,
    required String userId,
  }) async {
    final rows =
        await (database.select(database.draftOrders)
              ..where(
                (table) =>
                    table.tenantId.equals(tenantId) &
                    table.userId.equals(userId),
              )
              ..orderBy([(table) => OrderingTerm.desc(table.updatedAt)]))
            .get();
    return Future.wait(
      rows.map((row) async {
        return DraftOrder(
          tenantId: row.tenantId,
          userId: row.userId,
          draftId: row.draftId,
          payload: await cipher.decrypt(
            EncryptedPayload(
              cipherText: row.payloadCipher,
              nonce: row.nonce,
              mac: row.mac,
            ),
          ),
          version: row.version,
          createdAt: row.createdAt,
          updatedAt: row.updatedAt,
        );
      }),
    );
  }

  Future<void> delete({
    required String tenantId,
    required String userId,
    required String draftId,
  }) {
    return (database.delete(database.draftOrders)..where(
          (table) =>
              table.tenantId.equals(tenantId) &
              table.userId.equals(userId) &
              table.draftId.equals(draftId),
        ))
        .go();
  }

  Future<void> enqueuePhoto({
    required String tenantId,
    required String userId,
    required String uploadId,
    required String draftId,
    required String localPath,
    required String mimeType,
    required String checksum,
  }) async {
    final now = DateTime.now().toUtc();
    await database
        .into(database.photoUploads)
        .insertOnConflictUpdate(
          PhotoUploadsCompanion.insert(
            tenantId: tenantId,
            userId: userId,
            uploadId: uploadId,
            draftId: draftId,
            localPath: localPath,
            mimeType: mimeType,
            checksum: checksum,
            createdAt: now,
            updatedAt: now,
          ),
        );
  }

  Future<List<PhotoUpload>> photos({
    required String tenantId,
    required String userId,
  }) async {
    final rows =
        await (database.select(database.photoUploads)
              ..where(
                (table) =>
                    table.tenantId.equals(tenantId) &
                    table.userId.equals(userId),
              )
              ..orderBy([(table) => OrderingTerm.asc(table.createdAt)]))
            .get();
    return rows
        .map(
          (row) => PhotoUpload(
            uploadId: row.uploadId,
            draftId: row.draftId,
            localPath: row.localPath,
            mimeType: row.mimeType,
            checksum: row.checksum,
            status: row.status,
            attempts: row.attempts,
          ),
        )
        .toList();
  }

  Future<void> purgeScope({
    required String tenantId,
    required String userId,
  }) async {
    await (database.delete(database.draftOrders)..where(
          (table) =>
              table.tenantId.equals(tenantId) & table.userId.equals(userId),
        ))
        .go();
    await (database.delete(database.photoUploads)..where(
          (table) =>
              table.tenantId.equals(tenantId) & table.userId.equals(userId),
        ))
        .go();
  }

  Future<DraftOrder?> _find(
    String tenantId,
    String userId,
    String draftId,
  ) async {
    final row =
        await (database.select(database.draftOrders)..where(
              (table) =>
                  table.tenantId.equals(tenantId) &
                  table.userId.equals(userId) &
                  table.draftId.equals(draftId),
            ))
            .getSingleOrNull();
    if (row == null) {
      return null;
    }
    return DraftOrder(
      tenantId: row.tenantId,
      userId: row.userId,
      draftId: row.draftId,
      payload: await cipher.decrypt(
        EncryptedPayload(
          cipherText: row.payloadCipher,
          nonce: row.nonce,
          mac: row.mac,
        ),
      ),
      version: row.version,
      createdAt: row.createdAt,
      updatedAt: row.updatedAt,
    );
  }
}
