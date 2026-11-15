import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/session/session_controller.dart';
import '../../../core/ui/formatting.dart';
import '../../../core/ui/page_header.dart';
import '../../../core/ui/responsive.dart';
import '../../../core/ui/status_chip.dart';
import '../../../core/ui/theme.dart';
import '../domain/order_history_models.dart';
import 'order_detail_screen.dart';

class OrderHistoryScreen extends ConsumerStatefulWidget {
  const OrderHistoryScreen({super.key});

  @override
  ConsumerState<OrderHistoryScreen> createState() => _OrderHistoryScreenState();
}

class _OrderHistoryScreenState extends ConsumerState<OrderHistoryScreen> {
  final _search = TextEditingController();
  List<OrderHistory> _orders = const [];
  String? _nextCursor;
  bool _loading = false;
  String? _error;
  int _requestId = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _load({bool append = false}) async {
    final tenantId = ref
        .read(sessionControllerProvider)
        .asData
        ?.value
        ?.tenantId;
    if (tenantId == null) {
      setState(() => _error = 'Konteks tenant belum tersedia.');
      return;
    }
    final requestId = ++_requestId;
    setState(() {
      _loading = true;
      _error = null;
      if (!append) {
        _orders = const [];
        _nextCursor = null;
      }
    });
    try {
      final page = await ref
          .read(orderRepositoryProvider)
          .searchOrders(
            tenantId: tenantId,
            orderNumber: _search.text,
            cursor: append ? _nextCursor : null,
          );
      if (!mounted || requestId != _requestId) {
        return;
      }
      setState(() {
        _orders = append ? [..._orders, ...page.items] : page.items;
        _nextCursor = page.nextCursor;
        _loading = false;
      });
    } on ApiException catch (error) {
      if (mounted && requestId == _requestId) {
        setState(() {
          _loading = false;
          _error = error.message;
        });
      }
    } catch (_) {
      if (mounted && requestId == _requestId) {
        setState(() {
          _loading = false;
          _error = 'Riwayat pesanan belum dapat dimuat.';
        });
      }
    }
  }

  Future<void> _openDetail(OrderHistory order) async {
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
      appBar: AppBar(title: const Text('Pesanan')),
      body: DecoratedBox(
        decoration: const BoxDecoration(gradient: AppTheme.canvasGradient),
        child: SafeArea(
          top: false,
          child: ResponsiveCenter(
            child: RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                key: const Key('order-history-screen'),
                physics: const AlwaysScrollableScrollPhysics(),
                padding: EdgeInsets.fromLTRB(padding, 18, padding, 32),
                children: [
                  const AppPageHeader(
                    eyebrow: 'Riwayat',
                    title: 'Cari pesanan',
                    subtitle: 'Buka detail, status, dan sisa pembayaran.',
                  ),
                  const SizedBox(height: 18),
                  TextField(
                    key: const Key('order-history-search'),
                    controller: _search,
                    textInputAction: TextInputAction.search,
                    onSubmitted: (_) => _load(),
                    decoration: InputDecoration(
                      labelText: 'Cari nomor nota',
                      prefixIcon: const Icon(Icons.search_rounded),
                      suffixIcon: IconButton(
                        tooltip: 'Cari',
                        onPressed: _load,
                        icon: const Icon(Icons.arrow_forward_rounded),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  if (_error != null)
                    _ErrorPanel(message: _error!, onRetry: _load)
                  else if (_loading && _orders.isEmpty)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 48),
                      child: Center(child: CircularProgressIndicator()),
                    )
                  else if (_orders.isEmpty)
                    const _EmptyOrders()
                  else ...[
                    ..._orders.map(
                      (order) => Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: _OrderCard(
                          order: order,
                          onTap: () => _openDetail(order),
                        ),
                      ),
                    ),
                    if (_nextCursor != null)
                      OutlinedButton.icon(
                        onPressed: _loading ? null : () => _load(append: true),
                        icon: const Icon(Icons.expand_more_rounded),
                        label: Text(_loading ? 'Memuat...' : 'Muat lagi'),
                      ),
                  ],
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _OrderCard extends StatelessWidget {
  const _OrderCard({required this.order, required this.onTap});

  final OrderHistory order;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final (tone, color, background, icon) = switch (order.status) {
      'completed' => (
        StatusTone.success,
        const Color(0xFF0F6B4F),
        const Color(0xFFE7F7EF),
        Icons.check_circle_outline_rounded,
      ),
      'cancelled' => (
        StatusTone.danger,
        const Color(0xFFB42318),
        const Color(0xFFFEECE9),
        Icons.cancel_outlined,
      ),
      _ => (
        StatusTone.info,
        const Color(0xFF0E5F8A),
        const Color(0xFFE8F4FB),
        Icons.receipt_long_outlined,
      ),
    };

    return Card(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppTheme.containerRadius),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 46,
                height: 46,
                decoration: BoxDecoration(
                  color: background,
                  borderRadius: BorderRadius.circular(15),
                ),
                child: Icon(icon, color: color, size: 23),
              ),
              const SizedBox(width: 13),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      order.orderNumber,
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    const SizedBox(height: 3),
                    Text(
                      '${formatDateTime(order.dueAt)} • '
                      '${order.lines.length} layanan • '
                      '${order.bags.length} bag',
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                    const SizedBox(height: 9),
                    StatusChip(
                      label: order.status,
                      icon: Icons.circle_outlined,
                      tone: tone,
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    formatRupiah(order.balanceDueRupiah),
                    style: Theme.of(context).textTheme.titleSmall,
                  ),
                  const SizedBox(height: 3),
                  Text(
                    order.balanceDueRupiah > 0 ? 'Sisa' : 'Lunas',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                  const SizedBox(height: 8),
                  const Icon(
                    Icons.chevron_right_rounded,
                    size: 20,
                    color: AppTheme.textSecondary,
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _EmptyOrders extends StatelessWidget {
  const _EmptyOrders();

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 48, horizontal: 16),
      child: Column(
        children: [
          const AppIconTile(icon: Icons.receipt_long_outlined, size: 56),
          const SizedBox(height: 14),
          Text(
            'Belum ada pesanan',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 4),
          Text(
            'Coba nomor nota lain atau buat pesanan baru.',
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ],
      ),
    );
  }
}

class _ErrorPanel extends StatelessWidget {
  const _ErrorPanel({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final palette =
        Theme.of(context).extension<AppStatusColors>() ??
        AppStatusColors.standard;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: palette.dangerBackground,
        borderRadius: BorderRadius.circular(18),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(message),
          const SizedBox(height: 12),
          FilledButton.tonal(
            onPressed: onRetry,
            child: const Text('Coba lagi'),
          ),
        ],
      ),
    );
  }
}
