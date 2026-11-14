# NetClean Staff

Aplikasi Android staf NetClean memakai Flutter, Riverpod, Drift, dan secure storage.

## Local verification

```bash
flutter pub get
flutter analyze
flutter test
flutter build apk --debug --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

Build UAT staging:

```bash
flutter build apk --debug --dart-define=API_BASE_URL=https://staging.netclean.id/api/v1
```

APK tersedia di `build/app/outputs/flutter-apk/app-debug.apk`. Keystore, `key.properties`, dan `local.properties` tidak boleh dimasukkan ke repository.
