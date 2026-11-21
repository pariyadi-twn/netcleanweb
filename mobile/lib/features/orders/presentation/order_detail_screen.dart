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
import 'receipt_preview_screen.dart';
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

  Future<void> _openPrintPreview() async {
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
          builder: (context) => ReceiptPreviewScreen(receipt: receipt),
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
      appBar: AppBar(title: const Text('Detail pesanan')),
      body: SafeArea(
        child: ResponsiveCenter(
          child: ListView(
            key: const Key('order-detail-screen'),
            padding: EdgeInsets.fromLTRB(padding, 18, padding, 32),
            children: [
              _OrderSummary(order: order),
              const SizedBox(height: 16),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Informasi pesanan',
                        style: Theme.of(context).textTheme.titleLarge,
                      ),
                      const SizedBox(height: 14),
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
                onPressed: _busy ? null : _openPrintPreview,
                icon: const Icon(Icons.print_outlined),
                label: const Text('Pratinjau & cetak nota'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _OrderSummary extends StatelessWidget {
  const _OrderSummary({required this.order});

  final OrderHistory order;

  @override
  Widget build(BuildContext context) {
    final tone = order.status == 'completed'
        ? StatusTone.success
        : order.status == 'cancelled'
        ? StatusTone.danger
        : StatusTone.info;

    return ClipRRect(
      borderRadius: BorderRadius.circular(28),
      child: Container(
        decoration: const BoxDecoration(gradient: AppTheme.heroGradient),
        child: Stack(
          children: [
            Positioned(
              right: -50,
              top: -70,
              child: Container(
                width: 180,
                height: 180,
                decoration: BoxDecoration(
                  color: AppTheme.accent.withValues(alpha: 0.15),
                  shape: BoxShape.circle,
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(22),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Container(
                        width: 44,
                        height: 44,
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.1),
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(
                            color: Colors.white.withValues(alpha: 0.12),
                          ),
                        ),
                        child: const Icon(
                          Icons.receipt_long_outlined,
                          color: AppTheme.accent,
                          size: 22,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              order.orderNumber,
                              style: Theme.of(context).textTheme.titleLarge
                                  ?.copyWith(color: Colors.white),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              'Invoice ${order.invoiceNumber}',
                              style: Theme.of(context).textTheme.bodySmall
                                  ?.copyWith(
                                    color: Colors.white.withValues(alpha: 0.55),
                                  ),
                            ),
                          ],
                        ),
                      ),
                      StatusChip(
                        label: order.status,
                        icon: Icons.circle_outlined,
                        tone: tone,
                      ),
                    ],
                  ),
                  const SizedBox(height: 24),
                  Text(
                    'Sisa tagihan',
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: Colors.white.withValues(alpha: 0.55),
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    formatRupiah(order.balanceDueRupiah),
                    style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                      color: Colors.white,
                      fontSize: 32,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    order.balanceDueRupiah > 0
                        ? 'Total ${formatRupiah(order.totalRupiah)} • '
                              'dibayar ${formatRupiah(order.paidRupiah)}'
                        : 'Lunas • total ${formatRupiah(order.totalRupiah)}',
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: Colors.white.withValues(alpha: 0.6),
                    ),
                  ),
                ],
              ),
            ),
          ],
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
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Text(label, style: Theme.of(context).textTheme.bodyMedium),
          ),
          const SizedBox(width: 12),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.end,
              style: Theme.of(context).textTheme.titleSmall,
            ),
          ),
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
