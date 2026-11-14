# Product Requirements Document Laundry SaaS

Versi 1.1 · 28 Oktober 2026 · Status usulan untuk implementasi

[PRD](prd.md) · [TRD](trd.md) · [Database](erd.md) · [UI UX](ui_guidelines.md) · [Aturan AI](agents.md) · [Keamanan](security.md) · [Rencana](todo.md)

## 1 Tujuan dan batas dokumen

Produk menyediakan perangkat operasional laundry berbasis langganan untuk satu outlet hingga jaringan cabang dengan pusat produksi. Dokumen ini menetapkan perilaku dan hasil yang harus diterima pengguna. Cara implementasi terdapat pada [trd.md](trd.md), struktur penyimpanan pada [erd.md](erd.md), dan urutan pekerjaan pada [todo.md](todo.md).

Versi Markdown ini menurunkan dan merinci PRD Word sebelumnya, dengan mempertahankan FR01 sampai FR48 serta prioritasnya. Penjelasan baru memperjelas aturan keuangan, unit fisik, sinkronisasi, dan keterlacakan implementasi. Dokumen ini belum menyatakan aplikasi, integrasi, atau pengujian telah selesai dibangun.

### 1.1 Sasaran

- Kasir mencatat pesanan dengan cepat dan mendapatkan nota yang konsisten.
- Operator mengetahui pekerjaan, lokasi barang, serta tenggat yang harus dipenuhi.
- Pemilik melihat penjualan, uang masuk, piutang, dan kinerja cabang dengan definisi yang jelas.
- Pengelola platform melayani banyak organisasi tanpa membuka data antar pelanggan SaaS.
- Struktur produk mendukung pertumbuhan cabang melalui konfigurasi dan entitlement dalam satu basis kode.

### 1.2 Asumsi awal

Pasar awal Indonesia, mata uang IDR, satu badan usaha per tenant pada MVP, Android untuk staf, dan web untuk pemilik serta admin. Flutter iOS dan aplikasi pelanggan masuk pengembangan berikutnya. Harga paket, metode pajak, model merchant, serta retensi final harus ditetapkan pemilik produk sebelum peluncuran berbayar.

P0 wajib sebelum pilot. P1 wajib sebelum kemampuan jaringan atau pertumbuhan terkait dijual. P2 hanya dikembangkan sesudah kebutuhan dan biaya pelayanannya tervalidasi. Model tenant dan cabang tersedia sejak P0 meskipun transfer serta konsolidasi lengkap dirilis pada P1.

## 2 Segmen dan paket

| Aspek | Starter | Business | Enterprise |
|---|---|---|---|
| Sasaran | Satu outlet | Jaringan dua sampai sepuluh cabang | Jaringan kompleks dan kontrak khusus |
| Batas usulan | Satu cabang dan tiga staf aktif | Sampai sepuluh cabang dan tiga puluh staf aktif | Ditetapkan per kontrak |
| Produksi | Mode ringkas | Tahapan dan pusat produksi | Kapasitas serta alur lanjutan |
| Logistik | Pengambilan di outlet | Manifest, antar jemput, kurir | Integrasi mitra sesuai kebutuhan |
| Pelaporan | Outlet dan kas harian | Konsolidasi cabang | Analitik dan ekspor terjadwal |
| Isolasi | Database bersama yang diisolasi | Database bersama yang diisolasi | Opsi database atau deployment tersendiri |

Batas di atas merupakan hipotesis paket, bukan hardcode pada schema. Jangan menghubungkan kapasitas infrastruktur hanya dengan jumlah cabang. Tagihan SaaS dan pembayaran layanan laundry adalah dua alur uang berbeda. Biaya pesan, gateway, tambahan penyimpanan, atau deployment khusus dapat menjadi add on yang transparan.

## 3 Pengguna dan hak akses

| Peran | Kebutuhan | Pembatas utama |
|---|---|---|
| Admin platform | Tenant, paket, tagihan SaaS, kesehatan layanan | Tidak membaca rincian bisnis tenant secara default |
| Pemilik tenant | Cabang, tarif, staf, kebijakan, laporan | Hanya organisasinya sendiri |
| Manajer area | Persetujuan dan pemantauan | Cabang yang ditugaskan |
| Kepala cabang | Produksi, shift, selisih, transfer | Cabang dan lokasi yang ditugaskan |
| Kasir | Pelanggan, pesanan, pembayaran, nota | Diskon dan refund mengikuti izin serta batas nominal |
| Operator | Antrian, scan, pengerjaan, QC | Lokasi tugas; data keuangan dan kontak dibatasi |
| Kurir | Tugas, alamat, bukti, COD | Penugasan aktif dan kewajiban setorannya |
| Keuangan | Piutang, rekonsiliasi, ekspor | Cakupan cabang yang diotorisasi |
| Auditor | Bukti dan histori | Baca saja |
| Pelanggan akhir | Tracking, pengambilan, pembayaran | Pesanan miliknya atau tracking minimal dengan token |

Satu identitas login dapat memiliki beberapa keanggotaan tenant. Pergantian tenant harus eksplisit dan memisahkan cache serta draft. Peran hanya mengelompokkan permission; server tetap memeriksa tindakan, objek, cabang, lokasi, dan batas nominal.

## 4 Domain dan alur layanan

### 4.1 Istilah yang tidak boleh disamakan

