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

## Scan kamera

- Tombol **Scan kamera** tersedia di layar Scan, layar Operasi, dan dialog serah terima bag.
- Izin kamera diminta saat scanner pertama kali dibuka. Jika ditolak, gunakan **Masukkan manual** atau aktifkan izin dari Setelan aplikasi.
- Scanner menerima QR nota dan label bag. QR tracking pelanggan tidak dipakai untuk membuka data internal.
- Di perangkat tanpa kamera, input manual tetap menjadi fallback.

## Printer thermal

Printer P0 yang didukung: Eppos 80 mm, Smartcom portable 80 mm, dan Eppos 58 mm. Koneksi P0 memakai Bluetooth Classic. Plugin printer meminta izin `BLUETOOTH_CONNECT` dan `BLUETOOTH_SCAN` pada Android 12+; aplikasi tidak meminta izin lokasi.

1. Nyalakan printer dan pasangkan lewat **Setelan Bluetooth Android**.
2. Buka **Pengaturan printer** dari pratinjau nota.
3. Izinkan **Nearby devices** saat diminta.
4. Pilih printer, pilih lebar 80 mm atau 58 mm, lalu tekan **Tes cetak**.
5. Dari detail pesanan atau nota, tekan **Pratinjau & cetak** untuk memeriksa nota sebelum mencetak.

Printer default dan lebar kertas disimpan di secure storage perangkat. Cetak ulang membuat attempt baru dan tidak membuat order atau pembayaran baru. Jika Bluetooth mati, printer belum dipasangkan, atau kertas habis, aplikasi menampilkan pesan dan mencatat attempt gagal bila permintaan cetak sudah dikirim.

Catatan: ML Kit menggunakan model bundled agar scan tetap bekerja tanpa unduhan pertama; ukuran APK bertambah sekitar 3–10 MB.
