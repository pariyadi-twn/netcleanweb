import 'package:flutter/foundation.dart';

@immutable
class ApiException implements Exception {
  const ApiException({
    required this.code,
    required this.message,
    required this.statusCode,
    this.fieldErrors = const {},
    this.correlationId,
    this.retryAfterSeconds,
  });

  final String code;
  final String message;
  final int statusCode;
  final Map<String, List<String>> fieldErrors;
  final String? correlationId;
  final int? retryAfterSeconds;

  @override
  String toString() => '$code ($statusCode): $message';
}
