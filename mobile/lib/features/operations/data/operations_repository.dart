import '../../../core/network/api_client.dart';
import '../domain/operations_models.dart';

class OperationsRepository {
  OperationsRepository(this._api);

  final ApiClient _api;

  Future<OrderLookup?> lookupOrder({
    required String tenantId,
    String? orderNumber,
  }) async {
    final payload = await _api.get(
      '/orders',
      query: {
        'per_page': '1',
        if (orderNumber != null && orderNumber.trim().isNotEmpty)
          'order_number': orderNumber.trim(),
      },
      tenantId: tenantId,
    );
    final data = payload['data'];
    if (data is! List || data.isEmpty) {
      return null;
    }
    final first = data.first;
    return first is Map<String, dynamic> ? OrderLookup.fromJson(first) : null;
  }

  Future<List<ProductionTaskSummary>> tasks({
    required String tenantId,
    required String locationId,
  }) async {
    final page = await _api.getPage(
      '/production/tasks',
      query: {'location_id': locationId},
      perPage: 100,
      tenantId: tenantId,
    );
    return page.items
        .map(ProductionTaskSummary.fromJson)
        .toList(growable: false);
  }

  Future<void> receiveBag({
    required String tenantId,
    required String orderId,
    required String bagId,
    required String locationId,
    required int expectedVersion,
  }) async {
    await _api.post(
      '/orders/$orderId/bags/$bagId/receive',
      tenantId: tenantId,
      body: {'location_id': locationId, 'expected_version': expectedVersion},
    );
  }

  Future<void> planTasks({
    required String tenantId,
    required String orderId,
    required String locationId,
  }) async {
    await _api.post(
      '/orders/$orderId/production-tasks',
      tenantId: tenantId,
      body: {'location_id': locationId},
    );
  }

  Future<ProductionTaskSummary> transition({
    required String tenantId,
    required ProductionTaskSummary task,
    required String toStatus,
    String? reason,
  }) async {
    final payload = await _api.post(
      '/production/tasks/${task.taskId}/transitions',
      tenantId: tenantId,
      body: {
        'expected_version': task.version,
        'to_status': toStatus,
        if (reason != null && reason.trim().isNotEmpty) 'reason': reason.trim(),
      },
    );
    return ProductionTaskSummary.fromJson(_data(payload));
  }

  Future<void> recordQc({
    required String tenantId,
    required ProductionTaskSummary task,
    required String outcome,
    String? notes,
  }) async {
    await _api.post(
      '/production/tasks/${task.taskId}/qc-results',
      tenantId: tenantId,
      body: {
        'outcome': outcome,
        'checklist': {
          'visual': outcome == 'passed' ? 'ok' : 'failed',
          'quantity': outcome == 'passed' ? 'ok' : 'failed',
        },
        if (notes != null && notes.trim().isNotEmpty) 'notes': notes.trim(),
      },
    );
  }

  Future<OrderReadiness> readiness({
    required String tenantId,
    required String orderId,
  }) async {
    final payload = await _api.get(
      '/orders/$orderId/readiness',
      tenantId: tenantId,
    );
    return OrderReadiness.fromJson(_data(payload));
  }

  Future<HandoverSummary> handover({
    required String tenantId,
    required String orderId,
    required List<String> bagIds,
    required String locationId,
    required String recipientName,
    String verificationMethod = 'receipt',
  }) async {
    final payload = await _api.post(
      '/orders/$orderId/handovers',
      tenantId: tenantId,
      body: {
        'bag_ids': bagIds,
        'location_id': locationId,
        'recipient_name': recipientName,
        'verification_method': verificationMethod,
      },
    );
    return HandoverSummary.fromJson(_data(payload));
  }

  Map<String, dynamic> _data(Map<String, dynamic> payload) {
    final data = payload['data'];
    if (data is! Map<String, dynamic>) {
      throw StateError('The API response is missing data.');
    }
    return data;
  }
}
