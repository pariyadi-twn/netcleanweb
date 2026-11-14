import 'package:flutter/foundation.dart';

@immutable
class BranchInfo {
  const BranchInfo({
    required this.id,
    required this.name,
    required this.timezone,
    this.address,
  });

  factory BranchInfo.fromJson(Map<String, dynamic> json) {
    return BranchInfo(
      id: '${json['id']}',
      name: '${json['name']}',
      timezone: '${json['timezone'] ?? 'Asia/Jakarta'}',
      address: json['address'] as String?,
    );
  }

  final String id;
  final String name;
  final String timezone;
  final String? address;
}

@immutable
class ServiceSummary {
  const ServiceSummary({
    required this.id,
    required this.code,
    required this.name,
    required this.unit,
    required this.minimumQuantity,
    required this.quantityStep,
  });

  factory ServiceSummary.fromJson(Map<String, dynamic> json) {
    return ServiceSummary(
      id: '${json['id']}',
      code: '${json['code']}',
      name: '${json['name']}',
      unit: '${json['unit']}',
      minimumQuantity: '${json['minimum_quantity']}',
      quantityStep: '${json['quantity_step']}',
    );
  }

  final String id;
  final String code;
  final String name;
  final String unit;
  final String minimumQuantity;
  final String quantityStep;
}

@immutable
class OrderLineDraft {
  const OrderLineDraft({required this.service, required this.quantity});

  final ServiceSummary service;
  final String quantity;

  Map<String, dynamic> toRequestJson() {
    return {
      'service_id': service.id,
      'measured_quantity': quantity.replaceAll(',', '.'),
    };
  }
}

@immutable
class QuoteLineSummary {
  const QuoteLineSummary({
    required this.serviceName,
    required this.unit,
    required this.measuredQuantity,
    required this.billableQuantity,
    required this.lineTotalRupiah,
  });

  factory QuoteLineSummary.fromJson(Map<String, dynamic> json) {
    return QuoteLineSummary(
      serviceName: '${json['service_name']}',
      unit: '${json['unit']}',
      measuredQuantity: '${json['measured_quantity']}',
      billableQuantity: '${json['billable_quantity']}',
      lineTotalRupiah: _asInt(json['line_total_rupiah']),
    );
  }

  final String serviceName;
  final String unit;
  final String measuredQuantity;
  final String billableQuantity;
  final int lineTotalRupiah;
}

@immutable
class QuoteSummary {
  const QuoteSummary({
    required this.id,
    required this.totalRupiah,
    required this.dueAt,
    required this.expiresAt,
    required this.lines,
  });

  factory QuoteSummary.fromJson(Map<String, dynamic> json) {
    final lines = json['lines'];
    return QuoteSummary(
      id: '${json['id']}',
      totalRupiah: _asInt(json['total_rupiah']),
      dueAt: DateTime.parse('${json['due_at']}').toLocal(),
      expiresAt: DateTime.parse('${json['expires_at']}').toLocal(),
      lines: lines is List
          ? lines
                .whereType<Map<String, dynamic>>()
                .map(QuoteLineSummary.fromJson)
                .toList(growable: false)
          : const [],
    );
  }

  final String id;
  final int totalRupiah;
  final DateTime dueAt;
  final DateTime expiresAt;
  final List<QuoteLineSummary> lines;
}

@immutable
class OrderSummary {
  const OrderSummary({
    required this.orderId,
    required this.orderNumber,
    required this.invoiceId,
    required this.invoiceNumber,
    required this.totalRupiah,
    required this.paidRupiah,
    required this.balanceDueRupiah,
    required this.dueAt,
  });

  factory OrderSummary.fromJson(Map<String, dynamic> json) {
    return OrderSummary(
      orderId: '${json['order_id']}',
      orderNumber: '${json['order_number']}',
      invoiceId: '${json['invoice_id']}',
      invoiceNumber: '${json['invoice_number']}',
      totalRupiah: _asInt(json['total_rupiah']),
      paidRupiah: _asInt(json['paid_rupiah']),
      balanceDueRupiah: _asInt(json['balance_due_rupiah']),
      dueAt: DateTime.parse('${json['due_at_current']}').toLocal(),
    );
  }

