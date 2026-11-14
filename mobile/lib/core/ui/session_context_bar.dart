import 'package:flutter/material.dart';

import '../auth/auth_session.dart';
import 'status_chip.dart';

class SessionContextBar extends StatelessWidget {
  const SessionContextBar({super.key, required this.session});

  final AuthSession session;

  @override
  Widget build(BuildContext context) {
    final tenant = session.tenantId;
    final device = session.deviceId;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Wrap(
          spacing: 16,
          runSpacing: 12,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: [
            _ContextValue(
              icon: Icons.business_outlined,
              label: 'Tenant',
              value: tenant == null ? 'Belum dipilih' : _shortId(tenant),
            ),
            _ContextValue(
              icon: Icons.smartphone_outlined,
              label: 'Perangkat',
              value: _shortId(device),
            ),
            const StatusChip(
              label: 'Sesi aktif',
              icon: Icons.verified_user_outlined,
              tone: StatusTone.success,
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

class _ContextValue extends StatelessWidget {
  const _ContextValue({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 20, color: Theme.of(context).colorScheme.primary),
        const SizedBox(width: 8),
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: Theme.of(context).textTheme.labelMedium),
            Text(value, style: Theme.of(context).textTheme.bodyMedium),
          ],
        ),
      ],
    );
  }
}
