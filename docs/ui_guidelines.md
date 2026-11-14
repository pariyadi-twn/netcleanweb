# Panduan UI dan UX Laundry SaaS

Versi 1.1 · 28 Oktober 2026 · Status usulan untuk implementasi

[PRD](prd.md) · [TRD](trd.md) · [Database](erd.md) · [UI UX](ui_guidelines.md) · [Aturan AI](agents.md) · [Keamanan](security.md) · [Rencana](todo.md)

## 1 Prinsip pengalaman pengguna

Aplikasi membantu staf menerima, mengerjakan, dan menyerahkan cucian tanpa kehilangan konteks cabang, barang, atau uang. Mode ringkas untuk outlet kecil dan mode jaringan untuk multi cabang memakai model bisnis yang sama. Kurangi langkah yang tidak diperlukan, tetapi jangan menyembunyikan status pending atau meminta staf menebak apakah transaksi sudah tersimpan.

UI tidak menjadi sumber kebenaran harga, pembayaran, permission, dan sisa barang. Tampilan segera memberi umpan balik sentuhan, sedangkan keputusan final menunggu server. Rujukan perilaku: [prd.md](prd.md); kontrak status dan error: [trd.md](trd.md).

### UX01 Konteks selalu terlihat

Nama usaha, cabang aktif, peran, status koneksi, serta jumlah draft tertunda tersedia tanpa membuka pengaturan. Pada layar uang dan penyerahan, cabang ditampilkan dekat judul atau ringkasan. Pergantian tenant menyimpan draft secara terisolasi dan mengosongkan state tampilan lama. Jangan membiarkan hasil pencarian tenant sebelumnya terlihat sesaat pada tenant baru.

### UX02 Tindakan utama jelas

Setiap layar mempunyai satu tindakan utama. Pada formulir panjang, ringkasan total dan tombol lanjut berada di area yang konsisten. Tombol destruktif atau refund tidak menggunakan warna primary. Tampilkan nama tindakan, misalnya Konfirmasi pesanan atau Kembalikan Rp20.000, bukan OK tanpa konteks.

### UX03 Informasi bertahap

Pemilik satu outlet tidak melihat menu manifest atau pusat produksi yang belum diaktifkan. Operator produksi tidak melihat laporan uang. Seluruh penyembunyian menu tetap ditopang otorisasi server; UI bukan kontrol keamanan satu satunya.

## 2 Kanal dan navigasi

| Kanal | Navigasi utama | Catatan |
|---|---|---|
| Flutter kasir | Beranda, Pesanan, Scan, Pelanggan, Lainnya | Pesanan baru tersedia dari beranda dan daftar |
| Flutter operator | Tugas, Scan, QC, Lainnya | Filter lokasi dan tahap tanpa membuka banyak menu |
| Flutter kurir P1 | Tugas, Scan, Setoran, Profil | Tampilkan tugas hari ini dan yang belum selesai |
| Web pemilik | Ringkasan, Pesanan, Produksi, Keuangan, Laporan, Pengaturan | Sidebar dapat diringkas; tenant dan cabang di header |
| Web platform | Tenant, Langganan, Paket, Kesehatan, Bantuan | Visual pembeda platform dan tenant untuk mencegah salah konteks |
| Tracking pelanggan | Status minimal, perkiraan pengambilan, kontak outlet | Tanpa nomor telepon atau alamat pelanggan pada halaman publik |

Menu P1 atau P2 hanya tampil jika entitlement dan izin mendukung. Kelompok konfigurasi lanjutan tidak dimasukkan ke jalur cepat kasir.

## 3 Token visual dasar

Palet adalah baseline yang dapat diberi merek tenant tanpa mengubah makna status. Token semantik dipakai pada Flutter Theme dan CSS variables web.