| Istilah | Makna |
|---|---|
| Tenant | Organisasi pelanggan SaaS |
| Cabang penerima | Asal penjualan dan tanggung jawab pelanggan |
| Lokasi produksi | Tempat pekerjaan dilaksanakan; dapat melayani beberapa cabang |
| Lokasi fisik | Posisi bag saat ini atau status dalam perjalanan |
| Order line | Layanan yang dipesan dan dasar tagihan |
| Bag | Wadah fisik berlabel; pada MVP satu bag hanya berisi barang satu pesanan |
| Bag content | Alokasi kuantitas fisik suatu order line ke bag |
| Garment | Item per potong yang dilacak bila layanan membutuhkannya |
| Invoice | Kewajiban bayar yang dibekukan setelah diterbitkan |
| Payment attempt | Upaya pembayaran yang belum tentu berhasil |
| Payment | Penerimaan dana yang sudah sah |

### 4.2 Alur normal

1. Pilih pelanggan atau identitas walk in dan catat kondisi awal.
2. Pilih layanan, ukur berat atau jumlah, dan buat penawaran harga serta tenggat.
3. Konfirmasikan penawaran; server menerbitkan pesanan, invoice, label, dan bukti penerimaan.
4. Catat DP, pembayaran penuh, atau piutang yang disetujui.
5. Jalankan tugas produksi sesuai template layanan dan lokasi yang ditugaskan.
6. Lakukan QC; kegagalan membuka pekerjaan ulang dengan jejak asal.
7. Letakkan barang pada rak atau lokasi pengambilan yang tepat dan beri pemberitahuan.
8. Verifikasi unit, penerima, serta syarat pembayaran sebelum penyerahan.
9. Selesaikan pesanan dan rekonsiliasi uang; komplain lanjutan tetap tertaut pada histori.

Mode ringkas menampilkan Diterima, Diproses, Siap Diambil, dan Diserahkan. Mode rinci memperlihatkan sorting, washing, drying, ironing, packing, dan QC sesuai template. Keduanya memakai model tugas yang sama.

### 4.3 Status

| Objek | Nilai canonical |
|---|---|
| Order | `draft`, `accepted`, `in_progress`, `partially_ready`, `ready`, `partially_handed_over`, `completed`, `cancelled` |
| Tugas | `queued`, `in_progress`, `done`, `blocked`, `skipped` |
| Payment attempt | `pending`, `succeeded`, `failed`, `expired`, `cancelled` |
| Keseimbangan invoice | `unpaid`, `partially_paid`, `paid`; keadaan overallocated menjadi pengecualian rekonsiliasi |
| Transfer | `draft`, `dispatched`, `partially_received`, `received`, `disputed`, `cancelled` |
| Delivery | `requested`, `assigned`, `picked_up`, `in_transit`, `delivered`, `failed`, `cancelled` |
| Draft lokal | `local_draft`, `pending_sync`, `synced`, `conflict`, `rejected` |

Status produksi, keuangan, pengiriman, dan sinkronisasi tidak disatukan menjadi satu enum. `completed` berarti seluruh bagian aktif telah diserahkan dan sisa tagihan telah dibayar atau dipindahkan menjadi piutang yang disetujui. Pembatalan tidak menghilangkan uang ataupun penguasaan fisik barang.

## 5 Aturan bisnis yang mengikat

### BR01 Isolasi usaha dan cabang

Cabang berada dalam tenant yang sama; perpindahan produksi tidak mengubah asal omzet. Data pelanggan tidak dibagikan lintas tenant. Waralaba dengan badan usaha independen perlu keputusan tersendiri sebelum penggabungan data.

### BR02 Kuantitas fisik dan kuantitas tertagih

Simpan `measured_quantity` dan `billable_quantity` secara terpisah. Dengan minimum 3 kg, barang fisik 2,3 kg tetap ditransfer sebagai 2,3 kg walaupun ditagih 3 kg. Jumlah potong opsional pada layanan kiloan tidak boleh dijumlahkan dengan satuan kg. Harga minimum bukan tambahan berat fisik.

### BR03 Pemilihan harga

Prioritas usulan P1: harga kontrak pelanggan pada cabang, harga kontrak pelanggan, harga cabang, kemudian harga tenant. P0 memakai dua tingkat terakhir. Pada kombinasi layanan, tier, scope, dan periode yang sama tidak boleh ada harga efektif ambigu. Server menyimpan asal harga, versi, unit, pembulatan, diskon, dan ketentuan sebagai snapshot saat konfirmasi.

### BR04 Pembulatan dan nilai uang

Gunakan integer rupiah untuk nominal, decimal untuk kuantitas, dan aritmetika decimal untuk tarif serta persentase. Pembulatan nilai line mengikuti aturan half up ke rupiah sesudah perhitungan; pembulatan berat dilakukan lebih dahulu menurut kelipatan layanan. Nilai rupiah bulat tidak memakai float biner. Pajak inklusif atau eksklusif hanya diaktifkan setelah konfigurasi usaha disahkan.

### BR05 Contoh acuan

| Komponen | Nilai |
|---|---|
| Kiloan | 2,3 kg terukur; minimum 3 kg; 3 × Rp8.000 = Rp24.000 |
| Satuan | 2 bed cover × Rp25.000 = Rp50.000 |
| Subtotal layanan | Rp74.000 |
| Diskon layanan | 10 persen = Rp7.400 |
| Antar | Rp5.000; tidak ikut diskon pada contoh |
| Total tanpa pajak | Rp71.600 |
| DP | Rp30.000 |
| Sisa | Rp41.600 |

