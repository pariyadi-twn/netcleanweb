import 'package:flutter/material.dart';

import '../../../core/ui/formatting.dart';
import '../../../core/ui/responsive.dart';
import '../../../core/ui/theme.dart';
import '../domain/order_models.dart';

class ReceiptScreen extends StatelessWidget {
  const ReceiptScreen({super.key, required this.receipt});

  final ReceiptSummary receipt;

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    final padding = Responsive.horizontalPadding(width);

    return Scaffold(
      appBar: AppBar(title: const Text('Nota')),
      body: SafeArea(
        child: ResponsiveCenter(
          child: ListView(
            padding: EdgeInsets.fromLTRB(padding, 24, padding, 32),
            children: [
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        receipt.branchName,
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                      const SizedBox(height: 4),
                      Text('Nota ${receipt.orderNumber}'),
                      Text('Invoice ${receipt.invoiceNumber}'),
                      const SizedBox(height: 16),
                      _ValueRow(
                        label: 'Pelanggan',
                        value: receipt.customerName,
                      ),
                      _ValueRow(
                        label: 'Estimasi selesai',
                        value: formatDateTime(receipt.dueAt),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 16),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Rincian layanan',
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                      const SizedBox(height: 12),
                      if (receipt.lines.isEmpty)
                        const Text('Tidak ada rincian layanan.')
                      else
                        ...receipt.lines.map(
                          (line) => Padding(
                            padding: const EdgeInsets.only(bottom: 10),
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Expanded(
                                  child: Text(
                                    '${line.serviceName} • '
                                    '${formatQuantity(line.billableQuantity)} '
                                    '${line.unit}',
                                  ),
                                ),
                                const SizedBox(width: 12),
                                Text(formatRupiah(line.lineTotalRupiah)),
                              ],
                            ),
                          ),
                        ),
                      const Divider(height: 24),
                      _ValueRow(
                        label: 'Total',
                        value: formatRupiah(receipt.totalRupiah),
                        emphasize: true,
                      ),
                      _ValueRow(
                        label: 'Dibayar',
                        value: formatRupiah(receipt.paidRupiah),
                      ),
                      _ValueRow(
                        label: 'Sisa',
                        value: formatRupiah(receipt.balanceDueRupiah),
                        emphasize: receipt.balanceDueRupiah > 0,
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 24),
              Text(
                'Simpan nota ini. Pembayaran transfer manual diverifikasi '
                'oleh kasir sesuai bukti transfer.',
                style: Theme.of(context).textTheme.bodyMedium,
              ),
              const SizedBox(height: 16),
              FilledButton(
                onPressed: () => Navigator.of(context).pop(),
                child: const Text('Pesanan baru'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ValueRow extends StatelessWidget {
  const _ValueRow({
    required this.label,
    required this.value,
    this.emphasize = false,
  });

  final String label;
  final String value;
  final bool emphasize;

  @override
  Widget build(BuildContext context) {
    final style = Theme.of(context).textTheme.bodyLarge?.copyWith(
      fontWeight: emphasize ? FontWeight.w700 : FontWeight.w400,
      color: emphasize ? AppTheme.text : AppTheme.textSecondary,
    );
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          Expanded(child: Text(label, style: style)),
          const SizedBox(width: 12),
          Text(value, style: style, textAlign: TextAlign.end),
        ],
      ),
    );
  }
}
