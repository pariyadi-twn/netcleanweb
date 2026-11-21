import 'package:flutter_test/flutter_test.dart';
import 'package:netclean_staff/core/auth/secret_store.dart';
import 'package:netclean_staff/core/printer/printer_models.dart';
import 'package:netclean_staff/core/printer/printer_store.dart';

void main() {
  test('persists and clears the selected printer and paper width', () async {
    final store = PrinterStore(MemorySecretStore());
    expect(await store.read(), isNull);

    await store.save(
      const PrinterConfig(
        device: PrinterDevice(
          name: 'Smartcom 80',
          macAddress: 'AA:BB:CC:DD:EE:FF',
        ),
        paperWidth: PaperWidth.mm58,
      ),
    );

    final saved = await store.read();
    expect(saved?.device.name, 'Smartcom 80');
    expect(saved?.device.macAddress, 'AA:BB:CC:DD:EE:FF');
    expect(saved?.paperWidth, PaperWidth.mm58);

    await store.clear();
    expect(await store.read(), isNull);
  });

  test('falls back to 80 mm for an unknown stored width', () {
    expect(PaperWidth.fromStorage('nope'), PaperWidth.mm80);
    expect(PaperWidth.fromStorage(null), PaperWidth.mm80);
    expect(PaperWidth.fromStorage('mm58'), PaperWidth.mm58);
  });
}
