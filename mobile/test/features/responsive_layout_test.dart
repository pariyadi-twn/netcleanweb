import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:netclean_staff/core/auth/auth_session.dart';
import 'package:netclean_staff/core/session/session_controller.dart';
import 'package:netclean_staff/core/ui/responsive.dart';
import 'package:netclean_staff/features/auth/presentation/login_screen.dart';
import 'package:netclean_staff/features/home/presentation/home_screen.dart';

class _FakeSessionController extends SessionController {
  @override
  Future<AuthSession?> build() async => null;
}

void main() {
  test('breakpoints choose one, two, and three column layouts', () {
    expect(Responsive.gridColumns(360), 1);
    expect(Responsive.gridColumns(800), 2);
    expect(Responsive.gridColumns(1280), 3);
    expect(Responsive.usesNavigationRail(360), isFalse);
    expect(Responsive.usesNavigationRail(1024), isTrue);
  });

  testWidgets('login screen uses one form on phone and two panes on tablet', (
    tester,
  ) async {
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);

    tester.view.physicalSize = const Size(360, 800);
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          sessionControllerProvider.overrideWith(_FakeSessionController.new),
        ],
        child: const MaterialApp(home: LoginScreen()),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('login-form')), findsOneWidget);
    expect(find.byKey(const Key('login-brand-panel')), findsNothing);
    expect(tester.takeException(), isNull);

    tester.view.physicalSize = const Size(1024, 768);
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('login-form')), findsOneWidget);
    expect(find.byKey(const Key('login-brand-panel')), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'home screen switches bottom navigation to rail for tablet landscape',
    (tester) async {
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.reset);
      final session = AuthSession(
        token: 'token',
        expiresAt: DateTime.now().toUtc().add(const Duration(hours: 12)),
        deviceId: 'device',
      );

      tester.view.physicalSize = const Size(360, 800);
      await tester.pumpWidget(
        ProviderScope(
          child: MaterialApp(home: HomeScreen(session: session)),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('home-bottom-navigation')), findsOneWidget);
      expect(find.byKey(const Key('home-navigation-rail')), findsNothing);
      expect(tester.takeException(), isNull);

      tester.view.physicalSize = const Size(1280, 800);
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('home-navigation-rail')), findsOneWidget);
      expect(find.byKey(const Key('home-bottom-navigation')), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );
}
