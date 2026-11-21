import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:netclean_staff/core/auth/auth_session.dart';
import 'package:netclean_staff/core/providers.dart';
import 'package:netclean_staff/core/scan/code_scanner.dart';
import 'package:netclean_staff/core/session/session_controller.dart';
import 'package:netclean_staff/features/orders/presentation/scan_screen.dart';

class _NoTenantSession extends SessionController {
  @override
  Future<AuthSession?> build() async {
    return AuthSession(
      token: 'token',
      expiresAt: DateTime.now().toUtc().add(const Duration(hours: 1)),
      deviceId: 'device',
    );
  }
}

class _FakeScanner implements CodeScanner {
  _FakeScanner(this.value);

  final String? value;
  int calls = 0;

  @override
  Future<String?> scan(
    BuildContext context, {
    String title = 'Scan kode',
  }) async {
    calls++;
    return value;
  }
}

void main() {
  testWidgets('scan screen explains missing tenant context', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          sessionControllerProvider.overrideWith(_NoTenantSession.new),
        ],
        child: const MaterialApp(home: ScanScreen()),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Order terbaru'));
    await tester.pumpAndSettle();

    expect(find.text('Konteks tenant belum tersedia.'), findsOneWidget);
    expect(find.textContaining('Scan nota'), findsWidgets);
    expect(tester.takeException(), isNull);
  });

  testWidgets('camera scan fills the order number and starts lookup', (
    tester,
  ) async {
    final scanner = _FakeScanner('uat-main-20261114-000001');
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          sessionControllerProvider.overrideWith(_NoTenantSession.new),
          codeScannerProvider.overrideWithValue(scanner),
        ],
        child: const MaterialApp(home: ScanScreen()),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Scan kamera'));
    await tester.pumpAndSettle();

    expect(scanner.calls, 1);
    expect(
      find.widgetWithText(TextField, 'UAT-MAIN-20261114-000001'),
      findsOneWidget,
    );
    expect(find.text('Konteks tenant belum tersedia.'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