### BR06 Revisi invoice dan refund

Invoice terbit tidak diedit langsung. Koreksi memakai adjustment bertanda positif atau negatif dengan alasan dan persetujuan. Refund mengembalikan dana dari payment tertentu, sedangkan credit note mengurangi tagihan; kedua tindakan mempunyai akibat berbeda dan dapat diperlukan bersamaan. Pending refund belum mengurangi alokasi final, tetapi mencadangkan batas pengembalian agar dua permintaan tidak melampaui pembayaran asal.

### BR07 Produksi dan penguasaan barang

Barang hanya siap jika tahap wajib dan QC terpenuhi. Serah terima sebagian harus menyebut unit yang diserahkan. Pada P0 pengambilan sebagian dilakukan antar bag utuh; pemecahan bag harus menghasilkan label dan riwayat baru sebelum penyerahan. Barang dalam transfer tidak dapat dijadwalkan ke transfer aktif lain.

### BR08 Konsistensi perintah

Retry command yang sama mengembalikan hasil sama. Key sama dengan payload berbeda ditolak. Perubahan data usang menghasilkan konflik, bukan overwrite otomatis. Audit, receipt command, dan outbox disimpan bersama transaksi inti. Callback mobile bukan bukti pembayaran berhasil.

### BR09 Dukungan offline

P0 menyediakan baca cache, draft, dan antrean foto. Finalisasi pesanan memvalidasi penawaran di server. Pembayaran, debit saldo, penerimaan transfer, serta penyerahan barang memerlukan online pada MVP. Nota darurat mengikuti SOP dan harus direkonsiliasi; UI tidak menampilkannya sebagai transaksi server final.

### BR10 Langganan dan data

Downgrade tidak menghapus histori. Batas paket diberlakukan atomik di server. Usulan masa tenggang tujuh hari disertai rencana penyelesaian pesanan aktif. Sesudahnya mode baca dan ekspor mengikuti kontrak. Penghapusan data merupakan proses tersendiri dengan kebijakan retensi yang disahkan.

## 6 Persyaratan fungsional

Setiap ID berikut harus tetap muncul dalam backlog dan bukti UAT. Kriteria penerimaan adalah perilaku minimum; rincian transaksi dan kontrol akses pada dokumen teknis tetap berlaku.

## Kebutuhan fungsional Tenant dan identitas

### FR01 Pendaftaran dan penyediaan tenant

**Prioritas:** P0.

Pemilik membuat organisasi, memilih paket uji coba, zona waktu, cabang pertama, dan profil usaha. Provisioning harus dapat diulang dengan aman.

**Kriteria penerimaan:** Pengiriman ulang permintaan pendaftaran yang sama tidak membuat tenant atau langganan ganda; pemilik dapat menyelesaikan onboarding yang terputus.

### FR02 Isolasi organisasi

**Prioritas:** P0.

Seluruh data bisnis, cache, file, job, dan ekspor harus terikat tenant yang telah diotorisasi. Tenant tidak diambil begitu saja dari input pengguna.

**Kriteria penerimaan:** Akun tenant A gagal membaca, mengubah, menebak tracking privat, maupun mengunduh ekspor tenant B melalui seluruh endpoint yang diuji.

### FR03 Login dan perangkat

**Prioritas:** P0.

Sediakan login, pemulihan akun, daftar sesi perangkat, pencabutan token, serta autentikasi tambahan bagi pemilik dan admin platform.

**Kriteria penerimaan:** Perangkat yang dicabut langsung ditolak pada permintaan berikutnya; pemulihan akun tidak membocorkan apakah suatu akun terdaftar.

### FR04 Keanggotaan dan izin

**Prioritas:** P0.

Pemilik mengundang staf, menetapkan peran, membatasi cabang, dan menonaktifkan akses tanpa menghapus histori pekerjaan.

**Kriteria penerimaan:** Kasir yang hanya ditugaskan ke cabang A tidak dapat bertransaksi pada B dengan mengganti parameter permintaan.

### FR05 Profil dan cabang

**Prioritas:** P0.

Simpan alamat, nomor telepon, jam kerja, libur, zona waktu, format nota, dan daftar lokasi. Model cabang tersedia sejak MVP.

**Kriteria penerimaan:** Pesanan lama tetap terhubung ke cabang yang dinonaktifkan; cabang nonaktif tidak menerima pesanan baru.

### FR06 Jejak audit

**Prioritas:** P0.

Catat pelaku, waktu server, perangkat, tenant, objek, tindakan, alasan, dan perubahan relevan pada transaksi sensitif.

**Kriteria penerimaan:** Diskon, refund, perubahan izin, revisi tenggat, serta akses bantuan dapat ditelusuri; pengguna operasional tidak dapat menghapus catatan audit.

## Kebutuhan fungsional Layanan dan pelanggan

### FR07 Katalog layanan

**Prioritas:** P0.

Kelola layanan kiloan dan satuan, unit, minimum, pembulatan, express, estimasi selesai, serta tahapan produksi per layanan.

**Kriteria penerimaan:** Layanan tanpa satuan atau tarif valid tidak dapat dipublikasikan; perubahan katalog tidak mengubah pesanan terkonfirmasi.

### FR08 Daftar harga cabang

**Prioritas:** P0.

Gunakan harga bawaan tenant dan override cabang yang efektif berdasarkan waktu. Rilis kontrak menambahkan harga khusus pelanggan.

