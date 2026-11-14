import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/session/sync_scope_provider.dart';
import '../../../core/sync/command_queue.dart';
import '../../../core/sync/sync_controller.dart';

class SyncScreen extends ConsumerWidget {
  const SyncScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final scope = ref.watch(syncScopeProvider);
    if (scope == null) {
      return const Center(child: Text('Konteks tenant belum tersedia.'));
    }

    final state = ref.watch(syncControllerProvider);
    final report = ref.read(syncControllerProvider.notifier).lastReport;

    return state.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (error, stackTrace) =>
          Center(child: Text('Sinkronisasi gagal: $error')),
      data: (commands) => ListView(
        padding: const EdgeInsets.all(24),
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  'Sinkronisasi',
                  style: Theme.of(context).textTheme.headlineSmall,
                ),
              ),
              FilledButton.icon(
                onPressed: () =>
                    ref.read(syncControllerProvider.notifier).run(),
                icon: const Icon(Icons.sync),
                label: const Text('Sinkronkan'),
              ),
            ],
          ),
          if (report?.authRequired == true)
            const _Notice(
              text: 'Sesi berakhir. Login ulang untuk melanjutkan antrean.',
            ),
          if (report?.scopeLocked == true)
            const _Notice(text: 'Akses scope dikunci. Antrean dihentikan.'),
          if (commands.isEmpty)
            const Padding(
              padding: EdgeInsets.only(top: 24),
              child: Text('Tidak ada command tertunda.'),
            ),
          ...commands.map(
            (command) => Card(
              child: ListTile(
                leading: Icon(_icon(command.status)),
                title: Text(command.endpoint),
                subtitle: Text(
                  '${command.status.name} • percobaan ${command.attempts}'
                  '${command.errorCode == null ? '' : ' • ${command.errorCode}'}',
                ),
                trailing: Text(command.clientCommandId.substring(0, 8)),
              ),
            ),
          ),
        ],
      ),
    );
  }

  IconData _icon(CommandStatus status) {
    return switch (status) {
      CommandStatus.completed => Icons.check_circle_outline,
      CommandStatus.conflict => Icons.warning_amber_outlined,
      CommandStatus.rejected => Icons.cancel_outlined,
      CommandStatus.blocked => Icons.lock_outline,
      CommandStatus.sent => Icons.cloud_upload_outlined,
      CommandStatus.pending => Icons.schedule_outlined,
    };
  }
}

class _Notice extends StatelessWidget {
  const _Notice({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 16),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.errorContainer,
          borderRadius: BorderRadius.circular(12),
        ),
        child: Text(text),
      ),
    );
  }
}
