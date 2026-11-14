import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/session/session_controller.dart';
import '../../../core/ui/responsive.dart';
import '../../../core/ui/status_chip.dart';
import '../../../core/ui/theme.dart';
import '../domain/customer_summary.dart';
import 'customer_form_dialog.dart';

class CustomersScreen extends ConsumerStatefulWidget {
  const CustomersScreen({super.key});

  @override
  ConsumerState<CustomersScreen> createState() => _CustomersScreenState();
}

class _CustomersScreenState extends ConsumerState<CustomersScreen> {
  final _search = TextEditingController();
  Timer? _debounce;
  List<CustomerSummary> _customers = const [];
  String? _nextCursor;
  bool _loading = false;
  String? _error;
  int _requestId = 0;

  @override
  void initState() {
    super.initState();
    _search.addListener(_onSearchChanged);
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _search
      ..removeListener(_onSearchChanged)
      ..dispose();
    super.dispose();
  }

  void _onSearchChanged() {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 300), () {
      _load(query: _search.text.trim());
    });
  }

  Future<void> _load({String? query, bool append = false}) async {
    final session = ref.read(sessionControllerProvider).asData?.value;
    final tenantId = session?.tenantId;
    if (tenantId == null) {
      setState(() {
        _error =
            'Konteks tenant belum tersedia. Login ulang atau hubungi admin.';
        _loading = false;
      });
      return;
    }

    final requestId = ++_requestId;
    setState(() {
      _loading = true;
      _error = null;
      if (!append) {
        _customers = const [];
        _nextCursor = null;
      }
    });

    try {
      final page = await ref
          .read(customerRepositoryProvider)
          .search(
            tenantId: tenantId,
            query: query ?? _search.text.trim(),
            cursor: append ? _nextCursor : null,
          );
      if (!mounted || requestId != _requestId) {
        return;
      }
      setState(() {
        _customers = append
            ? [
                ..._customers,
                ...page.items.where(
                  (item) =>
                      !_customers.any((existing) => existing.id == item.id),
                ),
              ]
            : page.items;
        _nextCursor = page.nextCursor;
        _loading = false;
      });
    } on ApiException catch (error) {
      if (!mounted || requestId != _requestId) {
        return;
      }
      setState(() {
        _loading = false;
        _error = error.message;
      });
    } catch (_) {
      if (!mounted || requestId != _requestId) {
        return;
      }
      setState(() {
        _loading = false;
        _error = 'Daftar pelanggan belum dapat dimuat.';
      });
    }
  }

  Future<void> _createCustomer() async {
    final session = ref.read(sessionControllerProvider).asData?.value;
    final tenantId = session?.tenantId;
    if (tenantId == null) {
      return;
    }

    final draft = await showCustomerFormDialog(context);
    if (draft == null || !mounted) {
      return;
    }

    try {
      await ref
          .read(customerRepositoryProvider)
          .create(tenantId: tenantId, name: draft.name, phone: draft.phone);
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Pelanggan ditambahkan.')));
      await _load(query: _search.text.trim());
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.message)));
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Pelanggan belum dapat disimpan.')),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    final padding = Responsive.horizontalPadding(width);

    return ResponsiveCenter(
      child: RefreshIndicator(
        onRefresh: () => _load(query: _search.text.trim()),
        child: ListView(
          key: const Key('customers-screen'),
          physics: const AlwaysScrollableScrollPhysics(),
          padding: EdgeInsets.fromLTRB(padding, 24, padding, 32),
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    'Pelanggan',
                    style: Theme.of(context).textTheme.headlineSmall,
                  ),
                ),
                FilledButton.icon(
                  onPressed: _createCustomer,
                  icon: const Icon(Icons.person_add_alt_1_outlined),
                  label: const Text('Baru'),
                ),
              ],
            ),
            const SizedBox(height: 16),
            TextField(
              key: const Key('customer-search'),
              controller: _search,
              textInputAction: TextInputAction.search,
              decoration: const InputDecoration(
                labelText: 'Cari nama atau nomor telepon',
                prefixIcon: Icon(Icons.search),
              ),
            ),
            const SizedBox(height: 16),
            if (_error != null)
              _ErrorPanel(message: _error!, onRetry: () => _load())
            else if (_loading && _customers.isEmpty)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 48),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (_customers.isEmpty)
              const _EmptyPanel()
            else ...[
              ..._customers.map(
                (customer) => Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: Card(
                    child: ListTile(
                      leading: CircleAvatar(
                        child: Text(
                          customer.name.isEmpty
                              ? '?'
                              : customer.name.substring(0, 1).toUpperCase(),
                        ),
                      ),
                      title: Text(customer.name),
                      subtitle: Text(customer.phone ?? 'Tanpa nomor telepon'),
                      trailing: StatusChip(
                        label: customer.isActive ? 'Aktif' : 'Nonaktif',
                        icon: customer.isActive
                            ? Icons.check_circle_outline
                            : Icons.block_outlined,
                        tone: customer.isActive
                            ? StatusTone.success
                            : StatusTone.danger,
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
    );
  }
}

class _EmptyPanel extends StatelessWidget {
  const _EmptyPanel();

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 48, horizontal: 16),
      child: Column(
        children: [
          Icon(
            Icons.people_outline,
            size: 48,
            color: Theme.of(context).colorScheme.primary,
          ),
          const SizedBox(height: 12),
          Text(
            'Belum ada pelanggan',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 4),
          const Text('Tambahkan pelanggan baru atau ubah kata pencarian.'),
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
    final palette = Theme.of(context).extension<AppStatusColors>();
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: palette?.dangerBackground ?? const Color(0xFFFEF2F2),
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