| Token | Nilai awal | Penggunaan |
|---|---|---|
| `color.primary` | `#1D4ED8` | Tindakan utama dan tautan |
| `color.onPrimary` | `#FFFFFF` | Teks primary |
| `color.text` | `#0F172A` | Teks utama |
| `color.textSecondary` | `#475569` | Bantuan dan keterangan |
| `color.surface` | `#FFFFFF` | Formulir dan daftar |
| `color.background` | `#F8FAFC` | Latar aplikasi |
| `color.inputBorder` | `#64748B` | Batas input yang penting |
| `color.divider` | `#E2E8F0` | Pemisah dekoratif |
| `color.successText` / `successBg` | `#166534` / `#F0FDF4` | Sukses terkonfirmasi |
| `color.warningText` / `warningBg` | `#92400E` / `#FFFBEB` | Pending dan perlu perhatian |
| `color.dangerText` / `dangerBg` | `#B91C1C` / `#FEF2F2` | Gagal atau tindakan berisiko |
| `color.infoText` / `infoBg` | `#1E40AF` / `#EFF6FF` | Informasi netral |
| `radius.control` | 8 logical pixels | Input dan tombol |
| `radius.container` | 12 logical pixels | Kelompok konten |
| `spacing` | 4, 8, 12, 16, 24, 32 | Skala konsisten |

Teks isi mobile awal 16 sp, label 14 sp, judul layar 22–24 sp. Web isi 16 px, tabel padat minimum 14 px bila tetap terbaca. Jangan mengecilkan font untuk memasukkan lebih banyak field. Angka uang memakai tabular numerals bila tersedia.

Kontras teks biasa minimum 4,5:1, teks besar minimum 3:1; ikon dan batas kontrol penting diperiksa terhadap latarnya. Uji kombinasi aktual setelah white label dan dark mode. Token warna semata tidak membuktikan seluruh halaman sudah aksesibel.

## 4 Responsivitas dan aksesibilitas

- Titik awal layout: mobile di bawah 600 logical pixels, tablet 600–1023, web lebar mulai 1024. Sesuaikan berdasarkan isi dan pengujian, bukan merek perangkat.
- Target sentuh tindakan utama minimal 48 × 48 logical pixels; sediakan jarak antara batal dan konfirmasi.
- Teks dapat diperbesar sampai 200 persen pada alur inti tanpa menghilangkan tindakan penting. Gunakan wrap dan scroll yang masuk akal.
- Semua kontrol mempunyai label pembaca layar, urutan fokus yang wajar, dan feedback error yang terhubung ke field.
- Web dapat dipakai dengan keyboard. Fokus terlihat dan kembali ke pemicu saat dialog ditutup.
- Status menggunakan teks dan ikon, tidak hanya warna. Jangan menyampaikan sukses hanya melalui bunyi atau getaran.
- Jangan melakukan autofocus yang menutupi ringkasan dengan keyboard pada setiap pergantian layar.
- Tabel web di layar kecil berubah menjadi daftar record atau horizontal scroll yang terkontrol; nomor nota dan status tidak hilang.

## 5 Komponen dan keadaan standar

| Komponen | Aturan |
|---|---|
| Tombol | Keadaan enabled, pressed, loading, disabled; cegah ketukan ulang, tetapi idempotensi server tetap wajib |
| Input | Label tetap terlihat, unit eksplisit, bantuan pembulatan, error per field, nilai bertahan setelah error |
| Pencarian | Debounce usulan 300 ms, batalkan hasil request usang, hasil dipaginasi |
| Daftar | Ringkasan 25 record per page, filter jelas, load more, tanpa mengambil seluruh histori |
| Status chip | Nama status singkat dan ikon; pembayaran serta cucian memakai chip berbeda |
| Timeline | Waktu, tindakan, pelaku yang boleh dilihat, alasan koreksi; tidak menampilkan log teknis mentah |
| Scan QR | Izin kamera saat dibutuhkan, lampu bila tersedia, input manual sebagai fallback, verifikasi hasil sebelum aksi final |
| Ringkasan uang | Subtotal, diskon, biaya, pajak bila aktif, total, dibayar, sisa; tidak mencampur uang diserahkan dan dana diterima |
| Foto | Thumbnail, status upload, hapus sebelum final jika diizinkan, retry per foto tanpa mengulang order |
| Dialog | Judul tindakan, objek, akibat konkret, tombol kembali, dan tombol final yang menyebut tindakan |

Semua layar yang memuat data harus memiliki loading awal, refresh, empty, error dengan retry, tidak berizin, offline, serta data stale. Error satu panel tidak perlu menghilangkan seluruh layar yang masih berguna. Skeleton tidak menampilkan total uang palsu.

## 6 Layar utama kasir

### 6.1 Beranda

Prioritas: Pesanan baru, Scan, Siap diambil, Terlambat, dan Shift kasir. Ringkasan dibatasi pada cabang aktif. KPI menampilkan periode dan waktu pembaruan. Saat data cache digunakan, tulis Terakhir diperbarui pukul 09.15 WIB.

