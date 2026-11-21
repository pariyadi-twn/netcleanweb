import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/printer/esc_pos_receipt_builder.dart';
import '../../../core/printer/printer_models.dart';
import '../../../core/providers.dart';
import '../../../core/ui/page_header.dart';
import '../../../core/ui/responsive.dart';
import '../../../core/ui/theme.dart';

class PrinterSettingsScreen extends ConsumerStatefulWidget {
  const PrinterSettingsScreen({super.key});

  @override
  ConsumerState<PrinterSettingsScreen> createState() =>
      _PrinterSettingsScreenState();
}

class _PrinterSettingsScreenState extends ConsumerState<PrinterSettingsScreen> {
  PrinterConfig? _config;
  List<PrinterDevice> _devices = const [];
  PaperWidth _paperWidth = PaperWidth.mm80;
  bool _busy = true;
  bool _bluetoothEnabled = false;
  bool _permissionGranted = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    unawaited(_load());
  }

  Future<void> _load() async {
    if (!mounted) {
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final service = ref.read(printerServiceProvider);
      final permission = await service.permissionGranted();
      final enabled = permission ? await service.bluetoothEnabled() : false;
      var devices = const <PrinterDevice>[];
      if (enabled && permission) {
        devices = await service.pairedDevices();
      }
      final config = await ref.read(printerStoreProvider).read();
      if (!mounted) {
        return;
      }
      setState(() {
        _bluetoothEnabled = enabled;
        _permissionGranted = permission;
        _devices = devices;
        _config = config;
        _paperWidth = config?.paperWidth ?? PaperWidth.mm80;
      });
    } catch (_) {
      if (mounted) {
        setState(() => _error = 'Pengaturan printer belum dapat dimuat.');
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _requestPermission() async {
    setState(() => _busy = true);
    try {
      final granted = await ref
          .read(printerServiceProvider)
          .requestPermission();
      if (!granted) {
        _say('Izin Nearby devices belum diberikan.');
      }
    } catch (_) {
      _say('Izin Bluetooth belum dapat diminta.');
    } finally {
      await _load();
    }
  }

  Future<void> _select(PrinterDevice device) async {
    final config = PrinterConfig(device: device, paperWidth: _paperWidth);
    await ref.read(printerStoreProvider).save(config);
    if (mounted) {
      setState(() => _config = config);
    }
  }

  Future<void> _setPaper(PaperWidth width) async {
    setState(() => _paperWidth = width);
    final config = _config;
    if (config == null) {
      return;
    }
    final updated = PrinterConfig(device: config.device, paperWidth: width);
    await ref.read(printerStoreProvider).save(updated);
    if (mounted) {
      setState(() => _config = updated);
    }
  }

  Future<void> _testPrint() async {
    final config = _config;
    if (config == null) {
      _say('Pilih printer terlebih dahulu.');
      return;
    }
    setState(() => _busy = true);
    try {
      final service = ref.read(printerServiceProvider);
      await service.connect(config.device);
      final bytes = await EscPosReceiptBuilder().buildTest(_paperWidth);
      await service.printBytes(bytes);
      _say('Tes cetak berhasil.');
    } on PrinterException catch (error) {
      _say(error.message);
    } catch (_) {
      _say('Tes cetak gagal. Periksa printer dan coba lagi.');
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _forget() async {
    await ref.read(printerStoreProvider).clear();
    if (mounted) {
      setState(() => _config = null);
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
    final width = MediaQuery.sizeOf(context).width;
    final padding = Responsive.horizontalPadding(width);

    return Scaffold(
      appBar: AppBar(title: const Text('Printer thermal')),
      body: DecoratedBox(
        decoration: const BoxDecoration(gradient: AppTheme.canvasGradient),
        child: SafeArea(
          top: false,
          child: ResponsiveCenter(
            child: ListView(
              key: const Key('printer-settings-screen'),
              padding: EdgeInsets.fromLTRB(padding, 18, padding, 32),
              children: [
                const AppPageHeader(
                  eyebrow: 'Perangkat',
                  title: 'Printer thermal',
                  subtitle:
                      'Pasangkan printer sekali di Setelan Bluetooth Android, '
                      'lalu pilih printer default di sini.',
                ),
                if (_busy) ...[
                  const SizedBox(height: 16),
                  const LinearProgressIndicator(),
                ],
                if (_error != null) ...[
                  const SizedBox(height: 16),
                  _ErrorPanel(message: _error!),
                ],
                const SizedBox(height: 16),
                _StatusCard(
                  bluetoothEnabled: _bluetoothEnabled,
                  permissionGranted: _permissionGranted,
                  config: _config,
                ),
                if (!_permissionGranted) ...[
                  const SizedBox(height: 16),
                  _ActionCard(
                    title: 'Izin Nearby devices diperlukan',
                    description:
                        'Aplikasi memerlukan izin ini untuk berkomunikasi '
                        'dengan printer yang sudah dipasangkan.',
                    buttonLabel: 'Izinkan',
                    onPressed: _requestPermission,
                  ),
                ] else if (!_bluetoothEnabled) ...[
                  const SizedBox(height: 16),
                  _ActionCard(
                    title: 'Bluetooth belum aktif',
                    description:
                        'Aktifkan Bluetooth, lalu pasangkan printer thermal '
                        'dari Setelan Android.',
                    buttonLabel: 'Buka Setelan Bluetooth',
                    onPressed: ref
                        .read(printerServiceProvider)
                        .openBluetoothSettings,
                  ),
                ],
                const SizedBox(height: 16),
                _DevicesCard(
                  devices: _devices,
                  selectedMac: _config?.device.macAddress,
                  onSelect: _select,
                  onRefresh: _load,
                ),
                const SizedBox(height: 16),
                _PaperCard(paperWidth: _paperWidth, onChanged: _setPaper),
                const SizedBox(height: 16),
                FilledButton.icon(
                  onPressed: _busy || _config == null ? null : _testPrint,
                  icon: const Icon(Icons.print_outlined),
                  label: const Text('Tes cetak'),
                ),
                if (_config != null) ...[
                  const SizedBox(height: 8),
                  OutlinedButton(
                    onPressed: _busy ? null : _forget,
                    child: const Text('Lupakan printer'),
                  ),
                ],
                const SizedBox(height: 20),
                Text(
                  'Printer yang didukung: Eppos 80 mm, Smartcom portable 80 mm, '
                  'dan Eppos 58 mm. Gunakan kertas 80 mm sebagai default.',
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _StatusCard extends StatelessWidget {
  const _StatusCard({
    required this.bluetoothEnabled,
    required this.permissionGranted,
    required this.config,
  });

  final bool bluetoothEnabled;
  final bool permissionGranted;
  final PrinterConfig? config;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Status', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 12),
            _StatusRow(
              label: 'Bluetooth',
              value: !permissionGranted
                  ? 'Perlu izin'
                  : bluetoothEnabled
                  ? 'Aktif'
                  : 'Tidak aktif',
              ok: permissionGranted && bluetoothEnabled,
            ),
            _StatusRow(
              label: 'Izin Nearby devices',
              value: permissionGranted ? 'Diberikan' : 'Belum diberikan',
              ok: permissionGranted,
            ),
            _StatusRow(
              label: 'Printer default',
              value: config == null
                  ? 'Belum dipilih'
                  : '${config!.device.name} (${config!.paperWidth.label})',
              ok: config != null,
            ),
          ],
        ),
      ),
    );
  }
}

class _StatusRow extends StatelessWidget {
  const _StatusRow({
    required this.label,
    required this.value,
    required this.ok,
  });

  final String label;
  final String value;
  final bool ok;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          Expanded(child: Text(label)),
          Icon(
            ok ? Icons.check_circle_outline : Icons.error_outline,
            size: 18,
            color: ok ? Colors.green.shade700 : AppTheme.textSecondary,
          ),
          const SizedBox(width: 6),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.end,
              style: const TextStyle(fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }
}

class _ActionCard extends StatelessWidget {
  const _ActionCard({
    required this.title,
    required this.description,
    required this.buttonLabel,
    required this.onPressed,
  });

  final String title;
  final String description;
  final String buttonLabel;
  final Future<void> Function() onPressed;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 6),
            Text(description),
            const SizedBox(height: 14),
            FilledButton(onPressed: onPressed, child: Text(buttonLabel)),
          ],
        ),
      ),
    );
  }
}

class _DevicesCard extends StatelessWidget {
  const _DevicesCard({
    required this.devices,
    required this.selectedMac,
    required this.onSelect,
    required this.onRefresh,
  });

  final List<PrinterDevice> devices;
  final String? selectedMac;
  final Future<void> Function(PrinterDevice) onSelect;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    'Printer terpasang',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                ),
                IconButton(
                  tooltip: 'Muat ulang printer',
                  onPressed: onRefresh,
                  icon: const Icon(Icons.refresh_rounded),
                ),
              ],
            ),
            if (devices.isEmpty)
              const Padding(
                padding: EdgeInsets.only(top: 8),
                child: Text('Belum ada printer yang dipasangkan.'),
              )
            else
              ...devices.map(
                (device) => ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(Icons.print_outlined),
                  title: Text(device.name),
                  subtitle: Text(device.macAddress),
                  trailing: selectedMac == device.macAddress
                      ? Icon(
                          Icons.check_circle,
                          color: Theme.of(context).colorScheme.primary,
                        )
                      : null,
                  onTap: () => onSelect(device),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _PaperCard extends StatelessWidget {
  const _PaperCard({required this.paperWidth, required this.onChanged});

  final PaperWidth paperWidth;
  final Future<void> Function(PaperWidth) onChanged;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Lebar kertas', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 12),
            SegmentedButton<PaperWidth>(
              segments: const [
                ButtonSegment(value: PaperWidth.mm80, label: Text('80 mm')),
                ButtonSegment(value: PaperWidth.mm58, label: Text('58 mm')),
              ],
              selected: {paperWidth},
              onSelectionChanged: (selection) => onChanged(selection.first),
            ),
          ],
        ),
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
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: scheme.errorContainer,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.error_outline_rounded, color: scheme.error, size: 19),
          const SizedBox(width: 9),
          Expanded(
            child: Text(
              message,
              style: Theme.of(
                context,
              ).textTheme.bodySmall?.copyWith(color: scheme.error),
            ),
          ),
        ],
      ),
    );
  }
}