**Kriteria penerimaan:** Server menjelaskan sumber harga yang terpilih; harga untuk periode yang saling bertabrakan ditolak atau memiliki prioritas yang tegas.

### FR09 Data pelanggan

**Prioritas:** P0.

Kasir mencari nama atau nomor telepon, membuat pelanggan, menyimpan alamat dan catatan yang relevan, serta memeriksa kemungkinan duplikat.

**Kriteria penerimaan:** Nomor dinormalisasi ke format konsisten; nomor sama di tenant berbeda tidak menggabungkan atau membuka data pelanggan.

### FR10 Riwayat dan persetujuan kontak

**Prioritas:** P0.

Tampilkan histori pesanan dan preferensi kontak sesuai izin. Pisahkan pemberitahuan operasional dari persetujuan promosi.

**Kriteria penerimaan:** Pelanggan yang menolak promosi tetap dapat memperoleh nota dan pemberitahuan layanan yang relevan melalui kanal yang disepakati.

### FR11 Kalender dan janji selesai

**Prioritas:** P0.

Tenggat dihitung dari layanan, jadwal cabang, hari libur, dan cut off. Rilis jaringan menambahkan kapasitas produksi.

**Kriteria penerimaan:** Layanan 24 jam tidak disamakan dengan satu hari kerja; perubahan janji menyimpan waktu lama, waktu baru, alasan, dan pelaku.

### FR12 Label dan identifikasi fisik

**Prioritas:** P0.

Buat nomor pesanan yang mudah dibaca dan QR bag. Pelacakan per potong dapat diaktifkan untuk layanan bernilai tinggi.

**Kriteria penerimaan:** Dua bag satu pesanan memiliki ID berbeda; scan menampilkan informasi sesuai peran tanpa memuat data pribadi pelanggan dalam QR mentah.

## Kebutuhan fungsional Kasir dan penerimaan

### FR13 Pembuatan pesanan

**Prioritas:** P0.

Kasir memilih pelanggan, layanan campuran, berat atau jumlah, tenggat, cabang penyerahan, dan instruksi khusus melalui alur singkat.

**Kriteria penerimaan:** Server menghitung ulang total dari katalog; nilai total atau tenant_id yang dimanipulasi di perangkat tidak dipercaya.

### FR14 Kondisi awal barang

**Prioritas:** P0.

Catat noda, kerusakan, warna, jumlah bag, foto yang diperlukan, serta persetujuan ketentuan layanan saat penerimaan.

**Kriteria penerimaan:** Bukti kondisi terhubung ke pesanan dan waktu penerimaan; unggahan gagal tidak menghasilkan klaim bahwa foto telah tersimpan.

### FR15 Konfirmasi dan nota

**Prioritas:** P0.

Terbitkan nota setelah transaksi server berhasil, dengan rincian harga, pembayaran, sisa, tenggat, dan token tracking terbatas.

**Kriteria penerimaan:** Nota cetak dan digital memiliki nomor serta total identik; kegagalan printer dapat dicetak ulang tanpa membuat pesanan baru.

### FR16 Diskon dan revisi

**Prioritas:** P0.

Terapkan batas diskon per peran. Revisi berat, tarif, atau layanan setelah konfirmasi memerlukan alasan dan penyesuaian tagihan.

**Kriteria penerimaan:** Kasir tidak dapat melewati batas diskon melalui API; revisi meninggalkan versi lama serta bukti persetujuan yang diperlukan.

### FR17 Pembatalan dan pengecualian

**Prioritas:** P0.

Pembatalan memeriksa tahap produksi, barang yang dikuasai, uang yang diterima, dan pihak yang menyetujui.

**Kriteria penerimaan:** Pesanan yang telah dibayar tidak hilang dari laporan; uang dikembalikan melalui alur refund dan keberadaan barang tetap dapat ditelusuri.

### FR18 Pencegahan pesanan ganda

**Prioritas:** P0.

Setiap perintah pembuatan membawa client_command_id dan idempotency key yang disimpan secara unik oleh tenant.

**Kriteria penerimaan:** Permintaan yang sama diulang setelah timeout menghasilkan ID serta hasil pesanan yang sama; payload berbeda pada key yang sama ditolak dengan 409.

## Kebutuhan fungsional Produksi dan kualitas

### FR19 Antrian pengerjaan

**Prioritas:** P0.

Operator melihat pekerjaan pada lokasi yang diizinkan, dikelompokkan berdasarkan tenggat, tahap, layanan, dan prioritas express.

**Kriteria penerimaan:** Urutan antrian memperlihatkan pesanan terlambat; pengguna hanya menerima data dalam cakupan tugasnya dengan paginasi.

### FR20 Tahap produksi

**Prioritas:** P0.

Scan atau pilih bag untuk memulai dan menyelesaikan tugas berdasarkan template layanan. Simpan operator serta waktu server.

**Kriteria penerimaan:** Tahap yang prasyaratnya belum selesai ditolak; mode ringkas menyimpan perkembangan yang konsisten dengan template rinci.

### FR21 Batch dan kapasitas

**Prioritas:** P1.

Gabungkan pekerjaan ke batch berdasarkan kompatibilitas bahan, warna, layanan, berat, serta mesin atau meja kerja.

**Kriteria penerimaan:** Jumlah alokasi tidak melebihi sisa tiap order line; operator mendapat peringatan ketika kapasitas mesin yang dikonfigurasi terlampaui.

### FR22 Pemeriksaan kualitas

**Prioritas:** P0.

