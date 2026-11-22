import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:netclean_staff/core/auth/auth_session.dart';
import 'package:netclean_staff/core/network/api_client.dart';
import 'package:netclean_staff/core/providers.dart';
import 'package:netclean_staff/core/session/session_controller.dart';
import 'package:netclean_staff/features/orders/data/order_repository.dart';
import 'package:netclean_staff/features/payments/presentation/order_payment_sheet.dart';

class _CashierSession extends SessionController {
  @override
  Future<AuthSession?> build() async {
    return AuthSession(
      token: 'token',
      expiresAt: DateTime.now().toUtc().add(const Duration(hours: 1)),
      deviceId: 'device',
      tenantId: 'tenant-public-id',
      membershipId: 'membership-public-id',
      branchIds: const ['branch-public-id'],
      permissions: const ['payments.manage'],
    );
  }
}

void main() {
  testWidgets('QRIS payment requires manual confirmation and sends reference', (
    tester,
  ) async {
    Map<String, dynamic>? capturedBody;
    final client = MockClient((request) async {
      expect(request.url.path, '/api/v1/invoices/invoice-1/payments');
      capturedBody = jsonDecode(request.body) as Map<String, dynamic>;
      return http.Response(
        jsonEncode({
          'data': {
            'payment_id': 'payment-qris',
            'invoice_status': 'paid',
            'total_rupiah': 28000,
            'paid_rupiah': 28000,
            'balance_due_rupiah': 0,
            'change_rupiah': 0,
            'method': 'qris_manual',
            'reference': 'QRIS-1',
          },
        }),
        201,
      );
    });
    final repository = OrderRepository(
      ApiClient(client: client, baseUrl: 'https://example.test/api/v1'),
    );

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          sessionControllerProvider.overrideWith(_CashierSession.new),
          activeCashSessionProvider.overrideWith((ref) async => null),
          orderRepositoryProvider.overrideWithValue(repository),
        ],
        child: MaterialApp(
          home: Scaffold(
            body: Builder(
              builder: (context) => Center(
                child: FilledButton(
                  onPressed: () {
                    showOrderPaymentSheet(
                      context: context,
                      invoiceId: 'invoice-1',
                      orderNumber: 'ORD-1',
                      balanceDueRupiah: 28000,
                    );
                  },
                  child: const Text('Buka pembayaran'),
                ),
              ),
            ),
          ),
        ),
      ),
    );

    await tester.tap(find.text('Buka pembayaran'));
    await tester.pumpAndSettle();
    expect(find.text('Catat pembayaran'), findsOneWidget);

    await tester.tap(find.text('QRIS'));
    await tester.pumpAndSettle();
    expect(find.textContaining('Tunjukkan standee QRIS'), findsOneWidget);

    await tester.tap(find.text('Konfirmasi QRIS diterima'));
    await tester.pumpAndSettle();
    expect(capturedBody, isNull);
    expect(
      find.textContaining('Konfirmasi bahwa pembayaran QRIS'),
      findsOneWidget,
    );

    await tester.enterText(find.byType(TextField).last, 'QRIS-1');
    await tester.tap(find.byType(CheckboxListTile));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Konfirmasi QRIS diterima'));
    await tester.pumpAndSettle();

    expect(capturedBody?['method'], 'qris_manual');
    expect(capturedBody?['reference'], 'QRIS-1');
    expect(capturedBody?.containsKey('cash_session_id'), isFalse);
  });
}
