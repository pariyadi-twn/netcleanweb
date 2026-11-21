import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:netclean_staff/core/auth/secret_store.dart';
import 'package:netclean_staff/core/providers.dart';
import 'package:netclean_staff/features/orders/domain/order_models.dart';
import 'package:netclean_staff/features/orders/presentation/receipt_preview_screen.dart';

void main() {
  ReceiptSummary receipt() {
    return ReceiptSummary(
      orderId: 'order-public-id',
      orderNumber: 'UAT-MAIN-20261114-000001',
      invoiceNumber: 'INV-001',
      status: 'ready',
      customerName: 'Pelanggan UAT',
      branchName: 'NetClean UAT',
      dueAt: DateTime.utc(2026, 11, 14, 12),
      totalRupiah: 28000,
      paidRupiah: 10000,
      balanceDueRupiah: 18000,
      serviceSubtotalRupiah: 28000,
      lines: const [
        ReceiptLineSummary(
          serviceName: 'Cuci Kiloan',
          unit: 'kg',
          billableQuantity: '3.0',
          unitPriceRupiah: 9333,
          lineTotalRupiah: 28000,
        ),
      ],
    );
  }

  testWidgets('shows a complete receipt preview and printer actions', (
    tester,
  ) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [secretStoreProvider.overrideWithValue(MemorySecretStore())],
        child: MaterialApp(home: ReceiptPreviewScreen(receipt: receipt())),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Pratinjau sebelum cetak'), findsOneWidget);
    expect(find.text('NetClean UAT'), findsOneWidget);
    expect(find.text('NOTA UAT-MAIN-20261114-000001'), findsOneWidget);
    expect(find.text('Pelanggan UAT'), findsOneWidget);
    expect(find.text('Cuci Kiloan'), findsOneWidget);
    expect(find.text('Rp28.000'), findsWidgets);
    expect(find.text('Printer belum diatur'), findsOneWidget);
    expect(find.text('Cetak'), findsOneWidget);
    expect(find.text('Atur'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('switches the preview between 80 mm and 58 mm', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [secretStoreProvider.overrideWithValue(MemorySecretStore())],
        child: MaterialApp(home: ReceiptPreviewScreen(receipt: receipt())),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.textContaining('80 mm •'), findsOneWidget);
    await tester.tap(find.text('58'));
    await tester.pumpAndSettle();
    expect(find.textContaining('58 mm •'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