### 6.2 Pesanan baru

1. Pelanggan: cari telepon atau nama; tampilkan kandidat duplikat dalam tenant yang sama. Walk in memakai data minimal.
2. Layanan: pilih favorit, isi measured quantity dan unit. Tampilkan berat tertagih bila berbeda.
3. Janji dan pengambilan: pilih regular atau express, tenggat, lokasi, serta instruksi.
4. Tinjau: tampilkan rincian quote, minimum, diskon, antar, total, dan ketentuan.
5. Konfirmasi: tunggu hasil server; baru tampilkan nomor nota dan opsi pembayaran.

Perubahan field tidak menghapus isi field lain. Harga lama yang kedaluwarsa menampilkan perbandingan nilai lama dan baru lalu meminta penerimaan quote baru. Draft offline tidak menampilkan badge Pesanan diterima.

### 6.3 Detail pesanan

Urutan konten: nomor dan status; pelanggan; tenggat; daftar layanan; bag dan lokasi; uang; timeline; tindakan sesuai izin. Operator dapat memperoleh versi detail yang tidak memuat nominal atau kontak penuh. Status Siap sebagian harus menunjukkan bagian yang belum siap, bukan hanya persen.

### 6.4 Pembayaran

Tampilkan total, dana sudah diterima, sisa, metode, nilai dibayar sekarang, serta uang tunai diserahkan jika relevan. Hitung kembalian sebagai informasi kasir dan simpan payment sebesar dana bersih diterima. Untuk gateway, tampilkan Menunggu verifikasi server sampai status sah tersedia. Jangan memberi animasi lunas hanya berdasarkan callback SDK.

### 6.5 Pengambilan

Scan bag atau nota, tampilkan seluruh bag serta QC dan lokasi, pilih bag utuh yang diserahkan, verifikasi syarat bayar atau persetujuan piutang, lalu catat penerima. Dialog final mencantumkan nomor pesanan dan jumlah bag. Bag yang sudah diserahkan tidak dapat dipilih ulang. Kegagalan server tidak diubah menjadi sukses lokal.

## 7 Produksi dan multi cabang

Papan produksi menampilkan lokasi aktif, tahap, tenggat, prioritas, unit kerja, dan operator. Sediakan filter express dan terlambat; pilihan filter bertahan dalam konteks lokasi. Memulai tugas dan menyelesaikan tugas mempunyai label berbeda.

Mode ringkas menggabungkan tahap secara terkendali sesuai workflow server. QC mempunyai checklist kelengkapan serta kualitas; kegagalan harus menyimpan alasan dan pilihan tindak lanjut. Mengulang pekerjaan menampilkan referensi pekerjaan asal.

Manifest mempunyai tiga tampilan: draft, pengiriman terkunci, dan penerimaan. Penerima memindai yang benar benar tiba. Tampilkan Dikirim 3 bag, diterima 2 bag, selisih 1 bag. Jangan menyediakan tombol Terima semua yang mengasumsikan barang hadir tanpa verifikasi yang disetujui SOP.

## 8 Kurir dan COD

Tugas menampilkan slot, status, alamat yang diperlukan, instruksi, dan kontak. Saat tugas selesai atau dialihkan, akses alamat mengikuti izin terbaru. Lokasi GPS tidak dilacak terus menerus tanpa kebutuhan serta kebijakan yang jelas.

COD menampilkan uang yang telah diterima dan kewajiban disetor. Setoran disajikan sebagai pemindahan tanggung jawab kas, bukan pembayaran baru pelanggan. Bukti penerimaan atau kegagalan memiliki retry upload tanpa menggandakan peristiwa.

## 9 Keadaan pending konflik dan offline

