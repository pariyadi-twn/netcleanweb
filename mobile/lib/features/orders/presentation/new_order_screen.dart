import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:uuid/uuid.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/session/session_controller.dart';
import '../../../core/ui/formatting.dart';
import '../../../core/ui/responsive.dart';
import '../../../core/ui/theme.dart';
import '../../cash/presentation/cash_screen.dart';
import '../../customers/domain/customer_summary.dart';
import '../../customers/presentation/customer_form_dialog.dart';
import '../domain/order_models.dart';
import 'receipt_screen.dart';

class NewOrderScreen extends ConsumerStatefulWidget {
  const NewOrderScreen({super.key});

  @override
  ConsumerState<NewOrderScreen> createState() => _NewOrderScreenState();
}

class _NewOrderScreenState extends ConsumerState<NewOrderScreen> {
  final _customerQuery = TextEditingController();
  final _paymentAmount = TextEditingController();
  final _cashReceived = TextEditingController();
  String _paymentMethod = 'bank_manual';
  final _quantities = <String, TextEditingController>{};

  Timer? _customerDebounce;
  BranchInfo? _branch;
  List<ServiceSummary> _services = const [];
  List<CustomerSummary> _customerResults = const [];
  CustomerSummary? _customer;
  QuoteSummary? _quote;
  OrderSummary? _order;
  PaymentSummary? _payment;
  bool _loading = true;
  bool _searchingCustomers = false;
  bool _busy = false;
  String? _loadError;
  String? _customerError;
  String? _acceptCommandId;
  String? _paymentCommandId;
  int _customerRequestId = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  @override
  void dispose() {
    _customerDebounce?.cancel();
    _customerQuery.dispose();
    _paymentAmount.dispose();
    _cashReceived.dispose();
    for (final controller in _quantities.values) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _load() async {
    final session = ref.read(sessionControllerProvider).asData?.value;
    final tenantId = session?.tenantId;
    final branchId = session?.branchIds.isEmpty ?? true
        ? null
        : session!.branchIds.first;
    if (tenantId == null || branchId == null) {
      setState(() {
        _loading = false;
        _loadError = 'Cabang belum ditugaskan ke akun ini.';
      });
      return;
    }

    setState(() {
      _loading = true;
      _loadError = null;
    });

    try {
      final repository = ref.read(orderRepositoryProvider);
      final branch = await repository.branch(
        tenantId: tenantId,
        branchId: branchId,
      );
      final services = await repository.services(tenantId: tenantId);
      if (!mounted) {
        return;
      }
      for (final controller in _quantities.values) {
        controller.dispose();
      }
      _quantities
        ..clear()
        ..addEntries(
          services.map((service) {
            final controller = TextEditingController();
            controller.addListener(_invalidatePricing);
            return MapEntry(service.id, controller);
          }),
        );
      setState(() {
        _branch = branch;
        _services = services;
        _loading = false;
      });
    } on ApiException catch (error) {
      if (mounted) {
        setState(() {
          _loading = false;
          _loadError = _errorMessage(error);
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _loading = false;
          _loadError = 'Data cabang dan layanan belum dapat dimuat.';
        });
      }
    }
  }

  void _invalidatePricing() {
    if (_quote == null && _order == null && _payment == null) {
      return;
    }
    setState(() {
      _quote = null;
      _order = null;
      _payment = null;
      _acceptCommandId = null;
      _paymentCommandId = null;
      _paymentAmount.clear();
      _cashReceived.clear();
      _paymentMethod = 'bank_manual';
    });
  }

  void _onCustomerQueryChanged(String value) {
    _customerDebounce?.cancel();
    final query = value.trim();
    if (query.isEmpty) {
      _customerRequestId++;
      setState(() {
        _customerResults = const [];
        _customerError = null;
        _searchingCustomers = false;
      });
      return;
    }
    _customerDebounce = Timer(const Duration(milliseconds: 300), () {
      _searchCustomers(query);
    });
  }

  Future<void> _searchCustomers(String query) async {
    final tenantId = ref
        .read(sessionControllerProvider)
        .asData
        ?.value
        ?.tenantId;
    if (tenantId == null) {
      return;
    }
    final requestId = ++_customerRequestId;
    setState(() {
      _searchingCustomers = true;
      _customerError = null;
    });
    try {
      final page = await ref
          .read(customerRepositoryProvider)
          .search(tenantId: tenantId, query: query);
      if (!mounted || requestId != _customerRequestId) {
        return;
      }
      setState(() {
        _customerResults = page.items.take(5).toList(growable: false);
        _searchingCustomers = false;
      });
    } on ApiException catch (error) {
      if (!mounted || requestId != _customerRequestId) {
        return;
      }
      setState(() {
        _searchingCustomers = false;
        _customerError = _errorMessage(error);
      });
    } catch (_) {
      if (!mounted || requestId != _customerRequestId) {
        return;
      }
      setState(() {
        _searchingCustomers = false;
        _customerError = 'Pencarian pelanggan gagal.';
      });
    }
  }

  void _selectCustomer(CustomerSummary customer) {
    _invalidatePricing();
    setState(() {
      _customer = customer;
      _customerResults = const [];
      _customerQuery.clear();
    });
  }

  void _clearCustomer() {
    _invalidatePricing();
    setState(() => _customer = null);
  }

  Future<void> _createCustomer() async {
    final tenantId = ref
        .read(sessionControllerProvider)
        .asData
        ?.value
        ?.tenantId;
    if (tenantId == null) {
      return;
    }
    final draft = await showCustomerFormDialog(context);
    if (draft == null || !mounted) {
      return;
    }
    setState(() => _busy = true);
    try {
      final customer = await ref
          .read(customerRepositoryProvider)
          .create(tenantId: tenantId, name: draft.name, phone: draft.phone);
      if (!mounted) {
        return;
      }
      _selectCustomer(customer);
    } on ApiException catch (error) {
      _say(_errorMessage(error));
    } catch (_) {
      _say('Pelanggan belum dapat disimpan.');
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  List<OrderLineDraft>? _selectedLines() {
    final lines = <OrderLineDraft>[];
    for (final service in _services) {
      final text = _quantities[service.id]?.text.trim() ?? '';
      if (text.isEmpty) {
        continue;
      }
      final quantity = double.tryParse(text.replaceAll(',', '.'));
      if (quantity == null || quantity <= 0) {
        _say('Jumlah ${service.name} belum valid.');
        return null;
      }
      lines.add(
        OrderLineDraft(service: service, quantity: text.replaceAll(',', '.')),
      );
    }
    if (lines.isEmpty) {
      _say('Tambahkan jumlah pada minimal satu layanan.');
      return null;
    }
    return lines;
  }

  Future<void> _createQuote() async {
    final session = ref.read(sessionControllerProvider).asData?.value;
    final tenantId = session?.tenantId;
    final branch = _branch;
    if (tenantId == null || branch == null) {
      return;
    }
    final lines = _selectedLines();
    if (lines == null) {
      return;
    }

    setState(() {
      _busy = true;
      _quote = null;
      _acceptCommandId = null;
    });
    try {
      final quote = await ref
          .read(orderRepositoryProvider)
          .createQuote(
            tenantId: tenantId,
            branchId: branch.id,
            customerId: _customer?.id,
            lines: lines,
          );
      if (!mounted) {
        return;
      }
      setState(() => _quote = quote);
    } on ApiException catch (error) {
      _say(_errorMessage(error));
    } catch (_) {
      _say('Harga belum dapat dihitung. Periksa koneksi dan coba lagi.');
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _acceptOrder() async {
    final session = ref.read(sessionControllerProvider).asData?.value;
    final tenantId = session?.tenantId;
    final quote = _quote;
    final branch = _branch;
    if (tenantId == null || quote == null || branch == null) {
      return;
    }
    if (quote.expiresAt.isBefore(DateTime.now())) {
      _say('Quote sudah kedaluwarsa. Hitung ulang pesanan.');
      setState(() => _quote = null);
      return;
    }
    final lines = _selectedLines();
    if (lines == null) {
      return;
    }
    _acceptCommandId ??= const Uuid().v4();

    setState(() => _busy = true);
    try {
      final order = await ref
          .read(orderRepositoryProvider)
          .acceptOrder(
            tenantId: tenantId,
            branchId: branch.id,
            quoteId: quote.id,
            customerId: _customer?.id,
            lines: lines,
            commandId: _acceptCommandId!,
          );
      if (!mounted) {
        return;
      }
      _paymentAmount.text = order.balanceDueRupiah.toString();
      _cashReceived.text = order.balanceDueRupiah.toString();
      setState(() {
        _order = order;
        _quote = null;
        _acceptCommandId = null;
        _paymentCommandId = null;
        _payment = null;
      });
    } on ApiException catch (error) {
      _say(_errorMessage(error));
    } catch (_) {
      _say('Pesanan belum dapat dikonfirmasi.');
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _recordPayment() async {
    final session = ref.read(sessionControllerProvider).asData?.value;
    final tenantId = session?.tenantId;
    final order = _order;
    if (tenantId == null || order == null) {
      return;
    }
    final amount = int.tryParse(_paymentAmount.text.trim());
    if (amount == null || amount <= 0) {
      _say('Nominal pembayaran belum valid.');
      return;
    }

    final activeCash = ref.read(activeCashSessionProvider).asData?.value;
    final method = _paymentMethod == 'cash' && (activeCash?.isOpen ?? false)
        ? 'cash'
        : 'bank_manual';
    String? cashSessionId;
    int? cashReceived;
    if (method == 'cash') {
      cashSessionId = activeCash!.id;
      cashReceived = int.tryParse(_cashReceived.text.trim());
      if (cashReceived == null || cashReceived < amount) {
        _say('Uang tunai diterima tidak boleh kurang dari nominal dibayar.');
        return;
      }
    }

    _paymentCommandId ??= const Uuid().v4();

    setState(() => _busy = true);
    try {
      final payment = await ref
          .read(orderRepositoryProvider)
          .recordPayment(
            tenantId: tenantId,
            invoiceId: order.invoiceId,
            amountRupiah: amount,
            method: method,
            cashSessionId: cashSessionId,
            cashReceivedRupiah: cashReceived,
            commandId: _paymentCommandId!,
          );
      if (!mounted) {
        return;
      }
      _paymentAmount.text = payment.balanceDueRupiah > 0
          ? payment.balanceDueRupiah.toString()
          : '';
      _cashReceived.text = payment.balanceDueRupiah > 0
          ? payment.balanceDueRupiah.toString()
          : '';
      setState(() {
        _payment = payment;
        _order = order.copyWith(
          paidRupiah: payment.paidRupiah,
          balanceDueRupiah: payment.balanceDueRupiah,
        );
        _paymentCommandId = null;
      });
      if (method == 'cash') {
        ref.invalidate(cashRegistersProvider);
        ref.invalidate(activeCashSessionProvider);
      }
      _say(
        payment.balanceDueRupiah > 0
            ? 'Pembayaran tercatat. Sisa ${formatRupiah(payment.balanceDueRupiah)}.'
            : 'Pembayaran tercatat. Invoice lunas.',
      );
    } on ApiException catch (error) {
      _say(_errorMessage(error));
    } catch (_) {
      _say('Pembayaran belum dapat dicatat.');
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _openReceipt() async {
    final tenantId = ref
        .read(sessionControllerProvider)
        .asData
        ?.value
        ?.tenantId;
    final order = _order;
    if (tenantId == null || order == null) {
      return;
    }
    setState(() => _busy = true);
    try {
      final receipt = await ref
          .read(orderRepositoryProvider)
          .receipt(tenantId: tenantId, orderId: order.orderId);
      if (!mounted) {
        return;
      }
      await Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (context) => ReceiptScreen(receipt: receipt),
        ),
      );
      if (mounted) {
        _resetOrder();
      }
    } on ApiException catch (error) {
      _say(_errorMessage(error));
    } catch (_) {
      _say('Nota belum dapat dimuat.');
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  void _resetOrder() {
    for (final controller in _quantities.values) {
      controller.clear();
    }
    _paymentAmount.clear();
    _cashReceived.clear();
    setState(() {
      _quote = null;
      _order = null;
      _payment = null;
      _customer = null;
      _customerResults = const [];
      _acceptCommandId = null;
      _paymentCommandId = null;
      _paymentMethod = 'bank_manual';
    });
  }

  void _say(String message) {
    if (!mounted) {
      return;
    }
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  String _errorMessage(ApiException error) {
    for (final messages in error.fieldErrors.values) {
      if (messages.isNotEmpty) {
        return messages.first;
      }
    }
    return error.message;
  }

  String _initial(String name) {
    if (name.isEmpty) {
      return '?';
    }
    return name.substring(0, 1).toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_loadError != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(_loadError!, textAlign: TextAlign.center),
              const SizedBox(height: 12),
              FilledButton.tonal(
                onPressed: _load,
                child: const Text('Coba lagi'),
              ),
            ],
          ),
        ),
      );
    }

    final width = MediaQuery.sizeOf(context).width;
    final padding = Responsive.horizontalPadding(width);

    return ResponsiveCenter(
      child: ListView(
        key: const Key('new-order-screen'),
        padding: EdgeInsets.fromLTRB(padding, 24, padding, 32),
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      _order == null ? 'Pesanan baru' : 'Pesanan tersimpan',
                      style: Theme.of(context).textTheme.headlineSmall,
                    ),
                    const SizedBox(height: 4),
                    Text(
                      _branch == null
                          ? 'Cabang belum tersedia'
                          : '${_branch!.name} • ${_branch!.timezone}',
                    ),
                  ],
                ),
              ),
              if (_order != null)
                TextButton(
                  onPressed: _busy ? null : _resetOrder,
                  child: const Text('Pesanan berikutnya'),
                ),
            ],
          ),
          if (_busy) ...[
            const SizedBox(height: 12),
            const LinearProgressIndicator(),
          ],
          const SizedBox(height: 16),
          if (_order == null) ...[
            _customerSection(context),
            const SizedBox(height: 16),
            _servicesSection(context),
            const SizedBox(height: 16),
            if (_quote != null) _quoteSection(context),
            if (_quote == null)
              FilledButton.icon(
                onPressed: _busy ? null : _createQuote,
                icon: const Icon(Icons.calculate_outlined),
                label: const Text('Hitung pesanan'),
              ),
          ] else
            _orderSection(context),
        ],
      ),
    );
  }

  Widget _customerSection(BuildContext context) {
    final customer = _customer;
    return _Panel(
      title: 'Pelanggan',
      child: customer != null
          ? ListTile(
              contentPadding: EdgeInsets.zero,
              leading: CircleAvatar(child: Text(_initial(customer.name))),
              title: Text(customer.name),
              subtitle: Text(customer.phone ?? 'Tanpa nomor telepon'),
              trailing: TextButton(
                onPressed: _busy ? null : _clearCustomer,
                child: const Text('Ganti'),
              ),
            )
          : Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                TextField(
                  key: const Key('order-customer-search'),
                  controller: _customerQuery,
                  onChanged: _onCustomerQueryChanged,
                  decoration: InputDecoration(
                    labelText: 'Cari pelanggan (opsional)',
                    helperText: 'Kosongkan untuk walk-in.',
                    prefixIcon: const Icon(Icons.search),
                    suffixIcon: _searchingCustomers
                        ? const Padding(
                            padding: EdgeInsets.all(12),
                            child: SizedBox(
                              width: 20,
                              height: 20,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            ),
                          )
                        : null,
                  ),
                ),
                if (_customerError != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 8),
                    child: Text(
                      _customerError!,
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.error,
                      ),
                    ),
                  ),
                ..._customerResults.map(
                  (result) => ListTile(
                    contentPadding: EdgeInsets.zero,
                    title: Text(result.name),
                    subtitle: Text(result.phone ?? 'Tanpa nomor telepon'),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => _selectCustomer(result),
                  ),
                ),
                Align(
                  alignment: Alignment.centerLeft,
                  child: TextButton.icon(
                    onPressed: _busy ? null : _createCustomer,
                    icon: const Icon(Icons.person_add_alt_1_outlined),
                    label: const Text('Pelanggan baru'),
                  ),
                ),
              ],
            ),
    );
  }

  Widget _servicesSection(BuildContext context) {
    return _Panel(
      title: 'Layanan',
      child: _services.isEmpty
          ? const Padding(
              padding: EdgeInsets.symmetric(vertical: 12),
              child: Text(
                'Belum ada layanan aktif. Hubungi admin untuk mengaktifkan '
                'katalog.',
              ),
            )
          : Column(
              children: _services.map((service) {
                final controller = _quantities[service.id];
                return Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              service.name,
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                            const SizedBox(height: 2),
                            Text(
                              'per ${service.unit} • min '
                              '${formatQuantity(service.minimumQuantity)} '
                              '${service.unit} • step '
                              '${formatQuantity(service.quantityStep)}',
                              style: Theme.of(context).textTheme.bodyMedium,
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 12),
                      SizedBox(
                        width: 112,
                        child: TextField(
                          key: Key('quantity-${service.id}'),
                          controller: controller,
                          enabled: !_busy,
                          keyboardType: const TextInputType.numberWithOptions(
                            decimal: true,
                          ),
                          inputFormatters: [
                            FilteringTextInputFormatter.allow(
                              RegExp(r'[0-9.,]'),
                            ),
                          ],
                          decoration: InputDecoration(
                            labelText: service.unit,
                            isDense: true,
                          ),
                        ),
                      ),
                    ],
                  ),
                );
              }).toList(),
            ),
    );
  }

  Widget _quoteSection(BuildContext context) {
    final quote = _quote!;
    final expired = quote.expiresAt.isBefore(DateTime.now());
    return _Panel(
      title: 'Ringkasan harga',
      child: Column(
        children: [
          ...quote.lines.map(
            (line) => Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      '${line.serviceName} • '
                      '${formatQuantity(line.billableQuantity)} ${line.unit}',
                    ),
                  ),
                  const SizedBox(width: 12),
                  Text(formatRupiah(line.lineTotalRupiah)),
                ],
              ),
            ),
          ),
          const Divider(),
          _AmountRow(label: 'Total', amount: quote.totalRupiah, strong: true),
          _AmountRow(
            label: 'Estimasi selesai',
            value: formatDateTime(quote.dueAt),
          ),
          _AmountRow(
            label: 'Berlaku hingga',
            value: formatDateTime(quote.expiresAt),
          ),
          const SizedBox(height: 16),
          if (expired)
            const Text(
              'Quote kedaluwarsa. Hitung ulang sebelum mengonfirmasi.',
              style: TextStyle(color: Color(0xFFB91C1C)),
            )
          else
            SizedBox(
              width: double.infinity,
              child: FilledButton(
                onPressed: _busy ? null : _acceptOrder,
                child: const Text('Konfirmasi pesanan'),
              ),
            ),
        ],
      ),
    );
  }

  Widget _orderSection(BuildContext context) {
    final order = _order!;
    final paid = order.balanceDueRupiah <= 0;
    final cashState = ref.watch(activeCashSessionProvider);
    final cashSession = cashState.asData?.value;
    final cashAvailable = cashSession?.isOpen ?? false;
    final method = cashAvailable ? _paymentMethod : 'bank_manual';
    final amount = int.tryParse(_paymentAmount.text.trim());
    final received = int.tryParse(_cashReceived.text.trim());

    return Column(
      children: [
        _Panel(
          title: 'Pesanan ${order.orderNumber}',
          child: Column(
            children: [
              _AmountRow(label: 'Invoice', value: order.invoiceNumber),
              _AmountRow(
                label: 'Total',
                amount: order.totalRupiah,
                strong: true,
              ),
              _AmountRow(label: 'Dibayar', amount: order.paidRupiah),
              _AmountRow(
                label: 'Sisa',
                amount: order.balanceDueRupiah,
                strong: order.balanceDueRupiah > 0,
              ),
              _AmountRow(
                label: 'Estimasi selesai',
                value: formatDateTime(order.dueAt),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        _Panel(
          title: paid ? 'Pembayaran lunas' : 'Catat pembayaran',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              if (!paid) ...[
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    ChoiceChip(
                      label: const Text('Transfer'),
                      selected: method == 'bank_manual',
                      onSelected: _busy
                          ? null
                          : (_) => _selectPaymentMethod('bank_manual'),
                    ),
                    ChoiceChip(
                      label: const Text('Tunai'),
                      selected: method == 'cash',
                      onSelected: _busy
                          ? null
                          : cashAvailable
                          ? (_) => _selectPaymentMethod('cash')
                          : null,
                    ),
                  ],
                ),
                if (!cashAvailable)
                  Padding(
                    padding: const EdgeInsets.only(top: 8),
                    child: Row(
                      children: [
                        const Expanded(
                          child: Text(
                            'Buka shift kas untuk menerima pembayaran tunai.',
                          ),
                        ),
                        TextButton(
                          onPressed: _busy ? null : _openCash,
                          child: const Text('Buka kas'),
                        ),
                      ],
                    ),
                  ),
                const SizedBox(height: 12),
                TextField(
                  key: const Key('payment-amount'),
                  controller: _paymentAmount,
                  keyboardType: TextInputType.number,
                  inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                  onChanged: (_) => setState(() {}),
                  decoration: InputDecoration(
                    labelText: method == 'cash'
                        ? 'Nominal dibayar'
                        : 'Nominal transfer',
                  ),
                ),
                if (method == 'cash') ...[
                  const SizedBox(height: 12),
                  TextField(
                    key: const Key('cash-received'),
                    controller: _cashReceived,
                    keyboardType: TextInputType.number,
                    inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                    onChanged: (_) => setState(() {}),
                    decoration: const InputDecoration(
                      labelText: 'Uang tunai diterima',
                      helperText: 'Kembalian dihitung dari nominal dibayar.',
                    ),
                  ),
                  if (amount != null && received != null && received >= amount)
                    Padding(
                      padding: const EdgeInsets.only(top: 8),
                      child: Text(
                        'Kembalian ${formatRupiah(received - amount)}',
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                    ),
                ],
                const SizedBox(height: 12),
                FilledButton(
                  onPressed: _busy ? null : _recordPayment,
                  child: Text(
                    method == 'cash'
                        ? 'Catat pembayaran tunai'
                        : 'Catat transfer',
                  ),
                ),
              ] else
                const Text('Invoice sudah lunas. Nota dapat diberikan.'),
              if (_payment != null) ...[
                const SizedBox(height: 12),
                Text(
                  'Status invoice: ${_payment!.invoiceStatus}',
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
              ],
              const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: _busy ? null : _openReceipt,
                icon: const Icon(Icons.receipt_long_outlined),
                label: const Text('Lihat nota'),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Future<void> _openCash() async {
    await Navigator.of(
      context,
    ).push(MaterialPageRoute<void>(builder: (context) => const CashScreen()));
    ref.invalidate(cashRegistersProvider);
    ref.invalidate(activeCashSessionProvider);
    if (mounted) {
      setState(() {});
    }
  }

  void _selectPaymentMethod(String method) {
    if (method == 'cash') {
      final active = ref.read(activeCashSessionProvider).asData?.value;
      if (active == null || !active.isOpen) {
        _say('Buka shift kas terlebih dahulu.');
        return;
      }
      if (_cashReceived.text.trim().isEmpty) {
        _cashReceived.text = _paymentAmount.text;
      }
    }
    setState(() => _paymentMethod = method);
  }
}

class _Panel extends StatelessWidget {
  const _Panel({required this.title, required this.child});

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 12),
            child,
          ],
        ),
      ),
    );
  }
}

class _AmountRow extends StatelessWidget {
  const _AmountRow({
    required this.label,
    this.amount,
    this.value,
    this.strong = false,
  }) : assert(amount != null || value != null);

  final String label;
  final int? amount;
  final String? value;
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
          const SizedBox(width: 12),
          Text(
            amount == null ? value! : formatRupiah(amount!),
            style: style,
            textAlign: TextAlign.end,
          ),
        ],
      ),
    );
  }
}
