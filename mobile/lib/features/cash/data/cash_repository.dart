import '../../../core/network/api_client.dart';
import '../domain/cash_models.dart';

class CashRepository {
  CashRepository(this._api);

  final ApiClient _api;

  Future<List<CashRegisterSummary>> registers({
    required String tenantId,
    required String branchId,
  }) async {
    final payload = await _api.get(
      '/branches/$branchId/cash-registers',
      tenantId: tenantId,
    );
    final data = payload['data'];
    if (data is! List) {
      return const [];
    }
    return data
        .whereType<Map<String, dynamic>>()
        .map(CashRegisterSummary.fromJson)
        .toList(growable: false);
  }

  Future<CashSessionSummary> openSession({
    required String tenantId,
    required String registerId,
    required int openingRupiah,
  }) async {
    final payload = await _api.post(
      '/cash-registers/$registerId/sessions',
      tenantId: tenantId,
      body: {'opening_rupiah': openingRupiah},
    );
    return CashSessionSummary.fromJson(_data(payload));
  }

  Future<CashSessionSummary> closeSession({
    required String tenantId,
    required String sessionId,
    required int countedClosingRupiah,
    String? reason,
  }) async {
    final payload = await _api.post(
      '/cash-sessions/$sessionId/close',
      tenantId: tenantId,
      body: {
        'counted_closing_rupiah': countedClosingRupiah,
        if (reason != null && reason.trim().isNotEmpty) 'reason': reason.trim(),
      },
    );
    return CashSessionSummary.fromJson(_data(payload));
  }

  Future<CashRegisterSummary> createRegister({
    required String tenantId,
    required String branchId,
    required String code,
  }) async {
    final payload = await _api.post(
      '/branches/$branchId/cash-registers',
      tenantId: tenantId,
      body: {'code': code},
    );
    return CashRegisterSummary.fromJson(_data(payload));
  }

  Map<String, dynamic> _data(Map<String, dynamic> payload) {
    final data = payload['data'];
    if (data is! Map<String, dynamic>) {
      throw StateError('The API response is missing data.');
    }
    return data;
  }
}
