import 'package:flutter/widgets.dart';

enum WindowClass { compact, medium, expanded }

class Responsive {
  const Responsive._();

  static const compactBreakpoint = 600.0;
  static const expandedBreakpoint = 1024.0;
  static const navigationRailBreakpoint = 840.0;
  static const maxContentWidth = 1200.0;

  static WindowClass windowClass(double width) {
    if (width >= expandedBreakpoint) {
      return WindowClass.expanded;
    }
    if (width >= compactBreakpoint) {
      return WindowClass.medium;
    }
    return WindowClass.compact;
  }

  static int gridColumns(double width) {
    return switch (windowClass(width)) {
      WindowClass.compact => 1,
      WindowClass.medium => 2,
      WindowClass.expanded => 3,
    };
  }

  static bool usesNavigationRail(double width) =>
      width >= navigationRailBreakpoint;

  static double horizontalPadding(double width) {
    return switch (windowClass(width)) {
      WindowClass.compact => 16,
      WindowClass.medium => 24,
      WindowClass.expanded => 32,
    };
  }
}

class ResponsiveCenter extends StatelessWidget {
  const ResponsiveCenter({
    super.key,
    required this.child,
    this.maxWidth = Responsive.maxContentWidth,
  });

  final Widget child;
  final double maxWidth;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: ConstrainedBox(
        constraints: BoxConstraints(maxWidth: maxWidth),
        child: child,
      ),
    );
  }
}
