import 'package:flutter_test/flutter_test.dart';
import 'package:netclean_staff/core/ui/formatting.dart';

void main() {
  test('formats rupiah with thousands separators and negative values', () {
    expect(formatRupiah(0), 'Rp0');
    expect(formatRupiah(8000), 'Rp8.000');
    expect(formatRupiah(71600), 'Rp71.600');
    expect(formatRupiah(-2000), '-Rp2.000');
  });

  test('formats quantity without trailing zeroes', () {
    expect(formatQuantity('3'), '3');
    expect(formatQuantity('3.0'), '3');
    expect(formatQuantity('0.50'), '0.5');
    expect(formatQuantity('1.250'), '1.25');
  });
}
