import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:netclean_staff/core/printer/esc_pos_receipt_builder.dart';
import 'package:netclean_staff/core/printer/printer_models.dart';
import 'package:netclean_staff/features/orders/domain/order_models.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  ReceiptSummary receipt() {
    return ReceiptSummary(
      orderId: 'order-public-id',
      orderNumber: 'UAT-MAIN-20261114-000001',
      invoiceNumber: 'INV-001',
      status: 'ready',
      customerName: 'Pelanggan UAT',
      branchName: 'NetClean UAT',
      branchPhone: '021-123456',
      branchAddress: 'Jl. Contoh No. 1',
      dueAt: DateTime.utc(2026, 11, 14, 12),
      acceptedAt: DateTime.utc(2026, 11, 14, 9),
      totalRupiah: 28000,
      paidRupiah: 10000,
      balanceDueRupiah: 18000,
      serviceSubtotalRupiah: 28000,
      receiptHash: 'abcdef0123456789',
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

  test('builds a readable 80 mm ESC/POS receipt', () async {
    final bytes = await EscPosReceiptBuilder().build(
      receipt(),
      PaperWidth.mm80,
    );
    final text = latin1.decode(bytes);

    expect(bytes, isNotEmpty);
    expect(text, contains('NetClean UAT'));
    expect(text, contains('NOTA UAT-MAIN-20261114-000001'));
    expect(text, contains('Pelanggan UAT'));
    expect(text, contains('Cuci Kiloan'));
    expect(text, contains('28.000'));
    expect(text, contains('18.000'));
  });

  test('builds a 58 mm test ticket', () async {
    final bytes = await EscPosReceiptBuilder().buildTest(PaperWidth.mm58);
    final text = latin1.decode(bytes);

    expect(bytes, isNotEmpty);
    expect(text, contains('NetClean Staff'));
    expect(text, contains('58 mm'));
  });
}
