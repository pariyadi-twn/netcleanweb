# Kebijakan Keamanan Laundry SaaS

Versi 1.1 · 28 Oktober 2026 · Status usulan untuk implementasi

[PRD](prd.md) · [TRD](trd.md) · [Database](erd.md) · [UI UX](ui_guidelines.md) · [Aturan AI](agents.md) · [Keamanan](security.md) · [Rencana](todo.md)

## 1 Tujuan dan status

Kebijakan ini menetapkan kontrol minimum untuk menjaga data tenant, uang, penguasaan barang, dan operasional platform. Ini spesifikasi proyek yang perlu diterapkan dan diuji, bukan sertifikasi kepatuhan atau pernyataan sistem telah aman. Kebutuhan produk ada pada [prd.md](prd.md), mekanisme teknis pada [trd.md](trd.md), dan schema pada [erd.md](erd.md).

Pengelola produk menetapkan penanggung jawab keamanan, operasional, persetujuan keuangan, dan permintaan data sebelum berbayar. Target retensi dan waktu teknis di bawah adalah usulan operasional yang perlu disahkan; jangan menganggapnya sebagai batas hukum yang berlaku otomatis.

## 2 Klasifikasi data dan batas kepercayaan

| Kelas | Contoh | Kontrol dasar |
|---|---|---|
| Publik | Profil outlet dan status tracking minimal | Tidak mengandung kontak atau rincian pelanggan |
| Internal | Katalog dan SOP tenant | Autentikasi dan izin tenant |
| Rahasia usaha | Order, tarif kontrak, laporan, kas | Pembatasan cabang, audit, ekspor berizin |
| Data pribadi | Nama, telepon, alamat, foto kondisi | Minimalisasi, akses sesuai tugas, retensi terkelola |
| Sangat sensitif | Password, token, MFA secret, server key, backup key | Hash atau enkripsi, secret manager, tidak masuk log atau klien |

Browser, mobile, QR, header, file, webhook, dan data perangkat IoT adalah input yang belum dipercaya. Server memverifikasi asal serta izin. Database dan worker berada pada jaringan privat; admin platform dan support tidak memperoleh hak penuh atas usaha tenant secara default.

## 3 SEC01 Isolasi tenant

- Tenant aktif harus memiliki membership sah; `X-Tenant-ID` dan subdomain hanya pemilih konteks.
- Semua akses objek memeriksa tenant, scope cabang atau lokasi, dan tindakan. Terapkan juga pada pencarian, bulk action, file, ekspor, tracking privat, dan replay command.
- Gunakan FK gabungan tenant dan parent serta RLS untuk data bisnis sensitif. Role runtime bukan owner, bukan superuser, dan tanpa BYPASSRLS.
- Konteks RLS disetel lokal pada transaksi, menggunakan parameter binding dan internal tenant ID yang telah diotorisasi.
- Query tanpa konteks ditolak. Identity bootstrap serta control plane menggunakan layanan minimal yang eksplisit, bukan role yang mematikan seluruh isolasi.
- Uji tenant A terhadap objek tenant B, termasuk raw query, job, cache, dan koneksi yang dipakai ulang. UUID tidak menggantikan pemeriksaan ini.

**Bukti:** U01 dan test PostgreSQL dengan role runtime; test tidak boleh lulus hanya karena menggunakan role migration.

## 4 SEC02 Autentikasi dan sesi

Password memakai hasher framework yang didukung. Login, reset, penerbitan token, OTP bila dipakai, dan tracking dilindungi rate limit dengan respons yang tidak mengungkap akun. Reset atau undangan menggunakan token acak, sekali pakai, dan kedaluwarsa; token server disimpan sebagai hash.

Token mobile per perangkat mempunyai expiry eksplisit, usulan staf 12 jam, dan dapat dicabut. Revocation berlaku pada permintaan online berikutnya. Cache offline pada perangkat yang tidak terhubung tidak dapat dijamin terhapus dari jarak jauh; kebijakan penguncian dan batas umur cache harus mengurangi paparan. MFA wajib untuk pemilik dan admin platform. Recovery MFA memakai mekanisme yang diaudit, bukan perubahan role melalui permintaan support biasa.

