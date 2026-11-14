import 'package:flutter/material.dart';

import 'theme.dart';

enum StatusTone { success, warning, danger, info }

class StatusChip extends StatelessWidget {
  const StatusChip({
    super.key,
    required this.label,
    required this.icon,
    this.tone = StatusTone.info,
  });

  final String label;
  final IconData icon;
  final StatusTone tone;

  @override
  Widget build(BuildContext context) {
    final palette =
        Theme.of(context).extension<AppStatusColors>() ??
        AppStatusColors.standard;
    final (foreground, background) = switch (tone) {
      StatusTone.success => (palette.successText, palette.successBackground),
      StatusTone.warning => (palette.warningText, palette.warningBackground),
      StatusTone.danger => (palette.dangerText, palette.dangerBackground),
      StatusTone.info => (palette.infoText, palette.infoBackground),
    };

    return Semantics(
      label: 'Status: $label',
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: background,
          borderRadius: BorderRadius.circular(999),
        ),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 16, color: foreground),
              const SizedBox(width: 6),
              Text(
                label,
                style: Theme.of(
                  context,
                ).textTheme.labelMedium?.copyWith(color: foreground),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
