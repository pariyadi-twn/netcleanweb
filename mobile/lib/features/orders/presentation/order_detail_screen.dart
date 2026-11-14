import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/session/session_controller.dart';
import '../../../core/ui/formatting.dart';
import '../../../core/ui/responsive.dart';
import '../../../core/ui/status_chip.dart';
import '../../../core/ui/theme.dart';
import '../domain/order_history_models.dart';
import 'receipt_screen.dart';

class OrderDetailScreen extends ConsumerStatefulWidget {
  const OrderDetailScreen({super.key, required this.order});

  final OrderHistory order;

  @override
  ConsumerState<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends ConsumerState<OrderDetailScreen> {
  bool _busy = false;

  String? get _tenantId =>
      ref.read(sessionControllerProvider).asData?.value?.tenantId;

  Future<void> _openReceipt() async {
    final tenantId = _tenantId;
    if (tenantId == null) {
      return;
    }
    setState(() => _busy = true);
    try {
      final receipt = await ref
          .read(orderRepositoryProvider)
          .receipt(tenantId: tenantId, orderId: widget.order.orderId);
      if (!mounted) {
        return;
      }
      await Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (context) => ReceiptScreen(receipt: receipt),
        ),
      );
    } on ApiException catch (error) {
      _say(error.message);
    } catch (_) {
      _say('Nota belum dapat dimuat.');
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _simulatePrint() async {
    final tenantId = _tenantId;
    if (tenantId == null) {
      return;
    }
    setState(() => _busy = true);
    try {
      final receipt = await ref
          .read(orderRepositoryProvider)
          .receipt(tenantId: tenantId, orderId: widget.order.orderId);
      if (!mounted) {
        return;
      }
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Simulasi cetak nota'),
          content: Text(
            'Nota ${receipt.orderNumber}\n'
            'Total ${formatRupiah(receipt.totalRupiah)}\n'
            'Dibayar ${formatRupiah(receipt.paidRupiah)}\n'
            'Sisa ${formatRupiah(receipt.balanceDueRupiah)}\n\n'
            'Printer fisik belum tersedia. Percobaan cetak akan dicatat '
            'sebagai simulasi berhasil.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(false),
              child: const Text('Batal'),
            ),
            FilledButton(
              onPressed: () => Navigator.of(context).pop(true),
              child: const Text('Catat simulasi cetak'),
            ),
          ],
        ),
      );
      if (confirmed == true) {
        await ref
            .read(orderRepositoryProvider)
            .recordPrintAttempt(
              tenantId: tenantId,
              orderId: widget.order.orderId,
            );
        _say('Cetak disimulasikan dan tercatat.');
      }
    } on ApiException catch (error) {
      _say(error.message);
    } catch (_) {
      _say('Simulasi cetak belum dapat dicatat.');
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  void _say(String message) {
    if (!mounted) {
      return;
    }
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    final order = widget.order;
    final width = MediaQuery.sizeOf(context).width;
    final padding = Responsive.horizontalPadding(width);

    return Scaffold(
      appBar: AppBar(title: Text(order.orderNumber)),
      body: SafeArea(
        child: ResponsiveCenter(
          child: ListView(
            key: const Key('order-detail-screen'),
            padding: EdgeInsets.fromLTRB(padding, 24, padding, 32),
            children: [
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              order.orderNumber,
                              style: Theme.of(context).textTheme.headlineSmall,
                            ),
                          ),
                          StatusChip(
                            label: order.status,
                            icon: Icons.circle_outlined,
                            tone: order.status == 'completed'
                                ? StatusTone.success
                                : order.status == 'cancelled'
                                ? StatusTone.danger
                                : StatusTone.info,
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      _DetailRow(label: 'Invoice', value: order.invoiceNumber),
                      _DetailRow(
                        label: 'Status invoice',
                        value: order.invoiceStatus,
                      ),
                      _DetailRow(
                        label: 'Estimasi selesai',
                        value: formatDateTime(order.dueAt),
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
                      if (order.lines.isEmpty)
                        const Text('Tidak ada rincian layanan.')
                      else
                        ...order.lines.map(
                          (line) => Padding(
                            padding: const EdgeInsets.only(bottom: 10),
                            child: Row(
                              children: [
                                Expanded(
                                  child: Text(
                                    '${line.serviceName} • '
                                    '${formatQuantity(line.billableQuantity)} '
                                    '${line.unit}',
                                  ),
                                ),
                                Text(formatRupiah(line.lineTotalRupiah)),
                              ],
                            ),
                          ),
                        ),
                      const Divider(height: 24),
                      _AmountRow(label: 'Total', amount: order.totalRupiah),
                      _AmountRow(label: 'Dibayar', amount: order.paidRupiah),
                      _AmountRow(
                        label: 'Sisa',
                        amount: order.balanceDueRupiah,
                        strong: order.balanceDueRupiah > 0,
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
                        'Bag',
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                      const SizedBox(height: 8),
                      if (order.bags.isEmpty)
                        const Text('Belum ada bag.')
                      else
                        ...order.bags.map(
                          (bag) => _DetailRow(
                            label: bag.labelCode ?? bag.id.substring(0, 8),
                            value: bag.custodyStatus,
                          ),
                        ),
                    ],
                  ),
                ),
              ),
              if (_busy) ...[
                const SizedBox(height: 16),
                const LinearProgressIndicator(),
              ],
              const SizedBox(height: 20),
              FilledButton.icon(
                onPressed: _busy ? null : _openReceipt,
                icon: const Icon(Icons.receipt_long_outlined),
                label: const Text('Lihat nota'),
              ),
              const SizedBox(height: 10),
              OutlinedButton.icon(
                onPressed: _busy ? null : _simulatePrint,
                icon: const Icon(Icons.print_outlined),
                label: const Text('Simulasi cetak nota'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _DetailRow extends StatelessWidget {
  const _DetailRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        children: [
          Expanded(child: Text(label)),
          const SizedBox(width: 12),
          Text(value),
        ],
      ),
    );
  }
}

class _AmountRow extends StatelessWidget {
  const _AmountRow({
    required this.label,
    required this.amount,
    this.strong = false,
  });

  final String label;
  final int amount;
  final bool strong;

  @override
  Widget build(BuildContext context) {
    final style = Theme.of(context).textTheme.bodyLarge?.copyWith(
      fontWeight: strong ? FontWeight.w700 : FontWeight.w400,
      color: strong ? AppTheme.text : AppTheme.textSecondary,
    );
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        children: [
          Expanded(child: Text(label, style: style)),
          Text(formatRupiah(amount), style: style),
        ],
      ),
    );
  }
}
