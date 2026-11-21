import '../auth/secret_store.dart';
import 'printer_models.dart';

class PrinterStore {
  PrinterStore(this._store);

  static const _macKey = 'printer_mac_v1';
  static const _nameKey = 'printer_name_v1';
  static const _paperKey = 'printer_paper_v1';

  final SecretStore _store;

  Future<PrinterConfig?> read() async {
    final mac = await _store.read(_macKey);
    if (mac == null || mac.isEmpty) {
      return null;
    }
    final name = await _store.read(_nameKey);
    final paper = PaperWidth.fromStorage(await _store.read(_paperKey));
    return PrinterConfig(
      device: PrinterDevice(
        name: name == null || name.isEmpty ? mac : name,
        macAddress: mac,
      ),
      paperWidth: paper,
    );
  }

  Future<void> save(PrinterConfig config) async {
    await _store.write(_macKey, config.device.macAddress);
    await _store.write(_nameKey, config.device.name);
    await _store.write(_paperKey, config.paperWidth.name);
  }

  Future<void> clear() async {
    await _store.delete(_macKey);
    await _store.delete(_nameKey);
    await _store.delete(_paperKey);
  }
}
