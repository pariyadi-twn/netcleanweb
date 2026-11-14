import 'dart:convert';

import 'package:drift/drift.dart';

import '../cache/app_database.dart';
import '../cache/payload_cipher.dart';

enum CommandStatus {
  pending,
  sent,
  completed,
  conflict,
  rejected,
  blocked;

  bool get isFinal =>
      this == completed ||
      this == conflict ||
      this == rejected ||
      this == blocked;
  bool get isFailure => this == conflict || this == rejected || this == blocked;
}

class SyncScope {
  const SyncScope({required this.tenantId, required this.userId});

  final String tenantId;
  final String userId;

  @override
  bool operator ==(Object other) =>
      other is SyncScope &&
      other.tenantId == tenantId &&
      other.userId == userId;

  @override
  int get hashCode => Object.hash(tenantId, userId);
}

class QueuedCommand {
  const QueuedCommand({
    required this.scope,
    required this.clientCommandId,
    required this.endpoint,
    required this.payload,
    required this.dependencies,
    required this.status,
    required this.attempts,
    required this.nextRetryAt,
    required this.errorCode,
    required this.response,
    required this.createdAt,
    required this.updatedAt,
  });

  final SyncScope scope;
  final String clientCommandId;
  final String endpoint;
  final Map<String, dynamic> payload;
  final List<String> dependencies;
  final CommandStatus status;
  final int attempts;
  final DateTime? nextRetryAt;
  final String? errorCode;
  final Map<String, dynamic>? response;
  final DateTime createdAt;
  final DateTime updatedAt;
}

class CommandQueue {
  CommandQueue(this.database, this.cipher);

  final AppDatabase database;
  final PayloadCipher cipher;

  Future<QueuedCommand> enqueue({
    required SyncScope scope,
    required String clientCommandId,
    required String endpoint,
    required Map<String, dynamic> payload,
    List<String> dependencies = const [],
  }) async {
    final existing = await find(scope, clientCommandId);
    if (existing != null) {
      return existing;
    }

    final encrypted = await cipher.encrypt(payload);
    final now = DateTime.now().toUtc();
    await database
        .into(database.pendingCommands)
        .insert(
          PendingCommandsCompanion.insert(
            tenantId: scope.tenantId,
            userId: scope.userId,
            clientCommandId: clientCommandId,
            endpoint: endpoint,
            payloadCipher: encrypted.cipherText,
            nonce: encrypted.nonce,
            mac: encrypted.mac,
            dependenciesJson: Value(jsonEncode(dependencies)),
            createdAt: now,
            updatedAt: now,
          ),
        );
    return (await find(scope, clientCommandId))!;
  }

  Future<QueuedCommand?> find(SyncScope scope, String clientCommandId) async {
    final row =
        await (database.select(database.pendingCommands)..where(
              (table) =>
                  table.tenantId.equals(scope.tenantId) &
                  table.userId.equals(scope.userId) &
                  table.clientCommandId.equals(clientCommandId),
            ))
            .getSingleOrNull();
    return row == null ? null : _decode(row);
  }

  Future<List<QueuedCommand>> list(SyncScope scope) async {
    final rows =
        await (database.select(database.pendingCommands)
              ..where(
                (table) =>
                    table.tenantId.equals(scope.tenantId) &
                    table.userId.equals(scope.userId),
              )
              ..orderBy([(table) => OrderingTerm.asc(table.createdAt)]))
            .get();
    return Future.wait(rows.map(_decode));
  }

  Future<void> mark(
    QueuedCommand command, {
    required CommandStatus status,
    String? errorCode,
    Map<String, dynamic>? response,
    DateTime? nextRetryAt,
    int? attempts,
  }) async {
    EncryptedPayload? encryptedResponse;
    if (response != null) {
      encryptedResponse = await cipher.encrypt(response);
    }
    await (database.update(database.pendingCommands)..where(
          (table) =>
              table.tenantId.equals(command.scope.tenantId) &
              table.userId.equals(command.scope.userId) &
              table.clientCommandId.equals(command.clientCommandId),
        ))
        .write(
          PendingCommandsCompanion(
            status: Value(status.name),
            attempts: attempts == null ? const Value.absent() : Value(attempts),
            nextRetryAt: Value(nextRetryAt),
            errorCode: Value(errorCode),
            responseCipher: encryptedResponse == null
                ? const Value.absent()
                : Value(encryptedResponse.cipherText),
            responseNonce: encryptedResponse == null
                ? const Value.absent()
                : Value(encryptedResponse.nonce),
            responseMac: encryptedResponse == null
                ? const Value.absent()
                : Value(encryptedResponse.mac),
            updatedAt: Value(DateTime.now().toUtc()),
          ),
        );
  }

  Future<void> purgeScope(SyncScope scope) {
    return (database.delete(database.pendingCommands)..where(
          (table) =>
              table.tenantId.equals(scope.tenantId) &
              table.userId.equals(scope.userId),
        ))
        .go();
  }

  Future<QueuedCommand> _decode(PendingCommand row) async {
    final response = row.responseCipher == null
        ? null
        : await cipher.decrypt(
            EncryptedPayload(
              cipherText: row.responseCipher!,
              nonce: row.responseNonce!,
              mac: row.responseMac!,
            ),
          );
    return QueuedCommand(
      scope: SyncScope(tenantId: row.tenantId, userId: row.userId),
      clientCommandId: row.clientCommandId,
      endpoint: row.endpoint,
      payload: await cipher.decrypt(
        EncryptedPayload(
          cipherText: row.payloadCipher,
          nonce: row.nonce,
          mac: row.mac,
        ),
      ),
      dependencies: (jsonDecode(row.dependenciesJson) as List).cast<String>(),
      status: CommandStatus.values.firstWhere(
        (status) => status.name == row.status,
        orElse: () => CommandStatus.pending,
      ),
      attempts: row.attempts,
      nextRetryAt: row.nextRetryAt,
      errorCode: row.errorCode,
      response: response,
      createdAt: row.createdAt,
      updatedAt: row.updatedAt,
    );
  }
}
