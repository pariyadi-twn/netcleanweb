import 'package:flutter/foundation.dart';

@immutable
class OrderBagSummary {
  const OrderBagSummary({
    required this.id,
    required this.custodyStatus,
    required this.version,
    this.currentLocationId,
  });

  factory OrderBagSummary.fromJson(Map<String, dynamic> json) {
    return OrderBagSummary(
      id: '${json['id']}',
      custodyStatus: '${json['custody_status']}',
      currentLocationId: json['current_location_id'] as String?,
      version: _asInt(json['version']),
    );
  }

  final String id;
  final String custodyStatus;
  final String? currentLocationId;
  final int version;
}

@immutable
class OrderLookup {
  const OrderLookup({
    required this.orderId,
    required this.orderNumber,
    required this.status,
    required this.totalRupiah,
    required this.paidRupiah,
    required this.balanceDueRupiah,
    required this.dueAt,
    required this.bags,
  });

  factory OrderLookup.fromJson(Map<String, dynamic> json) {
    final bags = json['bags'];
    return OrderLookup(
      orderId: '${json['order_id']}',
      orderNumber: '${json['order_number']}',
      status: '${json['operational_status']}',
      totalRupiah: _asInt(json['total_rupiah']),
      paidRupiah: _asInt(json['paid_rupiah']),
      balanceDueRupiah: _asInt(json['balance_due_rupiah']),
      dueAt: DateTime.parse('${json['due_at_current']}').toLocal(),
      bags: bags is List
          ? bags
                .whereType<Map<String, dynamic>>()
                .map(OrderBagSummary.fromJson)
                .toList(growable: false)
          : const [],
    );
  }

  final String orderId;
  final String orderNumber;
  final String status;
  final int totalRupiah;
  final int paidRupiah;
  final int balanceDueRupiah;
  final DateTime dueAt;
  final List<OrderBagSummary> bags;
}

@immutable
class ProductionTaskSummary {
  const ProductionTaskSummary({
    required this.taskId,
    required this.orderId,
    required this.orderNumber,
    required this.bagId,
    required this.serviceName,
    required this.stepCode,
    required this.status,
    required this.dueAt,
    required this.overdue,
    required this.version,
    required this.requiresQc,
    required this.isRequired,
  });

  factory ProductionTaskSummary.fromJson(Map<String, dynamic> json) {
    return ProductionTaskSummary(
      taskId: '${json['task_id']}',
      orderId: '${json['order_id']}',
      orderNumber: '${json['order_number']}',
      bagId: '${json['bag_id']}',
      serviceName: '${json['service_name'] ?? 'Layanan'}',
      stepCode: '${json['step_code']}',
      status: '${json['status']}',
      dueAt: DateTime.parse('${json['due_at']}').toLocal(),
      overdue: json['overdue'] as bool? ?? false,
      version: _asInt(json['version']),
      requiresQc: json['requires_qc'] as bool? ?? false,
      isRequired: json['is_required'] as bool? ?? true,
    );
  }

  final String taskId;
  final String orderId;
  final String orderNumber;
  final String bagId;
  final String serviceName;
  final String stepCode;
  final String status;
  final DateTime dueAt;
  final bool overdue;
  final int version;
  final bool requiresQc;
  final bool isRequired;
}

@immutable
class ReadinessContent {
  const ReadinessContent({
    required this.bagContentId,
    required this.ready,
    required this.qcPassed,
    required this.qcRequired,
    required this.openRequiredTasks,
  });

  factory ReadinessContent.fromJson(Map<String, dynamic> json) {
    return ReadinessContent(
      bagContentId: '${json['bag_content_id']}',
      ready: json['ready'] as bool? ?? false,
      qcPassed: json['qc_passed'] as bool? ?? false,
      qcRequired: json['qc_required'] as bool? ?? false,
      openRequiredTasks: _asInt(json['open_required_tasks']),
    );
  }

  final String bagContentId;
  final bool ready;
  final bool qcPassed;
  final bool qcRequired;
  final int openRequiredTasks;
}

@immutable
class OrderReadiness {
  const OrderReadiness({
    required this.orderId,
    required this.orderNumber,
    required this.status,
    required this.canReady,
    required this.partiallyReady,
    required this.contents,
  });

  factory OrderReadiness.fromJson(Map<String, dynamic> json) {
    final contents = json['contents'];
    return OrderReadiness(
      orderId: '${json['order_id']}',
      orderNumber: '${json['order_number']}',
      status: '${json['operational_status']}',
      canReady: json['can_ready'] as bool? ?? false,
      partiallyReady: json['partially_ready'] as bool? ?? false,
      contents: contents is List
          ? contents
                .whereType<Map<String, dynamic>>()
                .map(ReadinessContent.fromJson)
                .toList(growable: false)
          : const [],
    );
  }

  final String orderId;
  final String orderNumber;
  final String status;
  final bool canReady;
  final bool partiallyReady;
  final List<ReadinessContent> contents;
}

@immutable
class HandoverSummary {
  const HandoverSummary({required this.handoverId, required this.orderStatus});

  factory HandoverSummary.fromJson(Map<String, dynamic> json) {
    final handover = json['handover'] is Map<String, dynamic>
        ? json['handover'] as Map<String, dynamic>
        : <String, dynamic>{};
    final order = json['order'] is Map<String, dynamic>
        ? json['order'] as Map<String, dynamic>
        : <String, dynamic>{};
    return HandoverSummary(
      handoverId: '${handover['handover_id']}',
      orderStatus: '${order['operational_status']}',
    );
  }

  final String handoverId;
  final String orderStatus;
}

int _asInt(dynamic value) {
  if (value is num) {
    return value.toInt();
  }
  return int.tryParse('${value ?? 0}') ?? 0;
}
