import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:netclean_staff/core/auth/secret_store.dart';
import 'package:netclean_staff/core/network/api_client.dart';
import 'package:netclean_staff/core/providers.dart';
import 'package:netclean_staff/features/auth/presentation/login_screen.dart';

void main() {
  const credentialsMessage = 'Email atau password tidak cocok.';
  const contextMessage =
      'Data organisasi belum dapat dimuat. Coba masuk kembali.';

  for (final rejectContext in [false, true]) {
    testWidgets('login explains the failed stage (context: $rejectContext)', (
      tester,
    ) async {
      tester.view.devicePixelRatio = 1;
      tester.view.physicalSize = const Size(1024, 768);
      addTearDown(tester.view.reset);
      final client = MockClient((request) async {
        if (request.url.path.endsWith('/auth/tokens') && rejectContext) {
          return http.Response(
            jsonEncode({
              'data': {
                'token': 'new-token',
                'expires_at': DateTime.now()
                    .toUtc()
                    .add(const Duration(hours: 1))
                    .toIso8601String(),
                'device_id': 'device',
              },
            }),
            201,
          );
        }

        expect(
          request.url.path,
          rejectContext ? '/api/v1/me/context' : '/api/v1/auth/tokens',
        );
        return http.Response(
          jsonEncode({
            'error': {'code': 'UNAUTHENTICATED', 'message': 'Unauthenticated.'},
          }),
          401,
          headers: {'x-correlation-id': 'login-ref'},
        );
      });
      final container = ProviderContainer(
        overrides: [
          secretStoreProvider.overrideWithValue(MemorySecretStore()),
          apiClientProvider.overrideWith((ref) {
            final tokens = ref.watch(tokenStoreProvider);
            return ApiClient(
              client: client,
              baseUrl: 'https://example.test/api/v1',
              tokenProvider: () async => (await tokens.readSession())?.token,
            );
          }),
        ],
      );
      addTearDown(container.dispose);

      await tester.pumpWidget(
        UncontrolledProviderScope(
          container: container,
          child: const MaterialApp(home: LoginScreen()),
        ),
      );
      await tester.pumpAndSettle();
      await tester.enterText(
        find.byType(TextField).first,
        'staff@example.test',
      );
      await tester.enterText(find.byType(TextField).last, 'test-password');
      await tester.tap(find.text('Masuk'));
      await tester.pumpAndSettle();

      final expected = rejectContext ? contextMessage : credentialsMessage;
      expect(find.text('$expected\nRef: login-ref'), findsOneWidget);
      expect(await container.read(tokenStoreProvider).readSession(), isNull);
    });
  }
}
