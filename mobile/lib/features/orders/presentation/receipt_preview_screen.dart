import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/printer/esc_pos_receipt_builder.dart';
import '../../../core/printer/printer_models.dart';
import '../../../core/providers.dart';
import '../../../core/session/session_controller.dart';
import '../../../core/ui/formatting.dart';
import '../../../core/ui/responsive.dart';
import '../../../core/ui/theme.dart';
import '../../printer/presentation/printer_settings_screen.dart';
import '../domain/order_models.dart';

class ReceiptPreviewScreen extends ConsumerStatefulWidget {
  const ReceiptPreviewScreen({super.key, required this.receipt});

  final ReceiptSummary receipt;

  @override
  ConsumerState<ReceiptPreviewScreen> createState() =>
      _ReceiptPreviewScreenState();
}

class _ReceiptPreviewScreenState extends ConsumerState<ReceiptPreviewScreen> {
  PaperWidth _paperWidth = PaperWidth.mm80;
  PrinterConfig? _printerConfig;
  bool _printing = false;

  @override
  void initState() {
    super.initState();
    unawaited(_loadPrinter());
  }

  Future<void> _loadPrinter() async {
    final config = await ref.read(printerStoreProvider).read();
    if (!mounted) {
      return;
    }
    setState(() {
      _printerConfig = config;
      _paperWidth = config?.paperWidth ?? _paperWidth;
    });
  }

