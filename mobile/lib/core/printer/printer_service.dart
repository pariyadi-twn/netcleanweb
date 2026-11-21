import 'dart:io';

import 'package:flutter/services.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:print_bluetooth_thermal/print_bluetooth_thermal.dart';

import 'printer_models.dart';

abstract interface class PrinterService {
  Future<bool> bluetoothEnabled();

  Future<bool> permissionGranted();

  Future<bool> requestPermission();

  Future<List<PrinterDevice>> pairedDevices();

  Future<bool> connectionStatus();

  Future<void> connect(PrinterDevice device);

  Future<void> printBytes(List<int> bytes);

  Future<void> disconnect();

  Future<void> openBluetoothSettings();
}

class BluetoothPrinterService implements PrinterService {
  static const _settingsChannel = MethodChannel(
    'id.netclean.netclean_staff/settings',
  );

  @override
  Future<bool> bluetoothEnabled() => PrintBluetoothThermal.bluetoothEnabled;

  @override
  Future<bool> permissionGranted() async {
    if (!Platform.isAndroid) {
      return true;
    }
    final connect = await Permission.bluetoothConnect.status;
    final scan = await Permission.bluetoothScan.status;
    return connect.isGranted && scan.isGranted;
  }

  @override
  Future<bool> requestPermission() async {
    if (!Platform.isAndroid) {
      return true;
    }
    if (await permissionGranted()) {
      return true;
    }
    final statuses = await [
      Permission.bluetoothConnect,
      Permission.bluetoothScan,
    ].request();
    return statuses.values.every((status) => status.isGranted);
  }

  @override
  Future<List<PrinterDevice>> pairedDevices() async {
    final devices = await PrintBluetoothThermal.pairedBluetooths;
    return devices
        .map(
          (device) => PrinterDevice(
            name: device.name.isEmpty ? device.macAdress : device.name,
            macAddress: device.macAdress,
          ),
        )
        .toList(growable: false);
  }

  @override
  Future<bool> connectionStatus() => PrintBluetoothThermal.connectionStatus;

  @override
  Future<void> connect(PrinterDevice device) async {
    if (!await requestPermission()) {
      throw const PrinterException(
        'bluetooth_permission_denied',
        'Izin Nearby devices diperlukan untuk menghubungkan printer.',
      );
    }
    if (!await bluetoothEnabled()) {
      throw const PrinterException(
        'bluetooth_disabled',
        'Bluetooth tidak aktif. Aktifkan Bluetooth lalu coba lagi.',
      );
    }
    final connected = await PrintBluetoothThermal.connect(
      macPrinterAddress: device.macAddress,
    );
    if (!connected) {
      throw const PrinterException(
        'connect_failed',
        'Printer tidak dapat dihubungkan. Pastikan printer menyala dan sudah dipasangkan.',
      );
    }
  }

  @override
  Future<void> printBytes(List<int> bytes) async {
    if (!await connectionStatus()) {
      throw const PrinterException(
        'connect_failed',
        'Printer terputus. Hubungkan ulang lalu coba cetak lagi.',
      );
    }
    final sent = await PrintBluetoothThermal.writeBytes(bytes);
    if (!sent) {
      throw const PrinterException(
        'write_failed',
        'Data nota gagal dikirim ke printer. Periksa kertas dan koneksi printer.',
      );
    }
  }

  @override
  Future<void> disconnect() async {
    await PrintBluetoothThermal.disconnect;
  }

  @override
  Future<void> openBluetoothSettings() async {
    if (!Platform.isAndroid) {
      await openAppSettings();
      return;
    }
    try {
      await _settingsChannel.invokeMethod<void>('openBluetoothSettings');
    } on PlatformException {
      await openAppSettings();
    }
  }
}
