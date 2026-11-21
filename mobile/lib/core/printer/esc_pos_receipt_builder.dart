import 'package:esc_pos_utils_plus/esc_pos_utils_plus.dart';

import '../../features/orders/domain/order_models.dart';
import '../ui/formatting.dart';
import 'printer_models.dart';

class EscPosReceiptBuilder {
  Future<List<int>> build(ReceiptSummary receipt, PaperWidth paperWidth) async {
    final profile = await CapabilityProfile.load();
    final generator = Generator(
      paperWidth == PaperWidth.mm80 ? PaperSize.mm80 : PaperSize.mm58,
      profile,
    );
    final width = paperWidth.columns;
    final bytes = <int>[];

    bytes.addAll(generator.reset());
    bytes.addAll(generator.setGlobalCodeTable('CP1252'));

    _add(
      bytes,
      generator,
      receipt.branchName,
      bold: true,
      align: PosAlign.center,
    );
    if (receipt.branchAddress != null) {
      _add(bytes, generator, receipt.branchAddress!, align: PosAlign.center);
    }
    if (receipt.branchPhone != null) {
      _add(bytes, generator, receipt.branchPhone!, align: PosAlign.center);
    }

    _line(bytes, generator, _divider(width));
    _add(
      bytes,
      generator,
      'NOTA ${receipt.orderNumber}',
      bold: true,
      align: PosAlign.center,
    );
    _add(
      bytes,
      generator,
      'Invoice ${receipt.invoiceNumber}',
      align: PosAlign.center,
    );
    _line(bytes, generator, _divider(width));

    _add(bytes, generator, 'Pelanggan: ${receipt.customerName}');
    _add(bytes, generator, 'Selesai: ${formatDateTime(receipt.dueAt)}');
    if (receipt.acceptedAt != null) {
      _add(
        bytes,
        generator,
        'Diterima: ${formatDateTime(receipt.acceptedAt!)}',
      );
    }
    if (receipt.status.isNotEmpty) {
      _add(bytes, generator, 'Status: ${receipt.status}');
    }

    _line(bytes, generator, _divider(width));

    for (final line in receipt.lines) {
      _add(bytes, generator, line.serviceName, bold: true);
      final quantity = [
        if (line.billableQuantity.isNotEmpty) line.billableQuantity,
        if (line.unit.isNotEmpty) line.unit,
      ].join(' ');
      if (quantity.isNotEmpty) {
        _line(
          bytes,
          generator,
          _twoColumns(quantity, formatRupiah(line.lineTotalRupiah), width),
        );
      } else {
        _line(
          bytes,
          generator,
          formatRupiah(line.lineTotalRupiah).padLeft(width),
        );
      }
      if (line.unitPriceRupiah > 0) {
        _add(
          bytes,
          generator,
          '@ ${formatRupiah(line.unitPriceRupiah)}',
          align: PosAlign.right,
        );
      }
    }

    _line(bytes, generator, _divider(width));
    if (receipt.serviceSubtotalRupiah > 0) {
      _line(
        bytes,
        generator,
        _twoColumns(
          'Subtotal',
          formatRupiah(receipt.serviceSubtotalRupiah),
          width,
        ),
      );
    }
    if (receipt.discountRupiah > 0) {
      _line(
        bytes,
        generator,
        _twoColumns(
          'Diskon',
          '-${formatRupiah(receipt.discountRupiah)}',
          width,
        ),
      );
    }
    if (receipt.expressSurchargeRupiah > 0) {
      _line(
        bytes,
        generator,
        _twoColumns(
          'Express',
          formatRupiah(receipt.expressSurchargeRupiah),
          width,
        ),
      );
    }
    if (receipt.deliveryFeeRupiah > 0) {
      _line(
        bytes,
        generator,
        _twoColumns('Antar', formatRupiah(receipt.deliveryFeeRupiah), width),
      );
    }
    _line(
      bytes,
      generator,
      _twoColumns('Total', formatRupiah(receipt.totalRupiah), width),
      bold: true,
    );
    _line(
      bytes,
      generator,
      _twoColumns('Dibayar', formatRupiah(receipt.paidRupiah), width),
    );
    _line(
      bytes,
      generator,
      _twoColumns('Sisa', formatRupiah(receipt.balanceDueRupiah), width),
      bold: receipt.balanceDueRupiah > 0,
    );
    _line(bytes, generator, _divider(width));

    _add(
      bytes,
      generator,
      'Simpan nota ini. Pembayaran transfer manual diverifikasi oleh kasir.',
      align: PosAlign.center,
    );
    final receiptHash = receipt.receiptHash;
    if (receiptHash != null && receiptHash.isNotEmpty) {
      final shortHash = receiptHash.length > 12
          ? receiptHash.substring(0, 12)
          : receiptHash;
      _add(bytes, generator, 'Ref: $shortHash', align: PosAlign.center);
    }

    bytes.addAll(generator.feed(2));
    bytes.addAll(generator.cut());
    return bytes;
  }

  Future<List<int>> buildTest(PaperWidth paperWidth) async {
    final profile = await CapabilityProfile.load();
    final generator = Generator(
      paperWidth == PaperWidth.mm80 ? PaperSize.mm80 : PaperSize.mm58,
      profile,
    );
    final bytes = <int>[];
    bytes.addAll(generator.reset());
    bytes.addAll(generator.setGlobalCodeTable('CP1252'));
    _add(
      bytes,
      generator,
      'NetClean Staff',
      bold: true,
      align: PosAlign.center,
    );
    _add(bytes, generator, 'Tes cetak printer', align: PosAlign.center);
    _add(
      bytes,
      generator,
      'Lebar: ${paperWidth.label}',
      align: PosAlign.center,
    );
    _add(
      bytes,
      generator,
      formatDateTime(DateTime.now()),
      align: PosAlign.center,
    );
    bytes.addAll(generator.feed(2));
    bytes.addAll(generator.cut());
    return bytes;
  }

  void _add(
    List<int> bytes,
    Generator generator,
    String value, {
    bool bold = false,
    PosAlign align = PosAlign.left,
  }) {
    final text = _sanitize(value);
    if (text.isEmpty) {
      return;
    }
    bytes.addAll(
      generator.text(
        text,
        styles: PosStyles(bold: bold, align: align),
      ),
    );
  }

  void _line(
    List<int> bytes,
    Generator generator,
    String value, {
    bool bold = false,
  }) {
    _add(bytes, generator, value, bold: bold);
  }

  String _twoColumns(String left, String right, int width) {
    final safeLeft = _sanitize(left);
    final safeRight = _sanitize(right);
    if (safeLeft.length + safeRight.length + 1 <= width) {
      return '$safeLeft${' ' * (width - safeLeft.length - safeRight.length)}$safeRight';
    }
    return '$safeLeft\n${safeRight.padLeft(width)}';
  }

  String _divider(int width) => '-' * width;

  String _sanitize(String value) {
    return value
        .replaceAll(RegExp(r'[\r\n\t]+'), ' ')
        .replaceAll(RegExp(r'[^\x20-\x7E\xA0-\xFF]'), '?')
        .trim();
  }
}
