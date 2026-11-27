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
    this.permissions = const [],
    this.productionMode = 'detailed',
    this.requirePaymentBeforeHandover = false,
  });

  final String token;
  final DateTime expiresAt;
  final String deviceId;
  final String? tenantId;
  final String? membershipId;
  final List<String> branchIds;
  final List<String> locationIds;
  final List<String> permissions;
  final String productionMode;
  final bool requirePaymentBeforeHandover;

  bool get isExpired => DateTime.now().toUtc().isAfter(expiresAt.toUtc());

  bool get canManagePayments =>
      permissions.isEmpty || permissions.contains('payments.manage');

  bool get usesSimpleProduction => productionMode == 'simple';
}
