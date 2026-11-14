import 'package:flutter/foundation.dart';

@immutable
class CustomerSummary {
  const CustomerSummary({
    required this.id,
    required this.name,
    this.phone,
    this.isActive = true,
  });

  factory CustomerSummary.fromJson(Map<String, dynamic> json) {
    return CustomerSummary(
      id: '${json['id']}',
      name: '${json['name']}',
      phone: json['normalized_phone'] as String?,
      isActive: json['is_active'] as bool? ?? true,
    );
  }

  final String id;
  final String name;
  final String? phone;
  final bool isActive;
}
