import 'package:flutter/material.dart';

import 'code_scanner_screen.dart';

abstract interface class CodeScanner {
  Future<String?> scan(BuildContext context, {String title = 'Scan kode'});
}

class CameraCodeScanner implements CodeScanner {
  const CameraCodeScanner();

  @override
  Future<String?> scan(BuildContext context, {String title = 'Scan kode'}) {
    return Navigator.of(context).push<String>(
      MaterialPageRoute<String>(
        builder: (context) => CodeScannerScreen(title: title),
      ),
    );
  }
}
