import 'dart:async';

import '../network/api_client.dart';
import '../network/api_exception.dart';
import 'command_queue.dart';

abstract class CommandSender {
  Future<Map<String, dynamic>> send(QueuedCommand command);
}

class ApiCommandSender implements CommandSender {
  ApiCommandSender(this.api);

  final ApiClient api;

  @override
  Future<Map<String, dynamic>> send(QueuedCommand command) {
    return api.post(
      command.endpoint,
      body: command.payload,
      tenantId: command.scope.tenantId,
      idempotencyKey: command.clientCommandId,
    );
  }
}

class SyncReport {
  const SyncReport({
    required this.commands,
    this.authRequired = false,
    this.scopeLocked = false,
  });

  final List<QueuedCommand> commands;
  final bool authRequired;
  final bool scopeLocked;
}

class CommandReplayService {
  CommandReplayService(this.queue, this.sender);

  final CommandQueue queue;
  final CommandSender sender;

  Future<SyncReport> run(SyncScope scope) async {
    final commands = await queue.list(scope);
    final byId = {
      for (final command in commands) command.clientCommandId: command,
    };
    final statuses = {
      for (final command in commands) command.clientCommandId: command.status,
    };
    var authRequired = false;
    var scopeLocked = false;

    for (final command in commands) {
      if (command.status.isFinal) {
        continue;
      }

      if (command.dependencies.any((id) => !byId.containsKey(id))) {
        await _mark(
          command,
          statuses,
          CommandStatus.blocked,
          errorCode: 'DEPENDENCY_MISSING',
        );
        continue;
      }
      if (command.dependencies.any((id) => statuses[id]?.isFailure ?? false)) {
        await _mark(
          command,
          statuses,
          CommandStatus.blocked,
          errorCode: 'DEPENDENCY_FAILED',
        );
        continue;
      }
      if (command.dependencies.any(
        (id) => statuses[id] != CommandStatus.completed,
      )) {
        continue;
      }
      if (command.nextRetryAt != null &&
          command.nextRetryAt!.isAfter(DateTime.now().toUtc())) {
        continue;
      }

      await queue.mark(
        command,
        status: CommandStatus.sent,
        attempts: command.attempts + 1,
      );
      statuses[command.clientCommandId] = CommandStatus.sent;

      try {
        final response = await sender.send(command);
        await _mark(
          command,
          statuses,
          CommandStatus.completed,
          response: response,
          attempts: command.attempts + 1,
        );
      } on ApiException catch (error) {
        switch (error.statusCode) {
          case 401:
            await _mark(
              command,
              statuses,
              CommandStatus.pending,
              errorCode: error.code,
              attempts: command.attempts + 1,
            );
            return SyncReport(
              commands: await queue.list(scope),
              authRequired: true,
              scopeLocked: scopeLocked,
            );
          case 403:
            await _mark(
              command,
              statuses,
              CommandStatus.blocked,
              errorCode: error.code,
              attempts: command.attempts + 1,
            );
            return SyncReport(
              commands: await queue.list(scope),
              authRequired: authRequired,
              scopeLocked: true,
            );
          case 409:
            await _mark(
              command,
              statuses,
              CommandStatus.conflict,
              errorCode: error.code,
              attempts: command.attempts + 1,
            );
          case 422:
            await _mark(
              command,
              statuses,
              CommandStatus.rejected,
              errorCode: error.code,
              attempts: command.attempts + 1,
            );
          case 429:
            await _mark(
              command,
              statuses,
              CommandStatus.pending,
              errorCode: error.code,
              nextRetryAt: DateTime.now().toUtc().add(
                Duration(seconds: error.retryAfterSeconds ?? 60),
              ),
              attempts: command.attempts + 1,
            );
          default:
            await _mark(
              command,
              statuses,
              CommandStatus.pending,
              errorCode: error.code,
              nextRetryAt: DateTime.now().toUtc().add(
                const Duration(seconds: 30),
              ),
              attempts: command.attempts + 1,
            );
        }
      } on TimeoutException {
        await _mark(
          command,
          statuses,
          CommandStatus.pending,
          errorCode: 'TIMEOUT',
          nextRetryAt: DateTime.now().toUtc().add(const Duration(seconds: 30)),
          attempts: command.attempts + 1,
        );
      } catch (_) {
        await _mark(
          command,
          statuses,
          CommandStatus.pending,
          errorCode: 'NETWORK_ERROR',
          nextRetryAt: DateTime.now().toUtc().add(const Duration(seconds: 30)),
          attempts: command.attempts + 1,
        );
      }
    }

    return SyncReport(
      commands: await queue.list(scope),
      authRequired: authRequired,
      scopeLocked: scopeLocked,
    );
  }

  Future<void> _mark(
    QueuedCommand command,
    Map<String, CommandStatus> statuses,
    CommandStatus status, {
    String? errorCode,
    Map<String, dynamic>? response,
    DateTime? nextRetryAt,
    int? attempts,
  }) async {
    await queue.mark(
      command,
      status: status,
      errorCode: errorCode,
      response: response,
      nextRetryAt: nextRetryAt,
      attempts: attempts,
    );
    statuses[command.clientCommandId] = status;
  }
}
