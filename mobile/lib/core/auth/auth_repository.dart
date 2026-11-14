import '../network/api_client.dart';
import 'auth_session.dart';
import 'token_store.dart';

class AuthRepository {
  AuthRepository(this._api, this._tokens);

  final ApiClient _api;
  final TokenStore _tokens;

  Future<AuthSession?> restore() async {
    final session = await _tokens.readSession();
    if (session == null ||
        session.tenantId == null ||
        session.branchIds.isNotEmpty) {
      return session;
    }

    try {
      final context = await _api.get('/me/context');
      final contexts = context['data'];
      if (contexts is! List) {
        return session;
      }
      for (final item in contexts.whereType<Map<String, dynamic>>()) {
        if (item['tenant_id'] != session.tenantId) {
          continue;
        }
        final updated = AuthSession(
          token: session.token,
          expiresAt: session.expiresAt,
          deviceId: session.deviceId,
          tenantId: session.tenantId,
          membershipId: item['membership_id'] as String?,
          branchIds: _stringList(item['branch_ids']),
          locationIds: _stringList(item['location_ids']),
        );
        await _tokens.saveSession(updated);
        return updated;
      }
    } catch (_) {
      return session;
    }

    return session;
  }

  Future<AuthSession> login({
    required String email,
    required String password,
  }) async {
    final installationId = await _tokens.installationId();
    final payload = await _api.post(
      '/auth/tokens',
      authenticated: false,
      body: {
        'email': email,
        'password': password,
        'device_name': 'NetClean Staff Android',
        'installation_id': installationId,
      },
    );
    final data = payload['data'];
    if (data is! Map<String, dynamic> || data['token'] is! String) {
      throw StateError('Token response is missing data.');
    }
    var session = AuthSession(
      token: data['token'] as String,
      expiresAt: DateTime.parse(data['expires_at'] as String).toUtc(),
      deviceId: '${data['device_id'] ?? ''}',
    );

    final context = await _api.get('/me/context');
    final contexts = context['data'];
    if (contexts is List &&
        contexts.isNotEmpty &&
        contexts.first is Map<String, dynamic>) {
      final selected = contexts.first as Map<String, dynamic>;
      session = AuthSession(
        token: session.token,
        expiresAt: session.expiresAt,
        deviceId: session.deviceId,
        tenantId: selected['tenant_id'] as String?,
        membershipId: selected['membership_id'] as String?,
        branchIds: _stringList(selected['branch_ids']),
        locationIds: _stringList(selected['location_ids']),
      );
    }

    await _tokens.saveSession(session);
    return session;
  }

  Future<void> logout() => _tokens.clear();

  List<String> _stringList(dynamic value) {
    if (value is! List) {
      return const [];
    }
    return value.whereType<String>().toList(growable: false);
  }
}
