import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:netclean_staff/core/network/api_client.dart';
import 'package:netclean_staff/features/orders/data/order_repository.dart';
import 'package:netclean_staff/features/orders/domain/order_models.dart';

const _baseUrl = 'https://example.test/api/v1';

const _service = ServiceSummary(
  id: 'service-1',
  code: 'kiloan',
  name: 'Kiloan',
  unit: 'kg',
  minimumQuantity: '3',
  quantityStep: '0.1',
);

void main() {
  test('loads branch and active services within the tenant scope', () async {
    final client = MockClient((request) async {
      expect(request.headers['X-Tenant-ID'], 'tenant-1');
      if (request.url.path.endsWith('/branches/branch-1')) {
        return http.Response(
          jsonEncode({
            'data': {
              'id': 'branch-1',
              'name': 'Cabang UAT',
              'timezone': 'Asia/Jakarta',
              'address': 'Jl. Uji',
            },
          }),
          200,
        );
      }
      expect(request.url.path, '/api/v1/services');
      expect(request.url.queryParameters['active'], '1');
      expect(request.url.queryParameters['per_page'], '100');
      return http.Response(
        jsonEncode({
          'data': [
            {
              'id': 'service-1',
              'code': 'kiloan',
              'name': 'Kiloan',
              'unit': 'kg',
              'minimum_quantity': '3',
              'quantity_step': '0.1',
            },
          ],
          'meta': {'next_cursor': null},
        }),
        200,
      );
    });
    final repository = OrderRepository(
      ApiClient(client: client, baseUrl: _baseUrl),
    );

    final branch = await repository.branch(
      tenantId: 'tenant-1',
      branchId: 'branch-1',
    );
    final services = await repository.services(tenantId: 'tenant-1');

    expect(branch.name, 'Cabang UAT');
    expect(services.single.name, 'Kiloan');
    expect(services.single.unit, 'kg');
  });

  test('creates a quote from selected lines', () async {
    final client = MockClient((request) async {
      expect(request.method, 'POST');
      expect(request.url.path, '/api/v1/orders/quotes');
      expect(request.headers['X-Tenant-ID'], 'tenant-1');
      final body = jsonDecode(request.body) as Map<String, dynamic>;
      expect(body['branch_id'], 'branch-1');
      expect(body['fulfillment_method'], 'pickup');
      expect(body['lines'], [
        {'service_id': 'service-1', 'measured_quantity': '3.5'},
      ]);
      return http.Response(
        jsonEncode({
          'data': {
            'id': 'quote-1',
            'total_rupiah': 28000,
            'due_at': '2026-11-16T12:00:00Z',
            'expires_at': '2026-11-14T08:15:00Z',
            'lines': [
              {
                'service_name': 'Kiloan',
                'unit': 'kg',
                'measured_quantity': '3.5',
                'billable_quantity': '3.5',
                'line_total_rupiah': 28000,
              },
            ],
          },
        }),
        201,
      );
    });
    final repository = OrderRepository(
      ApiClient(client: client, baseUrl: _baseUrl),
    );

    final quote = await repository.createQuote(
      tenantId: 'tenant-1',
      branchId: 'branch-1',
      customerId: null,
      lines: const [OrderLineDraft(service: _service, quantity: '3,5')],
    );

    expect(quote.id, 'quote-1');
    expect(quote.totalRupiah, 28000);
    expect(quote.lines.single.serviceName, 'Kiloan');
  });

  test('accepts an order with a stable idempotency key', () async {
    const commandId = '11111111-1111-4111-8111-111111111111';
    final client = MockClient((request) async {
      expect(request.method, 'POST');
      expect(request.url.path, '/api/v1/orders');
      expect(request.headers['Idempotency-Key'], commandId);
      final body = jsonDecode(request.body) as Map<String, dynamic>;
      expect(body['client_command_id'], commandId);
      expect(body['quote_id'], 'quote-1');
      return http.Response(
        jsonEncode({
          'data': {
            'order_id': 'order-1',
            'order_number': 'ORD-001',
            'invoice_id': 'invoice-1',
            'invoice_number': 'INV-001',
            'total_rupiah': 28000,
            'paid_rupiah': 0,
            'balance_due_rupiah': 28000,
            'due_at_current': '2026-11-16T12:00:00Z',
          },
        }),
        201,
      );
    });
    final repository = OrderRepository(
      ApiClient(client: client, baseUrl: _baseUrl),
    );

    final order = await repository.acceptOrder(
      tenantId: 'tenant-1',
      branchId: 'branch-1',
      quoteId: 'quote-1',
      customerId: null,
      lines: const [OrderLineDraft(service: _service, quantity: '3.5')],
      commandId: commandId,
    );

    expect(order.orderId, 'order-1');
    expect(order.balanceDueRupiah, 28000);
  });

  test(
    'records a cash payment with the open shift and cash received',
    () async {
      final client = MockClient((request) async {
        expect(request.method, 'POST');
        expect(request.url.path, '/api/v1/invoices/invoice-1/payments');
        final body = jsonDecode(request.body) as Map<String, dynamic>;
        expect(body['method'], 'cash');
        expect(body['amount_rupiah'], 28000);
        expect(body['cash_session_id'], 'session-1');
        expect(body['cash_received_rupiah'], 50000);
        return http.Response(
          jsonEncode({
            'data': {
              'payment_id': 'payment-2',
              'invoice_status': 'paid',
              'total_rupiah': 28000,
              'paid_rupiah': 28000,
              'balance_due_rupiah': 0,
              'change_rupiah': 22000,
            },
          }),
          201,
        );
      });
      final repository = OrderRepository(
        ApiClient(client: client, baseUrl: _baseUrl),
      );

      final payment = await repository.recordPayment(
        tenantId: 'tenant-1',
        invoiceId: 'invoice-1',
        amountRupiah: 28000,
        method: 'cash',
        cashSessionId: 'session-1',
        cashReceivedRupiah: 50000,
        commandId: '33333333-3333-4333-8333-333333333333',
      );

      expect(payment.invoiceStatus, 'paid');
      expect(payment.changeRupiah, 22000);
    },
  );

  test('records a manual transfer payment and builds a receipt', () async {
    final client = MockClient((request) async {
      expect(request.headers['X-Tenant-ID'], 'tenant-1');
      if (request.url.path.endsWith('/payments')) {
        expect(request.url.path, '/api/v1/invoices/invoice-1/payments');
        expect(
          request.headers['Idempotency-Key'],
          '22222222-2222-4222-8222-222222222222',
        );
        final body = jsonDecode(request.body) as Map<String, dynamic>;
        expect(body['amount_rupiah'], 10000);
        expect(body['method'], 'bank_manual');
        expect(body.containsKey('cash_session_id'), isFalse);
        return http.Response(
          jsonEncode({
            'data': {
              'payment_id': 'payment-1',
              'invoice_status': 'partially_paid',
              'total_rupiah': 28000,
              'paid_rupiah': 10000,
              'balance_due_rupiah': 18000,
              'change_rupiah': 0,
            },
          }),
          201,
        );
      }

      expect(request.url.path, '/api/v1/orders/order-1/receipt');
      return http.Response(
        jsonEncode({
          'data': {
            'order_number': 'ORD-001',
            'invoice_number': 'INV-001',
            'due_at': '2026-11-16T12:00:00Z',
            'customer': {'name': 'Walk-in'},
            'branch': {'name': 'Cabang UAT'},
            'totals': {
              'total_rupiah': 28000,
              'paid_rupiah': 10000,
              'balance_due_rupiah': 18000,
            },
            'lines': [
              {
                'service_name': 'Kiloan',
                'unit': 'kg',
                'billable_quantity': '3.5',
                'line_total_rupiah': 28000,
              },
            ],
          },
        }),
        200,
      );
    });
    final repository = OrderRepository(
      ApiClient(client: client, baseUrl: _baseUrl),
    );

    final payment = await repository.recordPayment(
      tenantId: 'tenant-1',
      invoiceId: 'invoice-1',
      amountRupiah: 10000,
      method: 'bank_manual',
      commandId: '22222222-2222-4222-8222-222222222222',
    );
    final receipt = await repository.receipt(
      tenantId: 'tenant-1',
      orderId: 'order-1',
    );

    expect(payment.invoiceStatus, 'partially_paid');
    expect(payment.balanceDueRupiah, 18000);
    expect(receipt.orderNumber, 'ORD-001');
    expect(receipt.balanceDueRupiah, 18000);
    expect(receipt.lines.single.serviceName, 'Kiloan');
  });
}