QC memeriksa kelengkapan, kualitas, dan kemasan. Untuk usaha kecil operator yang sama boleh melakukan QC sesuai pengaturan.

**Kriteria penerimaan:** Bag yang belum lolos QC tidak dapat ditandai siap diserahkan; kegagalan menyimpan alasan dan bukti jika diperlukan.

### FR23 Pencucian ulang dan komplain

**Prioritas:** P0.

Buka tugas rewash atau kasus barang hilang dan rusak yang tertaut ke order line serta bukti awal.

**Kriteria penerimaan:** Rewash tidak menggandakan omzet; kompensasi memerlukan persetujuan dan penyesuaian tagihan atau refund yang dapat diaudit.

### FR24 Rak dan serah terima

**Prioritas:** P0.

Catat rak penyimpanan dan verifikasi bag atau item saat pengambilan. Atur penyerahan penuh atau sebagian secara eksplisit.

**Kriteria penerimaan:** Satu unit tidak dapat diserahkan dua kali; penyerahan sebagian menyisakan daftar yang belum diserahkan dan bukti penerimanya.

## Kebutuhan fungsional Transfer dan logistik

### FR25 Penugasan lokasi produksi

**Prioritas:** P1.

Tetapkan pusat produksi per layanan dan cabang penerima. Penugasan dapat diubah melalui persetujuan ketika kapasitas terganggu.

**Kriteria penerimaan:** Perubahan lokasi tidak memindahkan omzet asal; seluruh bagian yang berpindah memiliki pergerakan fisik dan penanggung jawab.

### FR26 Manifest pengiriman

**Prioritas:** P1.

Pengirim memilih bag atau item yang tersedia, memindai keberangkatan, dan mencatat kurir serta segel bila digunakan.

**Kriteria penerimaan:** Barang yang sedang dalam manifest aktif tidak dapat dikirim lagi; setelah dispatched manifest tidak dapat diedit tanpa proses koreksi.

### FR27 Penerimaan dan selisih transfer

**Prioritas:** P1.

Penerima memindai barang yang benar benar tiba. Sistem mencatat penerimaan sebagian, kekurangan, kelebihan, atau kerusakan.

**Kriteria penerimaan:** Lokasi fisik berubah hanya untuk unit yang diterima; selisih membuat kasus rekonsiliasi tanpa menandai seluruh manifest selesai.

### FR28 Permintaan antar jemput

**Prioritas:** P1.

Pelanggan atau staf menentukan alamat, zona layanan, slot, estimasi biaya, dan kontak penerima. Berat awal dapat berupa estimasi.

**Kriteria penerimaan:** Harga estimasi diberi label jelas; total final setelah penimbangan memerlukan persetujuan perubahan sesuai ketentuan layanan.

### FR29 Tugas kurir

**Prioritas:** P1.

Dispatcher menugaskan kurir dan menampilkan urutan tugas manual. Kurir menyimpan bukti penjemputan, penerimaan, atau kegagalan.

**Kriteria penerimaan:** Kurir hanya melihat alamat tugas aktif; kiriman gagal menyimpan alasan dan jadwal ulang tanpa menganggap barang telah diserahkan.

### FR30 Kas kurir dan barang titipan

**Prioritas:** P1.

Catat COD dan barang yang dikuasai kurir sebagai tanggung jawab berbeda. Setoran dapat diverifikasi kasir atau keuangan.

**Kriteria penerimaan:** Penerimaan COD menciptakan kewajiban setoran; konfirmasi setoran tidak mencatat pendapatan pelanggan untuk kedua kalinya.

## Kebutuhan fungsional Pembayaran dan kas

### FR31 DP pelunasan dan piutang

**Prioritas:** P0.

Dukung pembayaran bertahap, gabungan metode, dan piutang dengan izin. Alokasikan penerimaan ke invoice secara eksplisit.

**Kriteria penerimaan:** Contoh tagihan Rp71.600 dan DP Rp30.000 menghasilkan sisa Rp41.600; percobaan pending belum mengurangi sisa.

### FR32 Gateway dan rekonsiliasi

**Prioritas:** P1.

Integrasikan kanal digital melalui adapter; validasi notifikasi server, merchant, nilai, mata uang, dan status yang sah.

**Kriteria penerimaan:** Callback aplikasi tidak menetapkan lunas; webhook berulang atau terlambat tidak menggandakan uang maupun menurunkan pembayaran sukses menjadi pending.

### FR33 Refund dan koreksi

**Prioritas:** P0.

Ajukan refund dengan transaksi asal, nominal, alasan, penyetuju, dan tujuan. Koreksi invoice menggunakan adjustment.

**Kriteria penerimaan:** Refund kumulatif tidak melebihi nilai yang dapat dikembalikan; kegagalan provider tidak dicatat sebagai uang berhasil dikembalikan.

### FR34 Shift kasir

**Prioritas:** P0.

Simpan kas awal, penerimaan tunai, biaya, setoran, pengembalian, kas fisik akhir, serta selisih. Batasi shift per kasir atau laci.

**Kriteria penerimaan:** Kas yang diharapkan dapat dihitung ulang dari ledger; pembukaan kembali shift memerlukan persetujuan dan tidak menghapus penutupan sebelumnya.

### FR35 Biaya dan transaksi nonpenjualan

**Prioritas:** P0.

Catat pengeluaran sederhana, setoran pemilik, dan perpindahan kas dengan kategori serta bukti. Pisahkan dari penjualan layanan.

