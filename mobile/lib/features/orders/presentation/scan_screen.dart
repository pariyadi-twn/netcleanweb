import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/scan/scan_payload.dart';
import '../../../core/session/session_controller.dart';
import '../../../core/ui/formatting.dart';
import '../../../core/ui/page_header.dart';
import '../../../core/ui/responsive.dart';
import '../../../core/ui/status_chip.dart';
import '../../../core/ui/theme.dart';
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

  Future<void> _openScanner() async {
    final code = await ref
        .read(codeScannerProvider)
        .scan(context, title: 'Scan nota');
    if (!mounted || code == null) {
      return;
    }
    final normalized = ScanPayload.normalize(code);
    if (normalized == null) {
      setState(() => _error = 'Kode tidak valid. Coba scan ulang.');
      return;
    }
    _code.text = normalized;
    await _scan();
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
      appBar: AppBar(title: const Text('Scan')),
      body: DecoratedBox(
        decoration: const BoxDecoration(gradient: AppTheme.canvasGradient),
        child: SafeArea(
          top: false,
          child: ResponsiveCenter(
            child: ListView(
              key: const Key('scan-screen'),
              padding: EdgeInsets.fromLTRB(padding, 18, padding, 32),
              children: [
                const AppPageHeader(
                  eyebrow: 'Cari cepat',
                  title: 'Temukan pesanan',
                  subtitle:
                      'Scan QR nota dengan kamera, atau masukkan nomor nota '
                      'secara manual.',
                ),
                const SizedBox(height: 18),
                _ScanHero(busy: _busy),
                const SizedBox(height: 18),
                FilledButton.icon(
                  onPressed: _busy ? null : _openScanner,
                  icon: const Icon(Icons.qr_code_scanner_rounded),
                  label: const Text('Scan kamera'),
                ),
                const SizedBox(height: 12),
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
                        icon: const Icon(Icons.search_rounded),
                        label: const Text('Cari nota'),
                      ),
                    ),
                    const SizedBox(width: 10),
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
                  _ScanError(message: _error!),
                ],
                if (_order != null) ...[
                  const SizedBox(height: 18),
                  _ScannedOrderCard(order: _order!, onOpen: _open),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _ScanHero extends StatelessWidget {
  const _ScanHero({required this.busy});

  final bool busy;

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(26),
      child: Container(
        decoration: const BoxDecoration(gradient: AppTheme.heroGradient),
        child: Stack(
          children: [
            Positioned(
              right: -30,
              top: -50,
              child: Container(
                width: 150,
                height: 150,
                decoration: BoxDecoration(
                  color: AppTheme.accent.withValues(alpha: 0.16),
                  shape: BoxShape.circle,
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(22),
              child: Row(
                children: [
                  Container(
                    width: 58,
                    height: 58,
                    decoration: BoxDecoration(
                      color: AppTheme.accent,
                      borderRadius: BorderRadius.circular(19),
                    ),
                    child: busy
                        ? const Padding(
                            padding: EdgeInsets.all(18),
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: AppTheme.primaryDeep,
                            ),
                          )
                        : const Icon(
                            Icons.qr_code_scanner_rounded,
                            color: AppTheme.primaryDeep,
                            size: 28,
                          ),
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Scan nota',
                          style: Theme.of(
                            context,
                          ).textTheme.titleLarge?.copyWith(color: Colors.white),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          'Gunakan kamera untuk membaca QR nota. Input manual '
                          'tetap tersedia sebagai fallback.',
                          style: Theme.of(context).textTheme.bodySmall
                              ?.copyWith(
                                color: Colors.white.withValues(alpha: 0.65),
                              ),
                        ),
                      ],
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

class _ScannedOrderCard extends StatelessWidget {
  const _ScannedOrderCard({required this.order, required this.onOpen});

  final OrderHistory order;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const AppIconTile(icon: Icons.receipt_long_outlined, size: 44),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    order.orderNumber,
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                ),
                StatusChip(
                  label: order.status,
                  icon: Icons.circle_outlined,
                  tone: order.status == 'completed'
                      ? StatusTone.success
                      : StatusTone.info,
                ),
              ],
            ),
            const Divider(height: 26),
            _ScanValue(label: 'Total', value: formatRupiah(order.totalRupiah)),
            _ScanValue(
              label: 'Sisa',
              value: formatRupiah(order.balanceDueRupiah),
            ),
            _ScanValue(label: 'Estimasi', value: formatDateTime(order.dueAt)),
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                onPressed: onOpen,
                icon: const Icon(Icons.open_in_new_rounded, size: 18),
                label: const Text('Buka detail pesanan'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ScanValue extends StatelessWidget {
  const _ScanValue({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
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

class _ScanError extends StatelessWidget {
  const _ScanError({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: scheme.errorContainer,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.error_outline_rounded, color: scheme.error, size: 19),
          const SizedBox(width: 9),
          Expanded(
            child: Text(
              message,
              style: Theme.of(
                context,
              ).textTheme.bodySmall?.copyWith(color: scheme.error),
            ),
          ),
        ],
      ),
    );
  }
}
