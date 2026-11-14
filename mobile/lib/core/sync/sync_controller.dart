import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../providers.dart';
import '../session/sync_scope_provider.dart';
import 'command_queue.dart';
import 'command_replay_service.dart';

final syncControllerProvider =
    AsyncNotifierProvider<SyncController, List<QueuedCommand>>(
      SyncController.new,
    );

class SyncController extends AsyncNotifier<List<QueuedCommand>> {
  SyncReport? lastReport;

  @override
  Future<List<QueuedCommand>> build() async {
    final scope = ref.watch(syncScopeProvider);
    if (scope == null) {
      return const [];
    }
    return ref.read(commandQueueProvider).list(scope);
  }

  Future<void> run() async {
    final scope = ref.read(syncScopeProvider);
    if (scope == null) {
      return;
    }
    state = const AsyncLoading();
    try {
      lastReport = await ref.read(commandReplayServiceProvider).run(scope);
      state = AsyncData(lastReport!.commands);
    } catch (error, stackTrace) {
      state = AsyncError(error, stackTrace);
    }
  }

  Future<void> refresh() async {
    final scope = ref.read(syncScopeProvider);
    if (scope != null) {
      state = AsyncData(await ref.read(commandQueueProvider).list(scope));
    }
  }
}
