import 'package:uuid/uuid.dart';

import 'auth_session.dart';
import 'secret_store.dart';

class TokenStore {
  TokenStore(this._secrets);

  static const _tokenKey = 'auth_token_v1';
  static const _expiryKey = 'auth_expiry_v1';
  static const _deviceKey = 'auth_device_v1';
  static const _installationKey = 'installation_id_v1';
  static const _tenantKey = 'auth_tenant_v1';
  static const _membershipKey = 'auth_membership_v1';

  final SecretStore _secrets;

  Future<AuthSession?> readSession() async {
    final token = await _secrets.read(_tokenKey);
    final expiry = await _secrets.read(_expiryKey);
    if (token == null || token.isEmpty || expiry == null) {
      return null;
    }
    final expiresAt = DateTime.tryParse(expiry);
    if (expiresAt == null ||
        DateTime.now().toUtc().isAfter(expiresAt.toUtc())) {
      await clear();
      return null;
    }
    return AuthSession(
      token: token,
      expiresAt: expiresAt.toUtc(),
      deviceId: await _secrets.read(_deviceKey) ?? '',
      tenantId: await _secrets.read(_tenantKey),
      membershipId: await _secrets.read(_membershipKey),
    );
  }

  Future<void> saveSession(AuthSession session) async {
    await _secrets.write(_tokenKey, session.token);
    await _secrets.write(
      _expiryKey,
      session.expiresAt.toUtc().toIso8601String(),
    );
    await _secrets.write(_deviceKey, session.deviceId);
    if (session.tenantId != null) {
      await _secrets.write(_tenantKey, session.tenantId!);
    }
    if (session.membershipId != null) {
      await _secrets.write(_membershipKey, session.membershipId!);
    }
  }

  Future<String> installationId() async {
    final existing = await _secrets.read(_installationKey);
    if (existing != null && existing.isNotEmpty) {
      return existing;
    }
    final generated = const Uuid().v4();
    await _secrets.write(_installationKey, generated);
    return generated;
  }

  Future<void> clear() async {
    await _secrets.delete(_tokenKey);
    await _secrets.delete(_expiryKey);
    await _secrets.delete(_deviceKey);
    await _secrets.delete(_tenantKey);
    await _secrets.delete(_membershipKey);
  }
}
