import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/session/session_controller.dart';
import '../../../core/ui/formatting.dart';
import '../../../core/ui/responsive.dart';
import '../../../core/ui/status_chip.dart';
import '../domain/order_history_models.dart';
import 'order_detail_screen.dart';

class ScanScreen extends ConsumerStatefulWidget {
  const ScanScreen({super.key});

  @override
  ConsumerState<ScanScreen> createState() => _ScanScreenState();
}

class _ScanScreenState extends ConsumerState<ScanScreen> {
  final _code = TextEditingController();
  OrderHistory? _order;
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  Future<void> _scan({bool latest = false}) async {
    final tenantId = ref
        .read(sessionControllerProvider)
        .asData
        ?.value
        ?.tenantId;
    if (tenantId == null) {
      setState(() => _error = 'Konteks tenant belum tersedia.');
      return;
    }
    if (!latest && _code.text.trim().isEmpty) {
      setState(() => _error = 'Masukkan nomor nota terlebih dahulu.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
      _order = null;
    });
    try {
      final page = await ref
          .read(orderRepositoryProvider)
          .searchOrders(
            tenantId: tenantId,
            orderNumber: latest ? null : _code.text,
            perPage: 1,
          );
      if (!mounted) {
        return;
      }
      if (page.items.isEmpty) {
        setState(() => _error = 'Order tidak ditemukan.');
      } else {
        setState(() => _order = page.items.first);
      }
    } on ApiException catch (error) {
      if (mounted) {
        setState(() => _error = error.message);
      }
    } catch (_) {
      if (mounted) {
        setState(() => _error = 'Pencarian order gagal.');
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _open() async {
    final order = _order;
    if (order == null) {
      return;
    }
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (context) => OrderDetailScreen(order: order),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    final padding = Responsive.horizontalPadding(width);

    return Scaffold(
      appBar: AppBar(title: const Text('Scan nota')),
      body: SafeArea(
        child: ResponsiveCenter(
          child: ListView(
            key: const Key('scan-screen'),
            padding: EdgeInsets.fromLTRB(padding, 24, padding, 32),
            children: [
              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: Theme.of(context).colorScheme.primaryContainer,
                  borderRadius: BorderRadius.circular(24),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(
                      Icons.qr_code_scanner,
                      size: 48,
                      color: Theme.of(context).colorScheme.primary,
                    ),
                    const SizedBox(height: 12),
                    Text(
                      'Simulasi scan',
                      style: Theme.of(context).textTheme.headlineSmall,
                    ),
                    const SizedBox(height: 6),
                    const Text(
                      'Kamera/scan fisik belum tersedia. Masukkan nomor nota '
                      'atau buka order terbaru sebagai fallback manual.',
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 20),
              TextField(
                key: const Key('scan-order-number'),
                controller: _code,
                textCapitalization: TextCapitalization.characters,
                textInputAction: TextInputAction.search,
                onSubmitted: (_) => _scan(),
                decoration: const InputDecoration(
                  labelText: 'Nomor nota',
                  prefixIcon: Icon(Icons.confirmation_number_outlined),
                ),
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: FilledButton.icon(
                      onPressed: _busy ? null : _scan,
                      icon: const Icon(Icons.search),
                      label: const Text('Cari nota'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: OutlinedButton(
                      onPressed: _busy ? null : () => _scan(latest: true),
                      child: const Text('Order terbaru'),
                    ),
                  ),
                ],
              ),
              if (_busy) ...[
                const SizedBox(height: 16),
                const LinearProgressIndicator(),
              ],
              if (_error != null) ...[
                const SizedBox(height: 16),
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFEF2F2),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Text(_error!),
                ),
              ],
              if (_order != null) ...[
                const SizedBox(height: 20),
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
                                _order!.orderNumber,
                                style: Theme.of(context).textTheme.titleMedium,
                              ),
                            ),
                            StatusChip(
                              label: _order!.status,
                              icon: Icons.circle_outlined,
                              tone: _order!.status == 'completed'
                                  ? StatusTone.success
                                  : StatusTone.info,
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Text(
                          'Total ${formatRupiah(_order!.totalRupiah)} • '
                          'Sisa ${formatRupiah(_order!.balanceDueRupiah)}',
                        ),
                        Text('Estimasi ${formatDateTime(_order!.dueAt)}'),
                        const SizedBox(height: 16),
                        FilledButton(
                          onPressed: _open,
                          child: const Text('Buka detail pesanan'),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