Web menggunakan cookie Secure, HttpOnly, SameSite sesuai deployment, rotasi session setelah login atau perubahan hak, serta CSRF. PIN kasir hanya membuka sesi perangkat terdaftar dalam kebijakan yang disepakati; PIN tidak menjadi token server universal.

## 5 SEC03 Otorisasi dan akses bantuan

Permission bersifat deny by default. Kewenangan refund, diskon, perubahan tarif, penyerahan berpiutang, ekspor, dan pembukaan shift ulang disahkan pemilik produk. Tindakan sensitif menggunakan persetujuan actor berbeda bila kebijakan mengharuskan; tindakan rutin dalam izin yang sudah diberikan tidak ditambah persetujuan berulang tanpa kebutuhan.

Support memerlukan grant dengan tenant, kasus, cakupan baca, waktu berlaku, dasar persetujuan, dan jejak akses. Grant tidak mengizinkan refund, pembayaran, maupun peningkatan peran melalui impersonation. Akses break glass untuk insiden mempunyai alasan dan peninjauan setelah kejadian; tidak menjadi kredensial harian.

Perubahan membership atau assignment menaikkan permission version dan membatalkan cache izin. Otorisasi ulang pada generate serta download ekspor. Pada aplikasi mobile, perubahan scope yang terdeteksi memicu penguncian atau purge cache terkait sebelum tampilan baru.

## 6 SEC04 Secret dan enkripsi

Semua kanal memakai TLS sesuai kemampuan penyedia yang didukung. Kredensial database, Redis, gateway, signing, MFA, dan backup tidak masuk repository, image, Flutter binary, prompt AI, atau log. Gunakan secret manager atau penyimpanan terenkripsi yang terkelola dengan akses minimal dan pemisahan environment.

Merchant tenant memakai secret masing masing atau model submerchant yang sah. Rotasi key mencatat pemilik, dependensi, rencana transisi, verifikasi, dan pencabutan lama. Jangan mengganti APP_KEY atau kunci enkripsi secara sembarang karena data lama mungkin tidak dapat dibaca. Kunci backup disimpan terpisah dengan prosedur pemulihan yang diuji.

Enkripsi transport dan disk tidak menggantikan authorization. Hash token atau password tidak dipakai untuk data yang harus didekripsi; enkripsi bukan pengganti hash password. Jangan membuat algoritma kriptografi sendiri.

## 7 SEC05 Validasi input dan pembatasan sumber daya

FormRequest atau validator memeriksa tipe, panjang, enum, batas angka, UUID, kepemilikan FK, dan hubungan parent. `tenant_id`, role, harga final, status succeeded, serta actor tidak dapat dimasukkan lewat mass assignment dari klien. Prepared statement wajib untuk SQL; nama kolom sort dan filter diambil dari allowlist.

Batasi pagination maksimum 100 record, sync batch usulan maksimum 50 command, ukuran body JSON sesuai endpoint, dan file terpisah dari command JSON. Batas dipasang di ingress serta aplikasi. Bulk import memakai dry run dan kuota; satu tenant tidak dapat membuat ekspor atau request provider tak terbatas.

Escape teks pada output HTML. Field catatan tidak mengeksekusi HTML atau script. CSV diekspor dengan perlindungan formula injection. Server tidak mengambil URL arbitrary dari pelanggan tanpa kontrol SSRF; integrasi provider menggunakan endpoint yang ditetapkan operator.

## 8 SEC06 Integritas uang dan barang

Perintah kritis idempoten dengan unique key persisten dan payload hash. Authorize sebelum mengembalikan hasil replay. Harga server dibekukan; jumlah fisik berbeda dari jumlah tertagih. Transaksi, lock, expected version, serta constraint mencegah race pada saldo, refund, kuota, transfer, dan handover.

Webhook diperiksa dengan mekanisme resmi provider serta merchant, order reference, nominal, currency bila tersedia, dan status. Simpan inbox durable sebelum 2xx. Jangan mempercayai callback mobile, alamat IP saja, atau tenant ID pada body. External reference tetap digunakan saat retry; timeout berarti perlu pengecekan, bukan izin membuat tagihan atau refund kedua.

