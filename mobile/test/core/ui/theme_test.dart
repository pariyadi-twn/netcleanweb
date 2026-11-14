import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:netclean_staff/core/ui/status_chip.dart';
import 'package:netclean_staff/core/ui/theme.dart';

void main() {
  testWidgets('theme exposes semantic status colors and 48 pixel actions', (
    tester,
  ) async {
    final semantics = tester.ensureSemantics();

    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        home: const Scaffold(
          body: Column(
            children: [
              FilledButton(onPressed: null, child: Text('Simpan')),
              StatusChip(
                label: 'Berhasil',
                icon: Icons.check_circle_outline,
                tone: StatusTone.success,
              ),
            ],
          ),
        ),
      ),
    );

    final buttonSize = tester.getSize(find.byType(FilledButton));
    final palette = AppTheme.light.extension<AppStatusColors>();

    expect(buttonSize.height, greaterThanOrEqualTo(48));
    expect(palette, isNotNull);
    expect(find.bySemanticsLabel(RegExp(r'^Status: Berhasil')), findsOneWidget);
    expect(tester.takeException(), isNull);
    semantics.dispose();
  });
}