  final String orderId;
  final String orderNumber;
  final String invoiceId;
  final String invoiceNumber;
  final int totalRupiah;
  final int paidRupiah;
  final int balanceDueRupiah;
  final DateTime dueAt;

  OrderSummary copyWith({int? paidRupiah, int? balanceDueRupiah}) {
    return OrderSummary(
      orderId: orderId,
      orderNumber: orderNumber,
      invoiceId: invoiceId,
      invoiceNumber: invoiceNumber,
      totalRupiah: totalRupiah,
      paidRupiah: paidRupiah ?? this.paidRupiah,
      balanceDueRupiah: balanceDueRupiah ?? this.balanceDueRupiah,
      dueAt: dueAt,
    );
  }
}

@immutable
class PaymentSummary {
  const PaymentSummary({
    required this.paymentId,
    required this.invoiceStatus,
    required this.totalRupiah,
    required this.paidRupiah,
    required this.balanceDueRupiah,
    required this.changeRupiah,
  });

  factory PaymentSummary.fromJson(Map<String, dynamic> json) {
    return PaymentSummary(
      paymentId: '${json['payment_id']}',
      invoiceStatus: '${json['invoice_status']}',
      totalRupiah: _asInt(json['total_rupiah']),
      paidRupiah: _asInt(json['paid_rupiah']),
      balanceDueRupiah: _asInt(json['balance_due_rupiah']),
      changeRupiah: _asInt(json['change_rupiah']),
    );
  }

  final String paymentId;
  final String invoiceStatus;
  final int totalRupiah;
  final int paidRupiah;
  final int balanceDueRupiah;
  final int changeRupiah;
}

@immutable
class ReceiptLineSummary {
  const ReceiptLineSummary({
    required this.serviceName,
    required this.unit,
    required this.billableQuantity,
    required this.lineTotalRupiah,
  });

  factory ReceiptLineSummary.fromJson(Map<String, dynamic> json) {
    return ReceiptLineSummary(
      serviceName: '${json['service_name'] ?? 'Layanan'}',
      unit: '${json['unit']}',
      billableQuantity: '${json['billable_quantity']}',
      lineTotalRupiah: _asInt(json['line_total_rupiah']),
    );
  }

  final String serviceName;
  final String unit;
  final String billableQuantity;
  final int lineTotalRupiah;
}

@immutable
class ReceiptSummary {
  const ReceiptSummary({
    required this.orderNumber,
    required this.invoiceNumber,
    required this.customerName,
    required this.branchName,
    required this.dueAt,
    required this.totalRupiah,
    required this.paidRupiah,
    required this.balanceDueRupiah,
    required this.lines,
  });

  factory ReceiptSummary.fromJson(Map<String, dynamic> json) {
    final totals = json['totals'] is Map<String, dynamic>
        ? json['totals'] as Map<String, dynamic>
        : <String, dynamic>{};
    final customer = json['customer'] is Map<String, dynamic>
        ? json['customer'] as Map<String, dynamic>
        : <String, dynamic>{};
    final branch = json['branch'] is Map<String, dynamic>
        ? json['branch'] as Map<String, dynamic>
        : <String, dynamic>{};
    final lines = json['lines'];

    return ReceiptSummary(
      orderNumber: '${json['order_number']}',
      invoiceNumber: '${json['invoice_number'] ?? '-'}',
      customerName: '${customer['name'] ?? 'Walk-in'}',
      branchName: '${branch['name'] ?? '-'}',
      dueAt: DateTime.parse('${json['due_at']}').toLocal(),
      totalRupiah: _asInt(totals['total_rupiah']),
      paidRupiah: _asInt(totals['paid_rupiah']),
      balanceDueRupiah: _asInt(totals['balance_due_rupiah']),
      lines: lines is List
          ? lines
                .whereType<Map<String, dynamic>>()
                .map(ReceiptLineSummary.fromJson)
                .toList(growable: false)
          : const [],
    );
  }

  final String orderNumber;
  final String invoiceNumber;
  final String customerName;
  final String branchName;
  final DateTime dueAt;
  final int totalRupiah;
  final int paidRupiah;
  final int balanceDueRupiah;
  final List<ReceiptLineSummary> lines;
}

int _asInt(dynamic value) {
  if (value is num) {
    return value.toInt();
  }
  return int.tryParse('${value ?? 0}') ?? 0;
}
