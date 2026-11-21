import 'package:flutter_test/flutter_test.dart';
import 'package:netclean_staff/core/scan/scan_payload.dart';

void main() {
  test('normalizes order numbers and bag labels', () {
    expect(
      ScanPayload.normalize('  uat-main-20261114-000001\n'),
      'UAT-MAIN-20261114-000001',
    );
    expect(ScanPayload.normalize('bag-01 abc'), 'BAG-01ABC');
    expect(ScanPayload.matchesLabel('bag-01abc', 'BAG-01ABC'), isTrue);
  });

  test('rejects empty, oversized, whitespace-only, and url payloads', () {
    expect(ScanPayload.normalize(null), isNull);
    expect(ScanPayload.normalize('   '), isNull);
    expect(
      ScanPayload.normalize('https://staging.netclean.id/tracking/abc'),
      isNull,
    );
    expect(ScanPayload.normalize('x' * 101), isNull);
    expect(ScanPayload.matchesLabel('bag-02', 'BAG-01'), isFalse);
  });
}
