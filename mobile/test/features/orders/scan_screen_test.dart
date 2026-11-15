import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:netclean_staff/core/auth/auth_session.dart';
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
    expect(find.textContaining('Simulasi scan'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
