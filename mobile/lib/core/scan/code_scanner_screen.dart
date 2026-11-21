import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:permission_handler/permission_handler.dart';

import 'scan_payload.dart';

class CodeScannerScreen extends StatefulWidget {
  const CodeScannerScreen({super.key, this.title = 'Scan kode'});

  final String title;

  @override
  State<CodeScannerScreen> createState() => _CodeScannerScreenState();
}

class _CodeScannerScreenState extends State<CodeScannerScreen>
    with WidgetsBindingObserver {
  late final MobileScannerController _controller;
  StreamSubscription<BarcodeCapture>? _subscription;
  MobileScannerException? _startError;
  DateTime? _lastRejectedAt;
  bool _handled = false;

  @override
  void initState() {
    super.initState();
    _controller = MobileScannerController(
      autoStart: false,
      formats: const [BarcodeFormat.qrCode, BarcodeFormat.code128],
      detectionSpeed: DetectionSpeed.noDuplicates,
    );
    WidgetsBinding.instance.addObserver(this);
    _listen();
    unawaited(_start());
  }

  void _listen() {
    _subscription ??= _controller.barcodes.listen(
      _onCapture,
      onError: _onScanError,
    );
  }

  Future<void> _start() async {
    try {
      await _controller.start();
    } on MobileScannerException catch (error) {
      if (mounted) {
        setState(() => _startError = error);
      }
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _listen();
      unawaited(_start());
      return;
    }

    if (!_controller.value.hasCameraPermission) {
      return;
    }

    unawaited(_subscription?.cancel());
    _subscription = null;
    unawaited(_controller.stop());
  }

  void _onCapture(BarcodeCapture capture) {
    if (_handled) {
      return;
    }

    for (final barcode in capture.barcodes) {
      final value = ScanPayload.normalize(barcode.rawValue);
      if (value == null) {
        continue;
      }
      _handled = true;
      unawaited(HapticFeedback.mediumImpact());
      Navigator.of(context).pop(value);
      return;
    }

    _reject('Kode tidak dikenali. Gunakan QR nota atau label bag.');
  }

  void _onScanError(Object error, StackTrace stackTrace) {
    _reject('Kode belum dapat dibaca. Coba arahkan kamera lagi.');
  }

  void _reject(String message) {
    final now = DateTime.now();
    if (_lastRejectedAt != null &&
        now.difference(_lastRejectedAt!) < const Duration(seconds: 2)) {
      return;
    }
    _lastRejectedAt = now;
    if (mounted) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(message)));
    }
  }

  Future<void> _manualEntry() async {
    final input = TextEditingController();
    final value = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Masukkan kode manual'),
        content: TextField(
          controller: input,
          autofocus: true,
          textCapitalization: TextCapitalization.characters,
          decoration: const InputDecoration(
            labelText: 'Nomor nota atau label bag',
          ),
          onSubmitted: (value) => Navigator.of(context).pop(value),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Batal'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(input.text),
            child: const Text('Gunakan'),
          ),
        ],
      ),
    );
    input.dispose();
    if (!mounted || value == null) {
      return;
    }
    final normalized = ScanPayload.normalize(value);
    if (normalized == null) {
      _reject('Kode tidak valid. Periksa kembali nomor nota atau label bag.');
      return;
    }
    Navigator.of(context).pop(normalized);
  }

  Future<void> _openSettings() => openAppSettings();

  String _errorMessage(MobileScannerException error) {
    return switch (error.errorCode) {
      MobileScannerErrorCode.permissionDenied =>
        'Izin kamera ditolak. Aktifkan izin kamera di Setelan aplikasi.',
      MobileScannerErrorCode.unsupported =>
        'Perangkat ini tidak mendukung kamera untuk scan.',
      _ => 'Kamera belum dapat dibuka. Coba lagi atau masukkan kode manual.',
    };
  }

  Widget _errorView(MobileScannerException error) {
    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(
              Icons.no_photography_outlined,
              color: Colors.white,
              size: 44,
            ),
            const SizedBox(height: 16),
            Text(
              _errorMessage(error),
              textAlign: TextAlign.center,
              style: const TextStyle(color: Colors.white, fontSize: 16),
            ),
            const SizedBox(height: 20),
            if (error.errorCode == MobileScannerErrorCode.permissionDenied) ...[
              FilledButton(
                onPressed: _openSettings,
                child: const Text('Buka Setelan aplikasi'),
              ),
              const SizedBox(height: 10),
              OutlinedButton(
                onPressed: () {
                  setState(() => _startError = null);
                  unawaited(_start());
                },
                child: const Text('Coba lagi'),
              ),
            ] else
              FilledButton(
                onPressed: () {
                  setState(() => _startError = null);
                  unawaited(_start());
                },
                child: const Text('Coba lagi'),
              ),
            const SizedBox(height: 10),
            OutlinedButton(
              onPressed: _manualEntry,
              child: const Text('Masukkan manual'),
            ),
            const SizedBox(height: 10),
            TextButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text('Tutup'),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        title: Text(widget.title),
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        actions: [
          ValueListenableBuilder<MobileScannerState>(
            valueListenable: _controller,
            builder: (context, state, _) => IconButton(
              tooltip: state.torchState == TorchState.on
                  ? 'Matikan lampu'
                  : 'Nyalakan lampu',
              onPressed: state.torchState == TorchState.unavailable
                  ? null
                  : () => unawaited(_controller.toggleTorch()),
              icon: Icon(
                state.torchState == TorchState.on
                    ? Icons.flash_on_rounded
                    : Icons.flash_off_rounded,
              ),
            ),
          ),
          IconButton(
            tooltip: 'Ganti kamera',
            onPressed: () => unawaited(_controller.switchCamera()),
            icon: const Icon(Icons.cameraswitch_outlined),
          ),
        ],
      ),
      body: ValueListenableBuilder<MobileScannerState>(
        valueListenable: _controller,
        builder: (context, state, _) {
          final error = _startError ?? state.error;
          if (error != null) {
            return _errorView(error);
          }

          return Stack(
            fit: StackFit.expand,
            children: [
              MobileScanner(
                controller: _controller,
                onDetectError: _onScanError,
                errorBuilder: (context, error) => _errorView(error),
              ),
              const IgnorePointer(
                child: CustomPaint(painter: _ScanWindowPainter()),
              ),
              Positioned(
                left: 20,
                right: 20,
                bottom: 24,
                child: SafeArea(
                  top: false,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Text(
                        'Arahkan kamera ke QR nota atau label bag.',
                        textAlign: TextAlign.center,
                        style: TextStyle(color: Colors.white, fontSize: 14),
                      ),
                      const SizedBox(height: 12),
                      SizedBox(
                        width: double.infinity,
                        child: FilledButton.tonalIcon(
                          onPressed: _manualEntry,
                          icon: const Icon(Icons.keyboard_outlined),
                          label: const Text('Masukkan manual'),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    unawaited(_subscription?.cancel());
    _subscription = null;
    unawaited(_controller.dispose());
    super.dispose();
  }
}

class _ScanWindowPainter extends CustomPainter {
  const _ScanWindowPainter();

  @override
  void paint(Canvas canvas, Size size) {
    final side = size.shortestSide * 0.72;
    final window = Rect.fromCenter(
      center: Offset(size.width / 2, size.height * 0.42),
      width: side,
      height: side,
    );
    final shade = Paint()..color = Colors.black.withValues(alpha: 0.5);
    final path = Path()
      ..addRect(Offset.zero & size)
      ..addRRect(RRect.fromRectAndRadius(window, const Radius.circular(22)))
      ..fillType = PathFillType.evenOdd;
    canvas.drawPath(path, shade);
    canvas.drawRRect(
      RRect.fromRectAndRadius(window, const Radius.circular(22)),
      Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = 3
        ..color = Colors.white,
    );
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
