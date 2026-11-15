import 'package:flutter/material.dart';

import 'theme.dart';

class NetCleanMark extends StatelessWidget {
  const NetCleanMark({super.key, this.size = 40, this.light = false});

  final double size;
  final bool light;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        gradient: AppTheme.accentGradient,
        borderRadius: BorderRadius.circular(size * 0.32),
        boxShadow: light
            ? null
            : [
                BoxShadow(
                  color: AppTheme.primary.withValues(alpha: 0.18),
                  blurRadius: 18,
                  offset: const Offset(0, 8),
                ),
              ],
      ),
      alignment: Alignment.center,
      child: Text(
        'N',
        style: TextStyle(
          color: AppTheme.primaryDeep,
          fontSize: size * 0.44,
          fontWeight: FontWeight.w900,
          letterSpacing: -1,
          height: 1,
        ),
      ),
    );
  }
}

class NetCleanLockup extends StatelessWidget {
  const NetCleanLockup({
    super.key,
    this.markSize = 38,
    this.onDark = false,
    this.showSubtitle = true,
  });

  final double markSize;
  final bool onDark;
  final bool showSubtitle;

  @override
  Widget build(BuildContext context) {
    final titleColor = onDark ? Colors.white : AppTheme.text;
    final subtitleColor = onDark
        ? Colors.white.withValues(alpha: 0.48)
        : AppTheme.textSecondary;

    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        NetCleanMark(size: markSize, light: true),
        const SizedBox(width: 10),
        Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'NetClean',
              style: TextStyle(
                color: titleColor,
                fontSize: markSize * 0.38,
                fontWeight: FontWeight.w800,
                letterSpacing: -0.4,
                height: 1.05,
              ),
            ),
            if (showSubtitle) ...[
              const SizedBox(height: 3),
              Text(
                'LAUNDRY OS',
                style: TextStyle(
                  color: subtitleColor,
                  fontSize: 8.5,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 1.5,
                  height: 1,
                ),
              ),
            ],
          ],
        ),
      ],
    );
  }
}
