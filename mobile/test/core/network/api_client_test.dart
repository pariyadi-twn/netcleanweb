import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:netclean_staff/core/network/api_client.dart';
import 'package:netclean_staff/core/network/api_exception.dart';

void main() {
  test(
    'api client adds auth, tenant, idempotency, parses body and emits correlation id',
    () async {
      late http.Request captured;
      final client = MockClient((request) async {
        captured = request;
        return http.Response(
          jsonEncode({
            'data': {'ok': true},
            'meta': {'correlation_id': 'server-correlation'},
          }),
          200,
          headers: {'content-type': 'application/json'},
        );
      });
      final api = ApiClient(
        client: client,
        baseUrl: 'https://example.test/api/v1',
        tokenProvider: () async => 'token-123',
      );

      final response = await api.post(
        '/orders',
        body: {'client_command_id': 'command-1'},
        tenantId: 'tenant-1',
        idempotencyKey: 'command-1',
      );

      expect(response['data'], {'ok': true});
      expect(captured.headers['Authorization'], 'Bearer token-123');
      expect(captured.headers['X-Tenant-ID'], 'tenant-1');
      expect(captured.headers['Idempotency-Key'], 'command-1');
      expect(captured.headers['X-Correlation-ID'], isNotEmpty);
      expect(captured.body, contains('command-1'));
    },
  );

  test('api client maps error envelope to typed exception', () async {
    final client = MockClient((request) async {
      return http.Response(
        jsonEncode({
          'error': {
            'code': 'VALIDATION_FAILED',
            'message': 'Invalid.',
            'field_errors': {
              'email': ['Required'],
            },
          },
          'meta': {'correlation_id': 'corr-1'},
        }),
        422,
      );
    });
    final api = ApiClient(
      client: client,
      baseUrl: 'https://example.test/api/v1',
    );

    await expectLater(
      api.post('/auth/tokens', body: {}, authenticated: false),
      throwsA(
        isA<ApiException>()
            .having((error) => error.code, 'code', 'VALIDATION_FAILED')
            .having((error) => error.fieldErrors['email'], 'field errors', [
              'Required',
            ]),
      ),
    );
  });

  test('api client pagination exposes next cursor', () async {
    final client = MockClient((request) async {
      expect(request.url.queryParameters['per_page'], '2');
      return http.Response(
        jsonEncode({
          'data': [
            {'id': '1'},
            {'id': '2'},
          ],
          'meta': {'next_cursor': 'next-page'},
        }),
        200,
      );
    });
    final api = ApiClient(
      client: client,
      baseUrl: 'https://example.test/api/v1',
    );

    final page = await api.getPage('/customers', perPage: 2);

    expect(page.items, hasLength(2));
    expect(page.nextCursor, 'next-page');
  });
}
