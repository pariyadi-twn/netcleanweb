import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:netclean_staff/core/auth/auth_repository.dart';
import 'package:netclean_staff/core/auth/secret_store.dart';
import 'package:netclean_staff/core/auth/token_store.dart';
import 'package:netclean_staff/core/network/api_client.dart';

void main() {
  test('login stores token, context and stable installation id', () async {
    final tokenStore = TokenStore(MemorySecretStore());
    final client = MockClient((request) async {
      if (request.url.path.endsWith('/me/context')) {
        return http.Response(
          jsonEncode({
            'data': [
              {
                'tenant_id': 'tenant-public-id',
                'membership_id': 'membership-public-id',
                'permission_version': 1,
                'branch_ids': <String>[],
                'location_ids': <String>[],
              },
            ],
          }),
          200,
        );
      }
      final body = jsonDecode(request.body) as Map<String, dynamic>;
      expect(request.url.path, '/api/v1/auth/tokens');
      expect(body['installation_id'], isNotEmpty);
      return http.Response(
        jsonEncode({
          'data': {
            'token': 'plain-token',
            'token_type': 'Bearer',
            'expires_at': DateTime.now()
                .toUtc()
                .add(const Duration(hours: 12))
                .toIso8601String(),
            'device_id': 'device-public-id',
          },
        }),
        201,
      );
    });
    final repository = AuthRepository(
      ApiClient(client: client, baseUrl: 'https://example.test/api/v1'),
      tokenStore,
    );

    final session = await repository.login(
      email: 'staff@example.test',
      password: 'secret',
    );

    expect(session.token, 'plain-token');
    expect(session.deviceId, 'device-public-id');
    expect(session.tenantId, 'tenant-public-id');
    expect(session.membershipId, 'membership-public-id');
    expect((await tokenStore.readSession())?.token, 'plain-token');
    expect(await tokenStore.installationId(), isNotEmpty);
  });

  test('logout clears session but keeps installation identity', () async {
    final tokenStore = TokenStore(MemorySecretStore());
    final client = MockClient((request) async {
      if (request.url.path.endsWith('/me/context')) {
        return http.Response(
          jsonEncode({
            'data': [
              {
                'tenant_id': 'tenant',
                'membership_id': 'membership',
                'permission_version': 1,
                'branch_ids': <String>[],
                'location_ids': <String>[],
              },
            ],
          }),
          200,
        );
      }
      return http.Response(
        jsonEncode({
          'data': {
            'token': 'plain-token',
            'expires_at': DateTime.now()
                .toUtc()
                .add(const Duration(hours: 12))
                .toIso8601String(),
            'device_id': 'device',
          },
        }),
        201,
      );
    });
    final repository = AuthRepository(
      ApiClient(client: client, baseUrl: 'https://example.test/api/v1'),
      tokenStore,
    );
    await repository.login(email: 'staff@example.test', password: 'secret');
    final installation = await tokenStore.installationId();

    await repository.logout();

    expect(await repository.restore(), isNull);
    expect(await tokenStore.installationId(), installation);
  });
}
