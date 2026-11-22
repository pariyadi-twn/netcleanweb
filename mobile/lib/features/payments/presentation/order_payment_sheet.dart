import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:uuid/uuid.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/session/session_controller.dart';
import '../../../core/ui/formatting.dart';
import '../../cash/presentation/cash_screen.dart';
import '../../orders/domain/order_models.dart';

Future<PaymentSummary?> showOrderPaymentSheet({
  required BuildContext context,
  required String invoiceId,
  required String orderNumber,
  required int balanceDueRupiah,
}) {
  return showModalBottomSheet<PaymentSummary>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    backgroundColor: Colors.transparent,
    builder: (context) => OrderPaymentSheet(
      invoiceId: invoiceId,
      orderNumber: orderNumber,
      balanceDueRupiah: balanceDueRupiah,
    ),
  );
}

class OrderPaymentSheet extends ConsumerStatefulWidget {
  const OrderPaymentSheet({
    super.key,
    required this.invoiceId,
    required this.orderNumber,
    required this.balanceDueRupiah,
  });

  final String invoiceId;
  final String orderNumber;
  final int balanceDueRupiah;

  @override
  ConsumerState<OrderPaymentSheet> createState() => _OrderPaymentSheetState();
}

class _OrderPaymentSheetState extends ConsumerState<OrderPaymentSheet> {
  late final TextEditingController _amount;
  late final TextEditingController _cashReceived;
  final _reference = TextEditingController();
  String _method = 'bank_manual';
  bool _qrisConfirmed = false;
  bool _busy = false;
  String? _error;
  String? _commandId;

  @override
  void initState() {
    super.initState();
    _amount = TextEditingController(text: widget.balanceDueRupiah.toString());
    _cashReceived = TextEditingController(
      text: widget.balanceDueRupiah.toString(),
    );
  }

