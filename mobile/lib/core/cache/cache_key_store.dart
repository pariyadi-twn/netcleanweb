import 'dart:convert';

import 'package:cryptography/cryptography.dart';

import '../auth/secret_store.dart';

class CacheKeyStore {
  CacheKeyStore(this._secrets);

  static const _key = 'cache_aes_key_v1';
  final SecretStore _secrets;

  Future<SecretKey> loadOrCreate() async {
    final stored = await _secrets.read(_key);
    if (stored != null && stored.isNotEmpty) {
      return AesGcm.with256bits().newSecretKeyFromBytes(base64Decode(stored));
    }

    final generated = await AesGcm.with256bits().newSecretKey();
    await _secrets.write(_key, base64Encode(await generated.extractBytes()));
    return generated;
  }
}
