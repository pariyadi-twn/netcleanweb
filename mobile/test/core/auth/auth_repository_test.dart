import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:netclean_staff/core/auth/auth_repository.dart';
import 'package:netclean_staff/core/auth/auth_session.dart';
import 'package:netclean_staff/core/auth/secret_store.dart';
import 'package:netclean_staff/core/auth/token_store.dart';
import 'package:netclean_staff/core/network/api_client.dart';

void main() {
  for (final previousToken in [null, 'previous-token']) {
    test(
      'login uses the new token for context (stored: $previousToken)',
      () async {
        final tokenStore = TokenStore(MemorySecretStore());
        if (previousToken != null) {
          await tokenStore.saveSession(
            AuthSession(
              token: previousToken,
              expiresAt: DateTime.now().toUtc().add(const Duration(hours: 1)),
              deviceId: 'device-public-id',
              tenantId: 'previous-tenant',
            ),
          );
        }
        final installationId = await tokenStore.installationId();
        final client = MockClient((request) async {
          if (request.url.path.endsWith('/me/context')) {
            expect(request.headers['Authorization'], 'Bearer plain-token');
            expect((await tokenStore.readSession())?.token, previousToken);
            return http.Response(
              jsonEncode({
                'data': [
                  {
                    'tenant_id': 'tenant-public-id',
                    'membership_id': 'membership-public-id',
                    'permission_version': 1,
                    'branch_ids': ['branch-public-id'],
                    'location_ids': ['location-public-id'],
                  },
                ],
              }),
              200,
            );
          }
          final body = jsonDecode(request.body) as Map<String, dynamic>;
          expect(request.url.path, '/api/v1/auth/tokens');
          expect(request.headers['Authorization'], isNull);
          expect(body['installation_id'], installationId);
          return _tokenResponse();
        });
        final repository = AuthRepository(
          ApiClient(
            client: client,
            baseUrl: 'https://example.test/api/v1',
            tokenProvider: () async => (await tokenStore.readSession())?.token,
          ),
          tokenStore,
        );

        final session = await repository.login(
          email: 'staff@example.test',
          password: 'secret',
        );

        final saved = await tokenStore.readSession();
        expect(session.tenantId, 'tenant-public-id');
        expect(saved?.token, 'plain-token');
        expect(saved?.deviceId, 'device-public-id');
        expect(saved?.tenantId, 'tenant-public-id');
        expect(saved?.membershipId, 'membership-public-id');
        expect(saved?.branchIds, ['branch-public-id']);
        expect(saved?.locationIds, ['location-public-id']);
      },
    );
  }

  test('restore refreshes missing branch context from the server', () async {
    final tokenStore = TokenStore(MemorySecretStore());
    await tokenStore.saveSession(
      AuthSession(
        token: 'stored-token',
        expiresAt: DateTime.now().toUtc().add(const Duration(hours: 1)),
        deviceId: 'device-public-id',
        tenantId: 'tenant-public-id',
        membershipId: 'membership-public-id',
      ),
    );
    final client = MockClient((request) async {
      expect(request.url.path, '/api/v1/me/context');
      expect(request.headers['Authorization'], 'Bearer stored-token');
      return http.Response(
        jsonEncode({
          'data': [
            {
              'tenant_id': 'tenant-public-id',
              'membership_id': 'membership-public-id',
              'permission_version': 2,
              'branch_ids': ['branch-public-id'],
              'location_ids': ['location-public-id'],
            },
          ],
        }),
        200,
      );
    });
    final repository = AuthRepository(
      ApiClient(
        client: client,
        baseUrl: 'https://example.test/api/v1',
        tokenProvider: () async => (await tokenStore.readSession())?.token,
      ),
      tokenStore,
    );

    final session = await repository.restore();

    expect(session?.branchIds, ['branch-public-id']);
    expect(session?.locationIds, ['location-public-id']);
    expect((await tokenStore.readSession())?.branchIds, ['branch-public-id']);
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

http.Response _tokenResponse() => http.Response(
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
