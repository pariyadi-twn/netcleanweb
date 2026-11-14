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
      appBar: AppBar(title: const Text('Riwayat pesanan')),
      body: SafeArea(
        child: ResponsiveCenter(
          child: RefreshIndicator(
            onRefresh: _load,
            child: ListView(
              key: const Key('order-history-screen'),
              physics: const AlwaysScrollableScrollPhysics(),
              padding: EdgeInsets.fromLTRB(padding, 24, padding, 32),
              children: [
                TextField(
                  key: const Key('order-history-search'),
                  controller: _search,
                  textInputAction: TextInputAction.search,
                  onSubmitted: (_) => _load(),
                  decoration: InputDecoration(
                    labelText: 'Cari nomor nota',
                    prefixIcon: const Icon(Icons.search),
                    suffixIcon: IconButton(
                      tooltip: 'Cari',
                      onPressed: _load,
                      icon: const Icon(Icons.arrow_forward),
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
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 48),
                    child: Center(child: Text('Belum ada pesanan.')),
                  )
                else ...[
                  ..._orders.map(
                    (order) => Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: Card(
                        child: ListTile(
                          onTap: () => _openDetail(order),
                          title: Text(order.orderNumber),
                          subtitle: Text(
                            '${formatDateTime(order.dueAt)} • '
                            '${order.lines.length} layanan • '
                            '${order.bags.length} bag',
                          ),
                          trailing: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            crossAxisAlignment: CrossAxisAlignment.end,
                            children: [
                              StatusChip(
                                label: order.status,
                                icon: Icons.circle_outlined,
                                tone: order.status == 'completed'
                                    ? StatusTone.success
                                    : order.status == 'cancelled'
                                    ? StatusTone.danger
                                    : StatusTone.info,
                              ),
                              const SizedBox(height: 4),
                              Text(formatRupiah(order.balanceDueRupiah)),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
                  if (_nextCursor != null)
                    OutlinedButton(
                      onPressed: _loading ? null : () => _load(append: true),
                      child: Text(_loading ? 'Memuat...' : 'Muat lagi'),
                    ),
                ],
              ],
            ),
          ),
        ),
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
        borderRadius: BorderRadius.circular(12),
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
