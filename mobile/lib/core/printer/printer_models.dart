import 'package:flutter/foundation.dart';

enum PaperWidth {
  mm58('58 mm', 32, 250),
  mm80('80 mm', 48, 340);

  const PaperWidth(this.label, this.columns, this.previewWidth);

  final String label;
  final int columns;
  final double previewWidth;

  static PaperWidth fromStorage(String? value) {
    return values.firstWhere(
      (width) => width.name == value,
      orElse: () => PaperWidth.mm80,
    );
  }
}

@immutable
class PrinterDevice {
  const PrinterDevice({required this.name, required this.macAddress});

  final String name;
  final String macAddress;
}

@immutable
class PrinterConfig {
  const PrinterConfig({required this.device, required this.paperWidth});

  final PrinterDevice device;
  final PaperWidth paperWidth;
}

class PrinterException implements Exception {
  const PrinterException(this.code, this.message);

  final String code;
  final String message;

  @override
  String toString() => '$code: $message';
}