  @override
  void dispose() {
    _amount.dispose();
    _cashReceived.dispose();
    _reference.dispose();
    super.dispose();
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

  Future<void> _submit() async {
    final session = ref.read(sessionControllerProvider).asData?.value;
    final tenantId = session?.tenantId;
    if (tenantId == null) {
      _sayError('Konteks tenant belum tersedia.');
      return;
    }

    final amount = int.tryParse(_amount.text.trim());
    if (amount == null || amount <= 0) {
      _sayError('Nominal pembayaran belum valid.');
      return;
    }

    if (_method == 'qris_manual' && !_qrisConfirmed) {
      _sayError(
        'Konfirmasi bahwa pembayaran QRIS sudah berhasil diverifikasi.',
      );
      return;
    }

    final activeCash = ref.read(activeCashSessionProvider).asData?.value;
    String? cashSessionId;
    int? cashReceived;
    if (_method == 'cash') {
      if (activeCash == null || !activeCash.isOpen) {
        _sayError('Buka shift kas terlebih dahulu untuk pembayaran tunai.');
        return;
      }
      cashSessionId = activeCash.id;
      cashReceived = int.tryParse(_cashReceived.text.trim());
      if (cashReceived == null || cashReceived < amount) {
        _sayError(
          'Uang tunai diterima tidak boleh kurang dari nominal dibayar.',
        );
        return;
      }
    }

    final reference = _reference.text.trim();
    _commandId ??= const Uuid().v4();

    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final payment = await ref
          .read(orderRepositoryProvider)
          .recordPayment(
            tenantId: tenantId,
            invoiceId: widget.invoiceId,
            amountRupiah: amount,
            method: _method,
            commandId: _commandId!,
            reference: reference.isEmpty ? null : reference,
            cashSessionId: cashSessionId,
            cashReceivedRupiah: cashReceived,
          );
      if (!mounted) {
        return;
      }
      if (_method == 'cash') {
        ref.invalidate(cashRegistersProvider);
        ref.invalidate(activeCashSessionProvider);
      }
      Navigator.of(context).pop(payment);
    } on ApiException catch (error) {
      _sayError(_errorMessage(error));
    } catch (_) {
      _sayError('Pembayaran belum dapat dicatat. Coba lagi.');
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  String _errorMessage(ApiException error) {
    if (error.statusCode == 403 || error.code == 'FORBIDDEN') {
      return 'Hanya kasir atau supervisor yang dapat mencatat pembayaran.';
    }
    for (final messages in error.fieldErrors.values) {
      if (messages.isNotEmpty) {
        return messages.first;
      }
    }
    return error.message;
  }

  void _sayError(String message) {
    if (mounted) {
      setState(() => _error = message);
    }
  }

  String get _submitLabel {
    return switch (_method) {
      'cash' => 'Catat pembayaran tunai',
      'qris_manual' => 'Konfirmasi QRIS diterima',
      _ => 'Catat transfer',
    };
  }

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(sessionControllerProvider).asData?.value;
    final canPay = session?.canManagePayments ?? false;
    final cashState = ref.watch(activeCashSessionProvider);
    final cashAvailable = cashState.asData?.value?.isOpen ?? false;
    final amount = int.tryParse(_amount.text.trim());
    final received = int.tryParse(_cashReceived.text.trim());
    final theme = Theme.of(context);

    return Material(
      color: theme.colorScheme.surface,
      borderRadius: const BorderRadius.vertical(top: Radius.circular(26)),
      child: Padding(
        padding: EdgeInsets.only(
          bottom: MediaQuery.viewInsetsOf(context).bottom,
        ),
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(20, 10, 20, 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Center(
                child: Container(
                  width: 44,
                  height: 4,
                  decoration: BoxDecoration(
                    color: theme.colorScheme.outlineVariant,
                    borderRadius: BorderRadius.circular(99),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              Text('Catat pembayaran', style: theme.textTheme.titleLarge),
              const SizedBox(height: 4),
              Text(
                'Order ${widget.orderNumber} · sisa '
                '${formatRupiah(widget.balanceDueRupiah)}',
                style: theme.textTheme.bodyMedium,
              ),
              const SizedBox(height: 18),
              if (!canPay) ...[
                _InfoPanel(
                  icon: Icons.lock_outline,
                  message:
                      'Hanya kasir atau supervisor yang dapat mencatat '
                      'pembayaran. Minta bantuan kasir.',
                ),
                const SizedBox(height: 16),
                OutlinedButton(
                  onPressed: () => Navigator.of(context).pop(),
                  child: const Text('Tutup'),
                ),
              ] else if (widget.balanceDueRupiah <= 0) ...[
                const _InfoPanel(
                  icon: Icons.check_circle_outline,
                  message: 'Invoice sudah lunas.',
                ),
                const SizedBox(height: 16),
                OutlinedButton(
                  onPressed: () => Navigator.of(context).pop(),
                  child: const Text('Tutup'),
                ),
              ] else ...[
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    ChoiceChip(
                      label: const Text('Transfer'),
                      selected: _method == 'bank_manual',
                      onSelected: _busy
                          ? null
                          : (_) => setState(() => _method = 'bank_manual'),
                    ),
                    ChoiceChip(
                      label: const Text('Tunai'),
                      selected: _method == 'cash',
                      onSelected: _busy
                          ? null
                          : (_) => setState(() => _method = 'cash'),
                    ),
                    ChoiceChip(
                      label: const Text('QRIS'),
                      selected: _method == 'qris_manual',
                      onSelected: _busy
                          ? null
                          : (_) => setState(() => _method = 'qris_manual'),
                    ),
                  ],
                ),
                if (_method == 'cash' && !cashAvailable) ...[
                  const SizedBox(height: 12),
                  _InfoPanel(
                    icon: Icons.point_of_sale_outlined,
                    message:
                        'Shift kas belum terbuka. Buka kas untuk menerima '
                        'pembayaran tunai.',
                    actionLabel: 'Buka kas',
                    onAction: _openCash,
                  ),
                ],
                const SizedBox(height: 14),
                TextField(
                  key: const Key('pickup-payment-amount'),
                  controller: _amount,
                  keyboardType: TextInputType.number,
                  inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                  onChanged: (_) => setState(() {}),
                  decoration: const InputDecoration(
                    labelText: 'Nominal dibayar',
                  ),
                ),
                if (_method == 'cash') ...[
                  const SizedBox(height: 12),
                  TextField(
                    key: const Key('pickup-cash-received'),
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
                        style: theme.textTheme.bodyMedium,
                      ),
                    ),
                ],
                if (_method != 'cash') ...[
                  const SizedBox(height: 12),
                  TextField(
                    controller: _reference,
                    enabled: !_busy,
                    decoration: InputDecoration(
                      labelText: _method == 'qris_manual'
                          ? 'ID transaksi QRIS (opsional)'
                          : 'Referensi transfer (opsional)',
                    ),
                  ),
                ],
                if (_method == 'qris_manual') ...[
                  const SizedBox(height: 14),
                  const _InfoPanel(
                    icon: Icons.qr_code_2_rounded,
                    message:
                        'Tunjukkan standee QRIS outlet kepada pelanggan. '
                        'Pastikan notifikasi pembayaran QRIS berhasil sebelum '
                        'menekan konfirmasi.',
                  ),
                  CheckboxListTile(
                    contentPadding: EdgeInsets.zero,
                    value: _qrisConfirmed,
                    onChanged: _busy
                        ? null
                        : (value) =>
                              setState(() => _qrisConfirmed = value ?? false),
                    controlAffinity: ListTileControlAffinity.leading,
                    title: const Text(
                      'Saya sudah memverifikasi pembayaran QRIS berhasil',
                    ),
                  ),
                ],
                if (_error != null) ...[
                  const SizedBox(height: 12),
                  _ErrorPanel(message: _error!),
                ],
                const SizedBox(height: 16),
                FilledButton(
                  onPressed: _busy ? null : _submit,
                  child: _busy
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : Text(_submitLabel),
                ),
                const SizedBox(height: 6),
                TextButton(
                  onPressed: _busy ? null : () => Navigator.of(context).pop(),
                  child: const Text('Batal'),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _InfoPanel extends StatelessWidget {
  const _InfoPanel({
    required this.icon,
    required this.message,
    this.actionLabel,
    this.onAction,
  });

  final IconData icon;
  final String message;
  final String? actionLabel;
  final Future<void> Function()? onAction;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: theme.colorScheme.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 20),
          const SizedBox(width: 10),
          Expanded(child: Text(message)),
          if (actionLabel != null && onAction != null) ...[
            const SizedBox(width: 8),
            TextButton(onPressed: onAction, child: Text(actionLabel!)),
          ],
        ],
      ),
    );
  }
}

class _ErrorPanel extends StatelessWidget {
  const _ErrorPanel({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: scheme.errorContainer,
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.error_outline, color: scheme.error, size: 19),
          const SizedBox(width: 8),
          Expanded(
            child: Text(message, style: TextStyle(color: scheme.error)),
          ),
        ],
      ),
    );
  }
}
