import 'package:flutter/material.dart';

import '../auth/auth_session.dart';
import 'page_header.dart';
import 'status_chip.dart';
import 'theme.dart';

class SessionContextBar extends StatelessWidget {
  const SessionContextBar({super.key, required this.session});

  final AuthSession session;

  @override
  Widget build(BuildContext context) {
    final tenant = session.tenantId;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const AppIconTile(icon: Icons.shield_outlined, size: 42),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Konteks perangkat',
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                      const SizedBox(height: 2),
                      Text(
                        'Tenant dan sesi aktif',
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ],
                  ),
                ),
                const StatusChip(
                  label: 'Aman',
                  icon: Icons.verified_user_outlined,
                  tone: StatusTone.success,
                ),
              ],
            ),
            const SizedBox(height: 16),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                _ContextTile(
                  icon: Icons.business_outlined,
                  label: 'Tenant',
                  value: tenant == null ? 'Belum dipilih' : _shortId(tenant),
                ),
                _ContextTile(
                  icon: Icons.smartphone_outlined,
                  label: 'Perangkat',
                  value: _shortId(session.deviceId),
                ),
                _ContextTile(
                  icon: Icons.account_tree_outlined,
                  label: 'Cabang',
                  value: '${session.branchIds.length}',
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  String _shortId(String value) {
    if (value.length <= 12) {
      return value;
    }

    return '${value.substring(0, 8)}...';
  }
}

class _ContextTile extends StatelessWidget {
  const _ContextTile({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: AppTheme.surfaceMuted,
        borderRadius: BorderRadius.circular(15),
        border: Border.all(color: AppTheme.divider),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 18, color: AppTheme.primary),
          const SizedBox(width: 9),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label.toUpperCase(),
                style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  fontSize: 9,
                  letterSpacing: 0.8,
                  color: AppTheme.textSecondary,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                value,
                style: Theme.of(
                  context,
                ).textTheme.titleSmall?.copyWith(fontSize: 13),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
