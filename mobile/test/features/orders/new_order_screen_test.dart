import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:netclean_staff/core/auth/auth_session.dart';
import 'package:netclean_staff/core/session/session_controller.dart';
import 'package:netclean_staff/features/orders/presentation/new_order_screen.dart';

class _NoBranchSession extends SessionController {
  @override
  Future<AuthSession?> build() async {
    return AuthSession(
      token: 'token',
      expiresAt: DateTime.now().toUtc().add(const Duration(hours: 1)),
      deviceId: 'device',
      tenantId: 'tenant',
      membershipId: 'membership',
    );
  }
}

void main() {
  testWidgets('new order screen explains when no branch is assigned', (
    tester,
  ) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          sessionControllerProvider.overrideWith(_NoBranchSession.new),
        ],
        child: const MaterialApp(home: Scaffold(body: NewOrderScreen())),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Cabang belum ditugaskan ke akun ini.'), findsOneWidget);
    expect(find.text('Coba lagi'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