| Keadaan | Pesan pengguna | Tindakan |
|---|---|---|
| Request timeout | Status penyimpanan belum diketahui. Sedang memeriksa hasil. | Query atau retry command yang sama; jangan buat order baru |
| Draft offline | Draft tersimpan di perangkat. Belum menjadi pesanan terkonfirmasi. | Sinkronkan saat online |
| Konflik versi | Data telah berubah di perangkat lain. | Muat versi terbaru dan tinjau perubahan |
| Quote kedaluwarsa | Harga atau ketentuan telah diperbarui. | Bandingkan dan setujui quote baru |
| Pembayaran pending | Pembayaran belum terverifikasi. | Cek status; hindari anjuran bayar ulang sebelum rekonsiliasi |
| Printer gagal | Pesanan tersimpan, nota belum tercetak. | Cetak ulang atau kirim nota digital |
| Upload gagal | Foto belum terkirim. | Retry foto, tanpa mengulang pesanan |
| Akses dicabut | Akses pada cabang ini telah berubah. | Kunci konteks dan kembali memilih akses yang masih sah |
| Cursor lama | Data perlu disinkronkan ulang. | Pertahankan draft yang aman dan lakukan bootstrap ulang |

Layar sinkronisasi menampilkan jumlah pending, berhasil, konflik, dan ditolak, dengan alasan yang dapat ditindaklanjuti. Jangan membiarkan tombol Hapus semua antrean menghilangkan bukti uang atau draft tanpa penjelasan dan kewenangan. Sebelum logout, tampilkan draft belum terkirim dan ikuti kebijakan perlindungan akun; akun berikutnya tidak boleh melihat draft tersebut.

## 10 Pelaporan dan paket

Laporan selalu menyebut basis tanggal, periode, cabang, zona waktu, dan watermark. Penjualan, penerimaan, piutang, refund, serta setoran dipisahkan. Drill down memakai filter yang sama dengan ringkasan. Ekspor menunjukkan queued, running, ready, expired, atau failed.

Halaman paket memperlihatkan batas yang dipakai, fitur, tanggal berlaku, biaya tambahan, serta dampak downgrade. Jangan mengunci akses ke histori tanpa pesan dan jalur ekspor yang sesuai kebijakan. Konfirmasi upgrade tidak boleh mengklaim aktif sebelum pembayaran SaaS tervalidasi.

## 11 Bahasa dan format lokal

- Bahasa awal Indonesia; istilah teknis seperti tenant dan outbox tidak masuk alur pelanggan bila tidak diperlukan.
- Format uang `Rp71.600`; API tetap integer `71600`.
- Tampilkan `2,3 kg` dan `3 kg tertagih`; unit tidak disembunyikan dalam placeholder.
- Parser input mengikuti locale; cegah interpretasi ambigu pemisah ribuan dan desimal, terutama berat.
- Waktu menampilkan zona cabang bila dibutuhkan, misalnya `28 Okt 2026 14.00 WIB`. API tetap UTC.
- Nama pelanggan asli tidak digunakan pada mockup publik atau telemetry. Gunakan data sintetis.

## 12 Kinerja visual

Render list secara lazy, subscribe state pada bagian widget yang diperlukan, cache thumbnail dengan batas, dan hindari efek visual mahal pada daftar kerja. Gambar besar diunggah dan diproses terpisah dari transaksi kasir. Jangan menampilkan ribuan record dalam satu tabel web.

Evaluasi startup, jank, memori, dan ukuran build pada perangkat acuan PRD. Ukur build profile untuk diagnosis dan release untuk hasil penerimaan. Screenshot visual tidak membuktikan integritas transaksi ataupun performa.

## 13 Bukti penerimaan desain

- [ ] Prototipe kasir diuji staf outlet kecil dan jaringan minimal tiga cabang.
- [ ] Pesanan contoh PRD menunjukkan minimum, total, DP, dan sisa dengan benar.
- [ ] Semua status pending, offline, konflik, error, dan kosong memiliki tampilan serta tindakan pemulihan.
- [ ] Teks 200 persen, keyboard, pembaca layar, dan kontras diuji pada layar inti.
- [ ] Pergantian akun atau tenant tidak menampilkan data konteks lama.
- [ ] Printer, scanner, dan ukuran nota diuji pada perangkat fisik yang dinyatakan didukung.
- [ ] Tindakan uang dan penyerahan menampilkan objek serta nilai sebelum konfirmasi.
- [ ] Waktu input, startup, dan rendering dicatat pada perangkat sasaran.

## 14 Sumber resmi

- [W3C contrast minimum](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html)
- [Flutter architecture recommendations](https://docs.flutter.dev/app-architecture/recommendations)
- [Flutter performance](https://docs.flutter.dev/perf/best-practices)

Palet, navigasi, serta batas layar merupakan keputusan desain produk ini dan dapat disempurnakan setelah uji pengguna tanpa mengubah aturan transaksi.