**Kriteria penerimaan:** Setoran modal tidak menaikkan omzet; transfer antar laci menghasilkan debit dan kredit yang berpasangan.

### FR36 Pelaporan operasional

**Prioritas:** P0.

Sediakan penjualan bersih, penerimaan kas, piutang, refund, pekerjaan tertunda, dan ketepatan waktu dengan definisi metrik.

**Kriteria penerimaan:** Laporan menjelaskan dasar tanggal serta cabang; jumlah detail dapat direkonsiliasi terhadap ringkasan pada periode yang sama.

## Kebutuhan fungsional Pengelolaan SaaS

### FR37 Paket dan entitlement

**Prioritas:** P0.

Kelola paket, batas cabang, staf, fitur, kuota, dan periode aktif di server. Catat versi paket pada langganan.

**Kriteria penerimaan:** Dua permintaan penambahan staf serentak tidak melewati batas paket; fitur berbayar tidak dapat dibuka hanya dengan perubahan tampilan klien.

### FR38 Tagihan SaaS

**Prioritas:** P0.

Platform memiliki invoice langganan, metode pembayaran, masa tenggang, dan status langganan sendiri. MVP boleh memakai verifikasi manual tercatat.

**Kriteria penerimaan:** Pembayaran SaaS tidak masuk laporan kas tenant; aktivasi memerlukan bukti pembayaran yang tervalidasi dan tidak terjadi dua kali.

### FR39 Upgrade downgrade dan berhenti

**Prioritas:** P1.

Tampilkan waktu berlaku, biaya prorata bila dipilih, serta dampak fitur sebelum perubahan. Sediakan akses ekspor sesuai kebijakan kontrak.

**Kriteria penerimaan:** Downgrade yang melebihi batas memunculkan rencana penyesuaian; data historis tetap dapat ditelusuri dan tidak dihapus otomatis.

### FR40 Impor dan ekspor

**Prioritas:** P0.

Sediakan template impor pelanggan dan katalog dengan dry run, pemetaan, validasi, dan laporan baris gagal. Ekspor besar berjalan di worker.

**Kriteria penerimaan:** Impor ulang dapat menghindari duplikasi; file ekspor hanya dapat diunduh pemilik izin dalam tenant yang sesuai dan memiliki kedaluwarsa.

### FR41 Bantuan dan akses sementara

**Prioritas:** P0.

Staf dukungan mengakses data pelanggan hanya dengan otorisasi kasus, alasan, cakupan, dan masa berlaku. Semua sesi bantuan diaudit.

**Kriteria penerimaan:** Akses kedaluwarsa ditolak; dukungan tidak dapat melakukan pembayaran, refund, atau perubahan hak melalui penyamaran pengguna.

### FR42 Pemantauan dan penghentian layanan

**Prioritas:** P0.

Admin memantau kesehatan tenant, error, penyimpanan, dan antrean dengan data pribadi yang dibatasi. Terapkan proses ekspor serta penghapusan.

**Kriteria penerimaan:** Permintaan penghapusan memeriksa retensi transaksi dan backup sesuai kebijakan yang disahkan; penyelesaian dicatat dan dapat dibuktikan.

## Kebutuhan fungsional Pertumbuhan dan integrasi

### FR43 Stok bahan dan pembelian

**Prioritas:** P1.

Kelola deterjen, pewangi, kemasan, pembelian, mutasi, stok minimum, dan stock opname per lokasi. Pemakaian resep merupakan estimasi.

**Kriteria penerimaan:** Stok aktual berubah melalui ledger mutasi; pemakaian otomatis dapat direkonsiliasi dan tidak diklaim sebagai pengukuran sensor.

### FR44 Paket prabayar dan loyalitas

**Prioritas:** P2.

Simpan paket kg atau nominal, masa berlaku, aturan penggunaan, poin, serta riwayat mutasi terpisah dari uang kas.

**Kriteria penerimaan:** Pemakaian serentak tidak membuat saldo negatif; pengembalian mengikuti ledger asal dan versi ketentuan yang dibeli pelanggan.

### FR45 Pelanggan kontrak B2B

**Prioritas:** P1.

Dukung tarif hotel atau rumah sakit sesuai kebutuhan yang divalidasi, siklus invoice gabungan, batas kredit, dan bukti serah terima.

**Kriteria penerimaan:** Invoice gabungan tidak menagih order line yang sama dua kali; order tertaut tetap dapat dilacak per manifest dan unit layanan.

### FR46 Konsolidasi dan analitik

**Prioritas:** P1.

Bandingkan omzet asal, kas diterima, produktivitas produksi, keterlambatan, dan transfer per cabang dengan hak akses yang sesuai.

**Kriteria penerimaan:** Pergerakan internal dieliminasi dari omzet konsolidasi; laporan menyajikan waktu pembaruan dan definisi pembilang serta penyebut metrik.

### FR47 Integrasi mitra dan IoT

**Prioritas:** P2.

Sediakan API berizin dan adapter untuk akuntansi, timbangan, mesin, atau sensor. Perangkat IoT memakai gateway dan identitas perangkat tersendiri.

**Kriteria penerimaan:** Pesan perangkat tidak dapat menandai pembayaran lunas atau penyerahan selesai; data duplikat ditangani berdasarkan ID pesan dan waktu sumber.

### FR48 Aplikasi pelanggan dan white label

**Prioritas:** P2.

Validasi kebutuhan aplikasi pelanggan sebelum membuat binary tambahan. Awali dengan halaman tracking web, kemudian Flutter pelanggan bila adopsi terbukti.

