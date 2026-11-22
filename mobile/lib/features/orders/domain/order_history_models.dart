import 'package:flutter/foundation.dart';

@immutable
class OrderHistoryLine {
  const OrderHistoryLine({
    required this.serviceName,
    required this.unit,
    required this.measuredQuantity,
    required this.billableQuantity,
    required this.lineTotalRupiah,
  });

  factory OrderHistoryLine.fromJson(Map<String, dynamic> json) {
    return OrderHistoryLine(
      serviceName: '${json['service_name'] ?? 'Layanan'}',
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
class OrderHistoryBag {
  const OrderHistoryBag({
    required this.id,
    required this.custodyStatus,
    this.labelCode,
    this.currentLocationId,
  });

  factory OrderHistoryBag.fromJson(Map<String, dynamic> json) {
    return OrderHistoryBag(
      id: '${json['id']}',
      labelCode: json['label_code'] as String?,
      custodyStatus: '${json['custody_status']}',
      currentLocationId: json['current_location_id'] as String?,
    );
  }

  final String id;
  final String? labelCode;
  final String custodyStatus;
  final String? currentLocationId;
}

@immutable
class OrderHistory {
  const OrderHistory({
    required this.orderId,
    required this.orderNumber,
    required this.status,
    required this.invoiceStatus,
    required this.invoiceNumber,
    required this.totalRupiah,
    required this.paidRupiah,
    required this.balanceDueRupiah,
    required this.dueAt,
    required this.lines,
    required this.bags,
    this.invoiceId = '',
  });

  factory OrderHistory.fromJson(Map<String, dynamic> json) {
    final lines = json['lines'];
    final bags = json['bags'];
    return OrderHistory(
      orderId: '${json['order_id']}',
      orderNumber: '${json['order_number']}',
      invoiceId: '${json['invoice_id'] ?? ''}',
      status: '${json['operational_status']}',
      invoiceStatus: '${json['invoice_status'] ?? '-'}',
      invoiceNumber: '${json['invoice_number'] ?? '-'}',
      totalRupiah: _asInt(json['total_rupiah']),
      paidRupiah: _asInt(json['paid_rupiah']),
      balanceDueRupiah: _asInt(json['balance_due_rupiah']),
      dueAt: DateTime.parse('${json['due_at_current']}').toLocal(),
      lines: lines is List
          ? lines
                .whereType<Map<String, dynamic>>()
                .map(OrderHistoryLine.fromJson)
                .toList(growable: false)
          : const [],
      bags: bags is List
          ? bags
                .whereType<Map<String, dynamic>>()
                .map(OrderHistoryBag.fromJson)
                .toList(growable: false)
          : const [],
    );
  }

  final String orderId;
  final String orderNumber;
  final String status;
  final String invoiceStatus;
  final String invoiceNumber;
  final int totalRupiah;
  final int paidRupiah;
  final int balanceDueRupiah;
  final DateTime dueAt;
  final List<OrderHistoryLine> lines;
  final List<OrderHistoryBag> bags;
  final String invoiceId;

  OrderHistory copyWith({
    String? invoiceStatus,
    int? paidRupiah,
    int? balanceDueRupiah,
  }) {
    return OrderHistory(
      orderId: orderId,
      orderNumber: orderNumber,
      status: status,
      invoiceStatus: invoiceStatus ?? this.invoiceStatus,
      invoiceNumber: invoiceNumber,
      totalRupiah: totalRupiah,
      paidRupiah: paidRupiah ?? this.paidRupiah,
      balanceDueRupiah: balanceDueRupiah ?? this.balanceDueRupiah,
      dueAt: dueAt,
      lines: lines,
      bags: bags,
      invoiceId: invoiceId,
    );
  }
}

@immutable
class OrderHistoryPage {
  const OrderHistoryPage({required this.items, this.nextCursor});

  final List<OrderHistory> items;
  final String? nextCursor;
}

int _asInt(dynamic value) {
  if (value is num) {
    return value.toInt();
  }
  return int.tryParse('${value ?? 0}') ?? 0;
}
