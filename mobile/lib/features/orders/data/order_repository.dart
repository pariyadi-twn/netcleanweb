import '../../../core/network/api_client.dart';
import '../domain/order_models.dart';

class OrderRepository {
  OrderRepository(this._api);

  final ApiClient _api;

  Future<BranchInfo> branch({
    required String tenantId,
    required String branchId,
  }) async {
    final payload = await _api.get('/branches/$branchId', tenantId: tenantId);
    return BranchInfo.fromJson(_data(payload));
  }

  Future<List<ServiceSummary>> services({required String tenantId}) async {
    final page = await _api.getPage(
      '/services',
      query: const {'active': '1'},
      perPage: 100,
      tenantId: tenantId,
    );
    return page.items.map(ServiceSummary.fromJson).toList(growable: false);
  }

  Future<QuoteSummary> createQuote({
    required String tenantId,
    required String branchId,
    required String? customerId,
    required List<OrderLineDraft> lines,
  }) async {
    final payload = await _api.post(
      '/orders/quotes',
      tenantId: tenantId,
      body: {
        'branch_id': branchId,
        'customer_id': ?customerId,
        'fulfillment_method': 'pickup',
        'lines': lines.map((line) => line.toRequestJson()).toList(),
      },
    );
    return QuoteSummary.fromJson(_data(payload));
  }

  Future<OrderSummary> acceptOrder({
    required String tenantId,
    required String branchId,
    required String quoteId,
    required String? customerId,
    required List<OrderLineDraft> lines,
    required String commandId,
  }) async {
    final payload = await _api.post(
      '/orders',
      tenantId: tenantId,
      idempotencyKey: commandId,
      body: {
        'client_command_id': commandId,
        'quote_id': quoteId,
        'branch_id': branchId,
        'customer_id': ?customerId,
        'fulfillment_method': 'pickup',
        'lines': lines.map((line) => line.toRequestJson()).toList(),
      },
    );
    return OrderSummary.fromJson(_data(payload));
  }

  Future<PaymentSummary> recordPayment({
    required String tenantId,
    required String invoiceId,
    required int amountRupiah,
    required String method,
    required String commandId,
    String? cashSessionId,
    int? cashReceivedRupiah,
  }) async {
    final payload = await _api.post(
      '/invoices/$invoiceId/payments',
      tenantId: tenantId,
      idempotencyKey: commandId,
      body: {
        'client_command_id': commandId,
        'amount_rupiah': amountRupiah,
        'method': method,
        'cash_session_id': ?cashSessionId,
        'cash_received_rupiah': ?cashReceivedRupiah,
      },
    );
    return PaymentSummary.fromJson(_data(payload));
  }

  Future<ReceiptSummary> receipt({
    required String tenantId,
    required String orderId,
  }) async {
    final payload = await _api.post(
      '/orders/$orderId/receipt',
      tenantId: tenantId,
    );
    return ReceiptSummary.fromJson(_data(payload));
  }

  Map<String, dynamic> _data(Map<String, dynamic> payload) {
    final data = payload['data'];
    if (data is! Map<String, dynamic>) {
      throw StateError('The API response is missing data.');
    }
    return data;
  }
}
