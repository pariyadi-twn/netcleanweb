import 'package:flutter/foundation.dart';

@immutable
class AuthSession {
  const AuthSession({
    required this.token,
    required this.expiresAt,
    required this.deviceId,
    this.tenantId,
    this.membershipId,
    this.branchIds = const [],
    this.locationIds = const [],
  });

  final String token;
  final DateTime expiresAt;
  final String deviceId;
  final String? tenantId;
  final String? membershipId;
  final List<String> branchIds;
  final List<String> locationIds;

  bool get isExpired => DateTime.now().toUtc().isAfter(expiresAt.toUtc());
}
