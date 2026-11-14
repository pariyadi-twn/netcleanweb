import 'package:flutter/foundation.dart';

@immutable
class AuthSession {
  const AuthSession({
    required this.token,
    required this.expiresAt,
    required this.deviceId,
    this.tenantId,
    this.membershipId,
  });

  final String token;
  final DateTime expiresAt;
  final String deviceId;
  final String? tenantId;
  final String? membershipId;

  bool get isExpired => DateTime.now().toUtc().isAfter(expiresAt.toUtc());
}