**Kriteria penerimaan:** Pelanggan hanya dapat melihat pesanannya; konfigurasi merek tidak membutuhkan fork kode bisnis atau membuka data tenant lain.


## 7 Persyaratan nonfungsional

Semua angka berikut target yang harus diukur, bukan hasil benchmark atau SLA yang telah dijanjikan.

| ID | Kebutuhan dan kriteria awal |
|---|---|
| NFR01 | Baca API inti p95 ≤300 ms untuk 25 record ringkas; ukur sisi server tanpa RTT klien |
| NFR02 | Konfirmasi pesanan atau penerimaan tunai p95 ≤600 ms; tidak termasuk waktu provider |
| NFR03 | 5xx <0,5 persen pada profil uji; 409 bisnis dan 429 dilaporkan terpisah |
| NFR04 | Payload 25 record ≤75 KB JSON tak terkompresi; foto diambil terpisah |
| NFR05 | Android 10+, RAM 4 GB; interaksi utama p95 ≤2 detik pada 10 Mbps dan RTT 100 ms; cache awal ≤1 detik |
| NFR06 | Cold start release p95 ≤3 detik; jank <5 persen; target resident memory ≤200 MB pada alur kasir 15 menit; unduhan per perangkat ≤40 MB |
| NFR07 | Target availability pilot 99,5 persen per bulan; pertumbuhan 99,9 persen dengan pengukuran dari luar |
| NFR08 | RPO ≤15 menit; RTO pilot ≤4 jam dan pertumbuhan ≤1 jam; bukti restore wajib |
| NFR09 | Nol akses lintas tenant dan nol efek uang atau barang ganda pada seluruh uji kritis |
| NFR10 | Aksesibilitas, audit, dan pemulihan error memenuhi [ui_guidelines.md](ui_guidelines.md) dan [security.md](security.md) |

## 8 Ukuran keberhasilan produk

- Median pembuatan pesanan ulang ≤60 detik setelah pelatihan, dari pelanggan dipilih sampai nota tersimpan.
- ≥95 persen pesanan jatuh tempo pada periode selesai sebelum janji awal; pembatalan dikeluarkan, revisi dan rewash dilaporkan tersendiri.
- Semua selisih kas memiliki alasan dan penanggung jawab; tidak ditutupi melalui pengeditan histori.
- ≥80 persen staf pilot menjalankan tugas sesuai peran setiap minggu aktif setelah empat minggu operasi.
- Semua pesanan terkonfirmasi memiliki snapshot tarif, tenggat, lokasi, dan penerima awal.

Baseline diambil sebelum pilot. Ukuran waktu produksi memakai due_at awal agar perubahan janji tidak memperbaiki KPI secara semu.

## 9 Di luar lingkup awal

Akuntansi umum lengkap, payroll, pengurusan pajak, marketplace lintas tenant, dompet penampungan dana platform, split settlement mandiri, optimasi rute otomatis, AI untuk harga, dan kendali mesin IoT tidak termasuk MVP. P2 tetap memerlukan proposal fitur dan kriteria penerimaan sendiri.

## 10 UAT lintas modul

| ID | FR atau NFR | Hasil minimum |
|---|---|---|
| U01 | FR02 FR04 NFR09 | Manipulasi ID tenant, cabang, file, job, dan ekspor ditolak |
| U02 | FR07 FR13 FR31 | Contoh BR05 menghasilkan total dan sisa tepat |
| U03 | FR08 FR16 | Tarif baru tidak mengubah nota terkonfirmasi |
| U04 | FR18 | Timeout sesudah commit dan tiga retry menghasilkan satu pesanan |
| U05 | FR20 FR22 | Ready ditolak bila QC salah satu bagian belum lulus |
| U06 | FR24 | Dua perangkat tidak dapat menyerahkan unit yang sama dua kali |
| U07 | FR26 FR27 | Dua dari tiga bag diterima; satu tetap tertelusur sebagai selisih atau dalam perjalanan |
| U08 | FR31 FR32 | Webhook berulang dan urutan terbalik tidak menggandakan dana |
| U09 | FR33 | Refund bersamaan tidak melampaui nilai yang tersedia |
| U10 | FR34 FR35 | Saldo shift cocok dengan ledger dan setoran bukan penjualan baru |
| U11 | FR37 FR39 | Slot paket terakhir tidak dipakai dua kali; downgrade mempertahankan histori |
| U12 | FR40 FR41 | Ekspor terisolasi dan akses bantuan kedaluwarsa |
| U13 | NFR05 NFR09 | Draft tahan restart; konflik tidak menimpa transaksi server |
| U14 | NFR08 NFR09 | Replay worker tidak menggandakan efek; restore dibuktikan |
| U15 | NFR01 NFR06 | Profil beban dan perangkat acuan memenuhi target atau mencatat gap yang disetujui |
| U16 | FR36 FR46 | Produksi lintas lokasi tidak menggandakan omzet konsolidasi |

## 11 Keputusan produk