Payment, allocation, adjustment, refund, dan cash ledger mempunyai jejak koreksi. Tidak ada tombol menghapus uang atau barang final. Pembayaran langganan SaaS tidak masuk kas tenant. Pending refund tetap reserved sampai hasil provider pasti; pemrosesan ulang tidak membuat efek ganda.

**Bukti:** U02–U10 dan U16, termasuk dua proses serentak pada database nyata.

## 9 SEC07 Job event dan proses berumur panjang

Job memuat tenant ID dan identitas objek minimum, memasang konteks sendiri, mengotorisasi tindakan layanan, dan membersihkan state pada finally. Hindari serialize seluruh model pelanggan beserta PII ke queue. Ganti penggunaan singleton atau static state yang membawa tenant lama.

Outbox dan efek inti satu transaksi. Consumer receipt dan efek database satu transaksi. Efek HTTP menggunakan intent, reference tetap, timeout, serta rekonsiliasi. `dispatched_at` bukan bukti job selesai. Hanya role worker yang memerlukan queue mendapat akses; dashboard Horizon dilindungi autentikasi serta otorisasi operator.

Octane atau runtime serupa tidak diaktifkan sebelum uji tenant bergantian, state bocor, dan memori lulus. Job yang gagal tidak direplay massal tanpa memeriksa idempotensi serta akibat eksternalnya.

## 10 SEC08 Cache pencarian dan data turunan

Cache key mencakup tenant, scope cabang, permission version bila relevan, serta versi data. Cache response pribadi tidak dipublikasikan ke CDN. Jangan menyimpan keputusan otorisasi berumur panjang tanpa invalidasi. Pencarian dan agregasi memakai batas yang sama dengan data detail; angka ringkasan pun dapat membocorkan usaha lain.

Redis antrean berbeda dari Redis cache dan berada pada jaringan privat. Eviction cache tidak boleh membuang job. Pemulihan queue mengandalkan outbox serta rekonsiliasi, bukan asumsi persistence tidak pernah kehilangan data.

## 11 SEC09 Upload unduhan dan tracking

Object key dibuat server dan file privat. Verifikasi batas usulan lima MB per foto, tipe yang diizinkan, magic bytes, dimensi, dan checksum; jangan hanya percaya extension. Foto awal dapat memakai JPEG, PNG, atau WebP yang telah didukung; PDF bukti memerlukan validator tersendiri. Tolak file aktif atau format yang belum didukung. Konten yang belum lolos pemeriksaan berstatus pending atau quarantined.

File terikat menggunakan join parent yang jelas. Orphan hanya tersedia untuk uploader berizin sampai finalize atau cleanup. Signed URL untuk foto diberi umur pendek, usulan maksimal lima menit; URL yang sudah diterbitkan dapat tetap berlaku sampai kedaluwarsa. Untuk ekspor keuangan yang menuntut pencabutan segera, gunakan download melalui gateway yang mengotorisasi tiap request, bukan URL storage langsung yang tidak dapat dicabut seketika.

Token tracking publik acak, dihash di server, dapat dicabut, dan hanya membuka status minimal. Tidak ada telepon, alamat, foto kondisi, rincian uang, atau data pelanggan lain tanpa verifikasi lebih kuat. QR berisi ID atau token yang tidak memuat PII mentah. Metadata lokasi gambar tidak ditampilkan publik; pengelolaan original sebagai bukti mengikuti kebijakan.

## 12 SEC10 Mobile dan perangkat outlet

Token disimpan melalui fasilitas aman OS, bukan preferences biasa atau database cache. Data lokal mempunyai pemisahan tenant dan user, perlindungan sandbox, serta enkripsi lokal yang diuji untuk PII yang disimpan. Drift sendiri bukan jaminan enkripsi bawaan. Pilih mekanisme kompatibel platform dan uji recovery key serta upgrade schema.

Usulan cache kerja hanya order aktif dan histori yang diperlukan, dengan batas akses offline lokal maksimal empat jam sebelum perlu verifikasi ulang atau mode terkunci. Ini mengurangi paparan dan tidak mengubah batas sesi server 12 jam. Draft tetap dapat disimpan secara terlindungi; logout atau pergantian akun tidak membukanya untuk akun berikutnya.

