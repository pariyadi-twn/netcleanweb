import 'package:flutter/foundation.dart';

@immutable
class CashSessionSummary {
  const CashSessionSummary({
    required this.id,
    required this.cashRegisterId,
    required this.status,
    required this.openingRupiah,
    required this.ledgerRupiah,
    required this.expectedRupiah,
    this.countedClosingRupiah,
    this.differenceRupiah,
    this.openedAt,
    this.discrepancyReason,
  });

  factory CashSessionSummary.fromJson(Map<String, dynamic> json) {
    return CashSessionSummary(
      id: '${json['id']}',
      cashRegisterId: '${json['cash_register_id']}',
      status: '${json['status']}',
      openingRupiah: _asInt(json['opening_rupiah']),
      ledgerRupiah: _asInt(json['ledger_rupiah']),
      expectedRupiah: _asInt(json['expected_rupiah']),
      countedClosingRupiah: json['counted_closing_rupiah'] == null
          ? null
          : _asInt(json['counted_closing_rupiah']),
      differenceRupiah: json['difference_rupiah'] == null
          ? null
          : _asInt(json['difference_rupiah']),
      discrepancyReason: json['discrepancy_reason'] as String?,
      openedAt: json['opened_at'] == null
          ? null
          : DateTime.parse('${json['opened_at']}').toLocal(),
    );
  }

  final String id;
  final String cashRegisterId;
  final String status;
  final int openingRupiah;
  final int ledgerRupiah;
  final int expectedRupiah;
  final int? countedClosingRupiah;
  final int? differenceRupiah;
  final DateTime? openedAt;
  final String? discrepancyReason;

  bool get isOpen => status == 'open';
}

@immutable
class CashRegisterSummary {
  const CashRegisterSummary({
    required this.id,
    required this.code,
    required this.isActive,
    this.currentSession,
  });

  factory CashRegisterSummary.fromJson(Map<String, dynamic> json) {
    final session = json['current_session'];
    return CashRegisterSummary(
      id: '${json['id']}',
      code: '${json['code']}',
      isActive: json['is_active'] as bool? ?? true,
      currentSession: session is Map<String, dynamic>
          ? CashSessionSummary.fromJson(session)
          : null,
    );
  }

  final String id;
  final String code;
  final bool isActive;
  final CashSessionSummary? currentSession;
}

int _asInt(dynamic value) {
  if (value is num) {
    return value.toInt();
  }
  return int.tryParse('${value ?? 0}') ?? 0;
}
