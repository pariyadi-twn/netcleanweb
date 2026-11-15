import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/session/sync_scope_provider.dart';
import '../../../core/sync/command_queue.dart';
import '../../../core/sync/sync_controller.dart';
import '../../../core/ui/page_header.dart';
import '../../../core/ui/responsive.dart';
import '../../../core/ui/theme.dart';

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
    final padding = Responsive.horizontalPadding(
      MediaQuery.sizeOf(context).width,
    );

    return DecoratedBox(
      decoration: const BoxDecoration(gradient: AppTheme.canvasGradient),
      child: state.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, stackTrace) =>
            Center(child: Text('Sinkronisasi gagal: $error')),
        data: (commands) => ResponsiveCenter(
          child: ListView(
            padding: EdgeInsets.fromLTRB(padding, 18, padding, 32),
            children: [
              AppPageHeader(
                eyebrow: 'Perangkat',
                title: 'Sinkronisasi',
                subtitle: commands.isEmpty
                    ? 'Tidak ada antrean yang perlu dikirim.'
                    : '${commands.length} command di antrean perangkat ini.',
                trailing: FilledButton.icon(
                  onPressed: () =>
                      ref.read(syncControllerProvider.notifier).run(),
                  icon: const Icon(Icons.sync_rounded, size: 18),
                  label: const Text('Sinkron'),
                ),
              ),
              if (report?.authRequired == true)
                const _Notice(
                  text: 'Sesi berakhir. Login ulang untuk melanjutkan antrean.',
                ),
              if (report?.scopeLocked == true)
                const _Notice(text: 'Akses scope dikunci. Antrean dihentikan.'),
              const SizedBox(height: 18),
              if (commands.isEmpty)
                const _EmptyQueue()
              else
                ...commands.map(
                  (command) => Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: _CommandCard(command: command),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _CommandCard extends StatelessWidget {
  const _CommandCard({required this.command});

  final QueuedCommand command;

  @override
  Widget build(BuildContext context) {
    final (color, background) = _palette(command.status);

    return Card(
      child: ListTile(
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        leading: Container(
          width: 44,
          height: 44,
          decoration: BoxDecoration(
            color: background,
            borderRadius: BorderRadius.circular(14),
          ),
          child: Icon(_icon(command.status), color: color, size: 22),
        ),
        title: Text(
          command.endpoint,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: Theme.of(context).textTheme.titleSmall,
        ),
        subtitle: Padding(
          padding: const EdgeInsets.only(top: 4),
          child: Text(
            '${command.status.name} • percobaan ${command.attempts}'
            '${command.errorCode == null ? '' : ' • ${command.errorCode}'}',
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ),
        trailing: Container(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
          decoration: BoxDecoration(
            color: AppTheme.surfaceMuted,
            borderRadius: BorderRadius.circular(8),
          ),
          child: Text(
            command.clientCommandId.substring(0, 8),
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: AppTheme.textSecondary,
              letterSpacing: 0.4,
            ),
          ),
        ),
      ),
    );
  }

  IconData _icon(CommandStatus status) {
    return switch (status) {
      CommandStatus.completed => Icons.check_circle_outline_rounded,
      CommandStatus.conflict => Icons.warning_amber_rounded,
      CommandStatus.rejected => Icons.cancel_outlined,
      CommandStatus.blocked => Icons.lock_outline_rounded,
      CommandStatus.sent => Icons.cloud_upload_outlined,
      CommandStatus.pending => Icons.schedule_rounded,
    };
  }

  (Color, Color) _palette(CommandStatus status) {
    return switch (status) {
      CommandStatus.completed => (
        const Color(0xFF0F6B4F),
        const Color(0xFFE7F7EF),
      ),
      CommandStatus.conflict => (
        const Color(0xFF8A5A00),
        const Color(0xFFFFF5E0),
      ),
      CommandStatus.rejected => (
        const Color(0xFFB42318),
        const Color(0xFFFEECE9),
      ),
      CommandStatus.blocked => (
        const Color(0xFFB42318),
        const Color(0xFFFEECE9),
      ),
      CommandStatus.sent => (const Color(0xFF0E5F8A), const Color(0xFFE8F4FB)),
      CommandStatus.pending => (AppTheme.primary, AppTheme.primarySoft),
    };
  }
}

class _EmptyQueue extends StatelessWidget {
  const _EmptyQueue();

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          children: [
            const AppIconTile(icon: Icons.cloud_done_outlined, size: 56),
            const SizedBox(height: 14),
            Text(
              'Semua sudah sinkron',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 4),
            Text(
              'Tidak ada command yang tertunda di perangkat ini.',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ),
      ),
    );
  }
}

class _Notice extends StatelessWidget {
  const _Notice({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Padding(
      padding: const EdgeInsets.only(top: 14),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: scheme.errorContainer,
          borderRadius: BorderRadius.circular(16),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(Icons.info_outline_rounded, color: scheme.error, size: 19),
            const SizedBox(width: 9),
            Expanded(
              child: Text(
                text,
                style: Theme.of(
                  context,
                ).textTheme.bodySmall?.copyWith(color: scheme.error),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