Tidak ada uang atau handover final dari cache pada MVP. Printer serta scanner bukan bukti otorisasi. Jangan memasang kredensial merchant bersama pada perangkat. Perangkat hilang memicu pencabutan token online dan prosedur pengamanan setempat; tidak ada klaim remote wipe pasti ketika perangkat offline.

## 13 SEC11 Web dan konfigurasi produksi

Pisahkan domain dan cookie sesuai arsitektur yang diuji; CORS hanya origin yang perlu, bukan wildcard dengan credentials. Terapkan CSRF pada web session, encoding output, CSP yang kompatibel dengan komponen yang dipakai, dan header keamanan yang diuji. HTTPS wajib pada produksi; debug dan error detail dimatikan.

Panel admin, Horizon, log, backup, database, dan Redis tidak dibuka publik tanpa kontrol. Landing page atau tracking publik memakai endpoint dengan informasi minimal dan rate limit sendiri. Rilis tidak menyertakan contoh credential atau endpoint development.

## 14 SEC12 Audit log dan deteksi

Audit mencakup perubahan akses, diskon, revisi, refund, shift, transfer, pengambilan, ekspor, bantuan, serta tindakan platform. Simpan actor, waktu server, tenant, subject, action, alasan, dan correlation ID. Gunakan hash atau referensi berizin untuk PII dalam telemetry; pseudonimisasi nomor telepon tidak memakai hash polos yang mudah ditebak.

Role aplikasi biasa tidak menghapus audit atau ledger. Proses retensi memakai role khusus, bukti pekerjaan, dan kebijakan yang disahkan. Pantau kegagalan login berulang, penolakan lintas tenant, permintaan ekspor tidak wajar, ketidaksesuaian webhook, lonjakan refund, dan antrean tanpa receipt. Jangan memakai tenant ID sebagai label metrik berjumlah tak terbatas bila dapat membebani sistem pemantauan.

## 15 SEC13 Backup dan pemulihan

Target RPO ≤15 menit dan RTO pilot ≤4 jam atau pertumbuhan ≤1 jam diuji, bukan sekadar ditulis. Usulan backup harian 35 hari serta WAL berkelanjutan, terenkripsi, terpisah dari host aplikasi, dengan alarm backup tertinggal. File dan kunci yang diperlukan untuk restore ikut direncanakan.

Latihan restore minimal bulanan di lingkungan terisolasi memakai dataset yang aman. Periksa relasi tenant, jumlah record, ledger, lampiran, outbox, serta transaksi yang perlu direkonsiliasi dengan provider. Jangan menyalin data pelanggan ke laptop pengembang sebagai kebiasaan. Restore satu tenant pada database bersama memerlukan rencana selektif; restore global dapat mengubah semua tenant.

## 16 SEC14 Keamanan pengembangan dan dependensi

Pin dependency melalui lockfile, pantau pembaruan keamanan, dan pisahkan secret environment. CI menjalankan pengujian authorization, schema, ledger, kontrak API, analisis statis yang tersedia, serta audit dependency. Perubahan berisiko diperiksa reviewer yang memahami modul.

Migration mengikuti expand dan contract, satu pelaksana, backup yang terbukti, dan rencana rollback aplikasi yang kompatibel. Perintah destruktif membutuhkan lingkup dan otorisasi yang jelas; jangan meminta konfirmasi ulang untuk tindakan aman yang sudah diotorisasi. Data uji sintetis; penggunaan data produksi perlu dasar dan kontrol tersendiri.

Prompt, lampiran, hasil log, atau catatan pelanggan diperlakukan sebagai data yang mungkin mengandung instruksi berbahaya. AI assistant mengikuti [agents.md](agents.md), tidak mengunggah rahasia atau PII ke layanan luar, dan tidak mengubah aturan uang berdasarkan saran model secara langsung.

## 17 SEC15 Penanganan insiden