| ID | Keputusan | Pemilik dan batas waktu | Status |
|---|---|---|---|
| DEC01 | Satu badan usaha per tenant; cabang dan pusat produksi milik badan usaha tersebut berada dalam tenant yang sama; waralaba independen memakai tenant terpisah | Pemilik produk/pengguna, dikonfirmasi dalam sesi implementasi 31 Oktober 2026 | Disetujui untuk fondasi P0 |
| DEC02 | Dana laundry langsung ke merchant tenant; merchant platform hanya menerima langganan SaaS | Pemilik produk/pengguna, dikonfirmasi dalam sesi implementasi 31 Oktober 2026 | Disetujui; adapter gateway tetap P1 |
| DEC03 | Pajak, minimum, pembulatan, dan aturan diskon | Operasional sebelum acceptance pricing; disahkan pengguna 31 Oktober 2026 | Disetujui untuk fixture P0; pajak nonaktif sampai keputusan aktivasi |
| DEC04 | Penyerahan dengan piutang dan batas kredit | Pemilik usaha; disetujui 31 Oktober 2026 | Disetujui: piutang default Rp0 per pelanggan, limit dan jatuh tempo 7 hari ditetapkan owner; penyerahan diblokir bila outstanding melewati limit |
| DEC05 | Printer, scanner, Android, dan kebutuhan iOS | Operasional; disetujui 31 Oktober 2026 | Disetujui: Eppos 80 mm, portable Smartcom 80 mm, dan Eppos 58 mm; nota 80 mm dengan fallback 58 mm; label bag 50 × 30 mm; Android 10+ target API 35 RAM 4 GB; kamera + scanner Bluetooth; iOS tidak masuk P0; pilot Karya Laundry 1 cabang + 1 produksi, arsitektur tetap mendukung banyak cabang + satu pusat produksi |
| DEC06 | Kebijakan kompensasi dan bukti kondisi awal | Operasional; disetujui 31 Oktober 2026 | Disetujui: rewash/penggantian didahulukan; kompensasi tunai per kasus dengan bukti kondisi awal dan persetujuan owner; tanpa formula otomatis pada P0 |
| DEC07 | Retensi, consent, lokasi hosting, dan dukungan | Product owner; disetujui 31 Oktober 2026 | Disetujui: consent per tujuan/kanal, WhatsApp resmi + email fallback, hosting Indonesia dengan DB/object privat, retensi pada SEC16, grant support maksimum 24 jam |
| DEC08 | Harga paket dan biaya add on | Bisnis setelah biaya pilot tersedia | Terbuka |

### Keputusan implementasi T030 dan T076

- **T030 diskon:** hanya diskon persentase pada subtotal layanan; biaya antar tidak didiskon; nominal tetap masuk P1. Batas peran: kasir 0%, supervisor maksimum 10%, owner sampai 100%. Diskon di atas 10% memerlukan persetujuan owner yang berbeda dari pemohon, alasan wajib, dan approval terikat pada quote serta kedaluwarsa bersama quote.
- **T030 revisi:** revisi kuantitas atau penggantian layanan hanya sebelum status `ready`; tarif baru dihitung server dari katalog saat revisi, bukan nominal bebas. Setelah `ready`/handover, koreksi hanya melalui debit/credit note. Revisi memakai `expected_version` dan menyimpan snapshot lama; selisih invoice menjadi adjustment. Adjustment di atas Rp100.000 atau selisih di atas 10% memerlukan owner berbeda aktor.
- **T076 tracking:** token acak disimpan sebagai hash, berlaku 30 hari setelah handover, dicabut saat order dibatalkan atau token dibuat ulang. Rate limit 30/menit per IP dan 10/menit per token. Halaman hanya menampilkan status, estimasi tenggat, kontak outlet, dan jumlah bag; tanpa PII, nominal, foto, atau order lain.

### SOP organisasi dan merchant untuk T001

1. Pendaftaran satu organisasi membentuk satu tenant untuk satu badan usaha. Cabang pertama menjadi cabang penerima; cabang berikutnya memakai identitas dan pembatasan akses dalam tenant yang sama.
2. Cabang penerima tetap menjadi asal penjualan dan tanggung jawab pelanggan. Pusat produksi dapat menjadi lokasi tanpa cabang penjualan sendiri; penugasan atau perpindahan barang tidak memindahkan asal omzet.
3. Lokasi outlet, pusat produksi, gudang, serta rak mempunyai identitas dan penanggung jawab dalam tenant yang sama. Manifest dan konsolidasi jaringan P1 tidak membuka perpindahan data atau barang lintas tenant.
4. Waralaba dengan badan usaha independen mendaftar sebagai tenant berbeda. Pelanggan, harga, uang, dan laporan tidak dibagikan otomatis; federasi lintas badan usaha memerlukan kebutuhan serta keputusan tersendiri.
5. Satu pengguna dapat menjadi anggota beberapa tenant, tetapi pilihan konteks eksplisit dan izin masing-masing tetap terpisah. Admin platform tidak menjadi anggota operasional tenant secara otomatis.
6. Pembayaran pelanggan laundry dicatat untuk merchant tenant. Merchant platform menerima pembayaran langganan/add on SaaS melalui alur dan ledger platform yang terpisah; platform tidak menampung dana laundry atau melakukan split settlement mandiri.

Keputusan ini menuntaskan batas organisasi T001 untuk pembangunan fondasi; angka harga, aturan kredit, perangkat, dan kebijakan data tetap mengikuti DEC03–DEC08.

## 12 Aturan perubahan

Perubahan perilaku memperbarui FR atau menambah FR baru tanpa mengganti arti ID lama diam diam. Perubahan teknis dijelaskan dalam ADR di TRD. Jika schema, izin, API, UI, atau acceptance berubah, perbarui dokumen terkait dalam perubahan yang sama. Task hanya selesai setelah bukti sesuai [todo.md](todo.md) tersedia.