  Future<void> _openSettings() async {
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (context) => const PrinterSettingsScreen(),
      ),
    );
    await _loadPrinter();
  }

  Future<void> _recordAttempt(String outcome, {String? errorCode}) async {
    final tenantId = ref
        .read(sessionControllerProvider)
        .asData
        ?.value
        ?.tenantId;
    final orderId = widget.receipt.orderId;
    if (tenantId == null || orderId.isEmpty) {
      return;
    }
    await ref
        .read(orderRepositoryProvider)
        .recordPrintAttempt(
          tenantId: tenantId,
          orderId: orderId,
          outcome: outcome,
          errorCode: errorCode,
        );
  }

  Future<void> _print() async {
    final config = _printerConfig;
    if (config == null) {
      await _openSettings();
      return;
    }

    setState(() => _printing = true);
    try {
      final service = ref.read(printerServiceProvider);
      final bytes = await EscPosReceiptBuilder().build(
        widget.receipt,
        _paperWidth,
      );
      await service.connect(config.device);
      await service.printBytes(bytes);
      try {
        await _recordAttempt('printed');
        _say('Nota tercetak.');
      } catch (_) {
        _say('Nota tercetak, tetapi catatan cetak gagal disimpan.');
      }
    } on PrinterException catch (error) {
      try {
        await _recordAttempt('failed', errorCode: error.code);
      } catch (_) {
        // The print attempt is already reported to the operator.
      }
      _say(error.message);
    } catch (_) {
      try {
        await _recordAttempt('failed', errorCode: 'write_failed');
      } catch (_) {
        // The print attempt is already reported to the operator.
      }
      _say('Cetak nota gagal. Periksa printer lalu coba lagi.');
    } finally {
      if (mounted) {
        setState(() => _printing = false);
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
    final padding = Responsive.horizontalPadding(
      MediaQuery.sizeOf(context).width,
    );

    return Scaffold(
      appBar: AppBar(title: const Text('Pratinjau nota')),
      body: DecoratedBox(
        decoration: const BoxDecoration(gradient: AppTheme.canvasGradient),
        child: SafeArea(
          top: false,
          child: Column(
            children: [
              Padding(
                padding: EdgeInsets.fromLTRB(padding, 14, padding, 8),
                child: Row(
                  children: [
                    const Icon(Icons.visibility_outlined, size: 18),
                    const SizedBox(width: 8),
                    const Expanded(child: Text('Pratinjau sebelum cetak')),
                    SegmentedButton<PaperWidth>(
                      segments: const [
                        ButtonSegment(
                          value: PaperWidth.mm80,
                          label: Text('80'),
                        ),
                        ButtonSegment(
                          value: PaperWidth.mm58,
                          label: Text('58'),
                        ),
                      ],
                      selected: {_paperWidth},
                      onSelectionChanged: (selection) {
                        setState(() => _paperWidth = selection.first);
                      },
                    ),
                  ],
                ),
              ),
              if (_printing) const LinearProgressIndicator(),
              Expanded(
                child: SingleChildScrollView(
                  padding: EdgeInsets.fromLTRB(padding, 10, padding, 24),
                  child: Center(
                    child: _PaperReceipt(
                      receipt: widget.receipt,
                      paperWidth: _paperWidth,
                    ),
                  ),
                ),
              ),
              _PrintBar(
                config: _printerConfig,
                paperWidth: _paperWidth,
                printing: _printing,
                onSettings: _openSettings,
                onPrint: _print,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _PaperReceipt extends StatelessWidget {
  const _PaperReceipt({required this.receipt, required this.paperWidth});

  final ReceiptSummary receipt;
  final PaperWidth paperWidth;

  @override
  Widget build(BuildContext context) {
    const smallStyle = TextStyle(
      fontFamily: 'monospace',
      fontSize: 10.5,
      height: 1.35,
      color: AppTheme.text,
    );
    final boldStyle = smallStyle.copyWith(fontWeight: FontWeight.w700);

    return Container(
      width: paperWidth.previewWidth,
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.12),
            blurRadius: 22,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      padding: const EdgeInsets.fromLTRB(16, 18, 16, 22),
      child: DefaultTextStyle.merge(
        style: smallStyle,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              receipt.branchName,
              textAlign: TextAlign.center,
              style: boldStyle.copyWith(fontSize: 13),
            ),
            if (receipt.branchAddress != null)
              Text(receipt.branchAddress!, textAlign: TextAlign.center),
            if (receipt.branchPhone != null)
              Text(receipt.branchPhone!, textAlign: TextAlign.center),
            const SizedBox(height: 8),
            const _DashedLine(),
            Text(
              'NOTA ${receipt.orderNumber}',
              textAlign: TextAlign.center,
              style: boldStyle,
            ),
            Text(
              'Invoice ${receipt.invoiceNumber}',
              textAlign: TextAlign.center,
            ),
            Text(
              receipt.paymentStatusLabel,
              textAlign: TextAlign.center,
              style: boldStyle,
            ),
            const _DashedLine(),
            _ValueRow(label: 'Pelanggan', value: receipt.customerName),
            _ValueRow(label: 'Selesai', value: formatDateTime(receipt.dueAt)),
            if (receipt.acceptedAt != null)
              _ValueRow(
                label: 'Diterima',
                value: formatDateTime(receipt.acceptedAt!),
              ),
            if (receipt.status.isNotEmpty)
              _ValueRow(label: 'Status', value: receipt.status),
            const _DashedLine(),
            ...receipt.lines.map(
              (line) => Padding(
                padding: const EdgeInsets.only(bottom: 6),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(line.serviceName, style: boldStyle),
                    _ValueRow(
                      label: [
                        if (line.billableQuantity.isNotEmpty)
                          line.billableQuantity,
                        if (line.unit.isNotEmpty) line.unit,
                      ].join(' '),
                      value: formatRupiah(line.lineTotalRupiah),
                    ),
                    if (line.unitPriceRupiah > 0)
                      Text(
                        '@ ${formatRupiah(line.unitPriceRupiah)}',
                        textAlign: TextAlign.right,
                      ),
                  ],
                ),
              ),
            ),
            const _DashedLine(),
            if (receipt.serviceSubtotalRupiah > 0)
              _ValueRow(
                label: 'Subtotal',
                value: formatRupiah(receipt.serviceSubtotalRupiah),
              ),
            if (receipt.discountRupiah > 0)
              _ValueRow(
                label: 'Diskon',
                value: '-${formatRupiah(receipt.discountRupiah)}',
              ),
            if (receipt.expressSurchargeRupiah > 0)
              _ValueRow(
                label: 'Express',
                value: formatRupiah(receipt.expressSurchargeRupiah),
              ),
            if (receipt.deliveryFeeRupiah > 0)
              _ValueRow(
                label: 'Antar',
                value: formatRupiah(receipt.deliveryFeeRupiah),
              ),
            _ValueRow(
              label: 'Total',
              value: formatRupiah(receipt.totalRupiah),
              bold: true,
            ),
            _ValueRow(
              label: 'Dibayar',
              value: formatRupiah(receipt.paidRupiah),
            ),
            _ValueRow(
              label: 'Sisa',
              value: formatRupiah(receipt.balanceDueRupiah),
              bold: receipt.balanceDueRupiah > 0,
            ),
            const _DashedLine(),
            Text(
              'Simpan nota ini. Pembayaran transfer manual diverifikasi oleh kasir.',
              textAlign: TextAlign.center,
            ),
            if (receipt.receiptHash != null && receipt.receiptHash!.isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(top: 6),
                child: Text(
                  'Ref: ${receipt.receiptHash!.length > 12 ? receipt.receiptHash!.substring(0, 12) : receipt.receiptHash!}',
                  textAlign: TextAlign.center,
                  style: smallStyle.copyWith(color: AppTheme.textSecondary),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _ValueRow extends StatelessWidget {
  const _ValueRow({
    required this.label,
    required this.value,
    this.bold = false,
  });

  final String label;
  final String value;
  final bool bold;

  @override
  Widget build(BuildContext context) {
    final style = TextStyle(
      fontFamily: 'monospace',
      fontSize: 10.5,
      height: 1.35,
      fontWeight: bold ? FontWeight.w700 : FontWeight.w400,
    );
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 1.5),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(child: Text(label, style: style)),
          const SizedBox(width: 8),
          Text(value, textAlign: TextAlign.end, style: style),
        ],
      ),
    );
  }
}

class _DashedLine extends StatelessWidget {
  const _DashedLine();

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 7),
      child: LayoutBuilder(
        builder: (context, constraints) {
          const dash = 4.0;
          const gap = 3.0;
          final count = (constraints.maxWidth / (dash + gap)).floor();
          return Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: List.generate(
              count,
              (_) => Container(width: dash, height: 1, color: AppTheme.divider),
            ),
          );
        },
      ),
    );
  }
}

class _PrintBar extends StatelessWidget {
  const _PrintBar({
    required this.config,
    required this.paperWidth,
    required this.printing,
    required this.onSettings,
    required this.onPrint,
  });

  final PrinterConfig? config;
  final PaperWidth paperWidth;
  final bool printing;
  final Future<void> Function() onSettings;
  final Future<void> Function() onPrint;

  @override
  Widget build(BuildContext context) {
    return Material(
      elevation: 10,
      color: AppTheme.surface,
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      config?.device.name ?? 'Printer belum diatur',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontWeight: FontWeight.w700),
                    ),
                    Text(
                      '${paperWidth.label} • ${config == null ? 'Pilih printer' : 'Siap mencetak'}',
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 10),
              OutlinedButton(
                onPressed: printing ? null : onSettings,
                child: const Text('Atur'),
              ),
              const SizedBox(width: 8),
              FilledButton.icon(
                onPressed: printing ? null : onPrint,
                icon: const Icon(Icons.print_outlined, size: 18),
                label: Text(printing ? 'Mencetak...' : 'Cetak'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