| Tingkat | Contoh | Tindakan awal |
|---|---|---|
| Sev1 | Akses lintas tenant, secret aktif bocor, uang atau barang final ganda | Batasi jalur terdampak, cabut akses atau key yang relevan, simpan bukti, aktifkan penanggung jawab insiden |
| Sev2 | Database atau pembayaran utama terganggu, transaksi tertahan luas | Lindungi transaksi, tampilkan status pending yang benar, jalankan failover atau rekonsiliasi sesuai runbook |
| Sev3 | Pesan, ekspor, atau satu perangkat gagal tanpa kehilangan inti | Isolasi komponen, retry terkontrol, berikan jalur operasional pengganti |

Alur: deteksi → penilaian ruang lingkup → containment → pelestarian bukti → pemulihan → verifikasi uang, barang, dan akses → evaluasi akar masalah. Jangan menghapus log untuk membersihkan gejala. Rotasi secret yang bocor harus diikuti pencabutan lama dan pemeriksaan penyalahgunaan.

Komunikasi kepada pelanggan, penyedia, atau pihak berwenang ditentukan penanggung jawab sesuai fakta dan kewajiban yang berlaku. Dokumen ini tidak menetapkan tenggat hukum yang belum dikaji dan tidak mengotorisasi AI mengirim pesan ke pihak lain secara otomatis. Laporan menyebut timeline, tenant atau data yang terdampak, tindakan, serta bukti pemulihan.

## 18 SEC16 Retensi consent dan penghapusan

| Data | Usulan awal atau keputusan yang diperlukan |
|---|---|
| Ledger dan dokumen transaksi | Disahkan 31 Oktober 2026: 10 tahun; tidak dihapus oleh kasir |
| Foto kondisi | 180 hari setelah order selesai, kecuali kasus komplain aktif |
| Ekspor sementara | Kedaluwarsa setelah 24 jam; akses tetap diperiksa tiap unduhan |
| Data mentah impor | Maksimum tujuh hari sesudah proses selesai kecuali perlu penyelesaian error yang disetujui |
| Log operasional | 90 hari dengan redaksi; audit keamanan 5 tahun |
| Backup | 35 hari; data yang dihapus dapat tersisa sampai lifecycle backup berakhir |
| Cache perangkat | Dataset kerja minimum, enkripsi PII lokal, batas offline empat jam; purge saat logout atau perubahan akun/scope |

Catat consent per tujuan dan kanal. Permintaan akses, ekspor, koreksi, serta penghapusan memerlukan verifikasi identitas dan pemeriksaan kewajiban penyimpanan. Catat pekerjaan penghapusan tanpa menyimpan ulang isi data yang dihapus pada log. Restore backup harus mengaplikasikan kembali daftar penghapusan yang sah agar data tidak muncul kembali tanpa kontrol.

## 19 Kriteria keamanan sebelum berbayar

- [ ] SEC01–SEC16 memiliki implementasi atau keputusan tertulis yang relevan dengan fase rilis.
- [ ] Uji akses negatif meliputi API, cache, job, file, ekspor, tracking, dan database runtime role.
- [ ] Uji idempotensi, concurrency, webhook, refund, transfer, dan handover lulus.
- [ ] Secret dan debug tidak ada pada repository atau binary; MFA dan revocation diuji.
- [ ] Upload, download, backup, restore, dan kebijakan data ditinjau.
- [ ] Kontak penanggung jawab dan runbook insiden tersedia; tidak mengandalkan akun pribadi tunggal.

## 20 Sumber resmi

- [OWASP broken object level authorization](https://api-security.owasp.org/editions/2023/en/0xa1-broken-object-level-authorization/)
- [PostgreSQL row security](https://www.postgresql.org/docs/18/ddl-rowsecurity.html)
- [Laravel Sanctum](https://laravel.com/docs/13.x/sanctum)
- [Laravel queues](https://laravel.com/docs/13.x/queues)
- [Midtrans notification verification](https://docs.midtrans.com/docs/https-notification-webhooks)

Kontrol spesifik pada dokumen ini merupakan baseline proyek. Audit implementasi serta kajian kewajiban data dilakukan pada konteks deployment dan bisnis yang sebenarnya.
