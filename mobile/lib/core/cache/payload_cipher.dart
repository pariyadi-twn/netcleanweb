import 'dart:convert';

import 'package:cryptography/cryptography.dart';

import 'cache_key_store.dart';

class EncryptedPayload {
  const EncryptedPayload({
    required this.cipherText,
    required this.nonce,
    required this.mac,
  });

  final String cipherText;
  final String nonce;
  final String mac;
}

class PayloadCipher {
  PayloadCipher(this._keys);

  final CacheKeyStore _keys;
  final AesGcm _algorithm = AesGcm.with256bits();

  Future<EncryptedPayload> encrypt(Map<String, dynamic> payload) async {
    final key = await _keys.loadOrCreate();
    final nonce = _algorithm.newNonce();
    final box = await _algorithm.encrypt(
      utf8.encode(jsonEncode(payload)),
      secretKey: key,
      nonce: nonce,
    );
    return EncryptedPayload(
      cipherText: base64Encode(box.cipherText),
      nonce: base64Encode(box.nonce),
      mac: base64Encode(box.mac.bytes),
    );
  }

  Future<Map<String, dynamic>> decrypt(EncryptedPayload payload) async {
    final key = await _keys.loadOrCreate();
    final clear = await _algorithm.decrypt(
      SecretBox(
        base64Decode(payload.cipherText),
        nonce: base64Decode(payload.nonce),
        mac: Mac(base64Decode(payload.mac)),
      ),
      secretKey: key,
    );
    return jsonDecode(utf8.decode(clear)) as Map<String, dynamic>;
  }
}
