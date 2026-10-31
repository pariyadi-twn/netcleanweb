# Backlog Pelaksanaan Pengembangan Laundry SaaS

Versi 3.1 · 31 Oktober 2026 · Fondasi platform, order, histori/consent, dan approval diskon terverifikasi

[PRD](prd.md) · [TRD](trd.md) · [Database](erd.md) · [UI UX](ui_guidelines.md) · [Aturan AI](agents.md) · [Keamanan](security.md) · [Rencana](todo.md)

## Ringkasan hasil kajian

Backlog ini disusun dari seluruh tujuh berkas Markdown dalam `docs`. ID **T001–T072 dipertahankan**, lalu **T073–T080** ditambahkan untuk kebutuhan yang sebelumnya belum mempunyai task khusus atau perlu gerbang penerimaan tersendiri. Total **80 task utama**: **58 menuju pilot P0**, **2 sesudah pilot menuju rilis berbayar**, **14 untuk jaringan P1**, dan **6 untuk ekspansi atau evaluasi P2**. Status pelaksanaan mengikuti checkbox dan bukti task; adanya dokumentasi atau kerangka Laravel bukan bukti fitur selesai.

| Sumber yang dipelajari | Kebutuhan yang diturunkan ke backlog |
|---|---|
| [prd.md](prd.md) | FR01–FR48, BR01–BR10, NFR01–NFR10, U01–U16, metrik produk, dan DEC01–DEC08 |
| [trd.md](trd.md) | ADR01–ADR09, TR01–TR16, kontrak API, transaksi, outbox, sinkronisasi, operasi, dan pengujian |
| [erd.md](erd.md) | Tabel inti dan pendukung, fase migration, FK tenant, RLS, constraint, serta rumus uang dan barang |
| [ui_guidelines.md](ui_guidelines.md) | Kanal, navigasi per peran, token visual, state UI, aksesibilitas, printer, dan pemulihan error |
| [security.md](security.md) | SEC01–SEC16, kontrol akses, perlindungan data, perangkat, backup, dan insiden |
| [agents.md](agents.md) | Aturan implementasi, otorisasi perubahan, verifikasi sesuai risiko, serta bukti penyelesaian |
| [todo.md](todo.md), versi 1.1 | Baseline 72 task, dependensi, fase rilis, dan matriks keterlacakan yang dipertahankan |

### Kondisi repositori saat kajian

- PHP CLI **8.4.27**, Composer **2.9.3**, dan Laravel terpasang **13.39.0**. Versi pasti berikutnya tetap mengikuti lockfile dan hasil CI.
- Aplikasi Laravel berada langsung di root proyek yang memiliki `artisan` dan `composer.json`, bukan direktori `backend/` seperti contoh struktur TRD.
- `app/` berisi controller dasar, model `User`, dan service provider; provider sudah memaksa HTTPS pada environment production. Migration yang tersedia baru tabel bawaan users, cache, dan jobs beserta tabel pendukung framework.
- `php artisan route:list --except-vendor --no-interaction` menampilkan satu route aplikasi, yaitu halaman awal. Test yang tersedia masih dua contoh bawaan, bukan pembuktian FR atau NFR.
- Belum ada direktori aplikasi Flutter. Sanctum, Horizon, dan Livewire belum tercantum sebagai dependency langsung ketika kajian dilakukan. Pengadaan dependency dan lokasi proyek mobile diselesaikan pada T007 sesuai otorisasi proyek.
- Belum ada bukti pipeline CI, PostgreSQL runtime role/RLS, integrasi provider, perangkat fisik, UAT, benchmark, maupun restore. Status layanan database atau infrastruktur yang sedang berjalan tidak disimpulkan dari contoh konfigurasi.
- Direktori kerja belum merupakan repositori Git. Inisialisasi repositori dan alur review termasuk T007; commit, publikasi, dan deployment mengikuti otorisasi yang berlaku.

### Prioritas dan hasil tiap tahap

| Tahap | Prioritas pelaksanaan | Task utama | Jumlah | Hasil yang dapat diterima |
|---|---|---|---|---|
| A. Discovery | P0, pembuka keputusan | T001–T006 | 6 | SOP, fixture aturan bisnis, perangkat, kebijakan data, dan prototype kasir |
| B. Fondasi | P0, pembuka implementasi | T007–T020, T073 | 15 | Identitas, tenant boundary, kontrak API, kuota, audit, retry, worker, dan storage |
| C. Penerimaan | P0 | T021–T033, T074–T076 | 16 | Pelanggan → quote → order/invoice → label/nota, tracking, dan draft mobile |
| D. Operasional | P0 | T034–T045, T077 | 13 | Produksi, QC, uang, handover, laporan, notifikasi, dan administrasi |
| E. Verifikasi pilot | P0, gerbang pilot | T046–T052, T079 | 8 | Bukti integritas, perangkat, beban, restore, rollout, UAT, dan keputusan pilot |
| E. Observasi dan komersial | Gerbang berbayar sesudah pilot | T080, T078 | 2 | Pengukuran empat minggu, biaya nyata, DEC08, dan keputusan berbayar |
| F. Jaringan | P1 | T053–T066 | 14 | Pusat produksi, transfer, kurir, gateway, inventori, B2B, dan konsolidasi |
| G. Ekspansi dan kapasitas | P2 atau evaluasi setelah P1 | T067–T072 | 6 | Fitur tambahan dan peningkatan kapasitas berdasarkan kebutuhan yang terbukti |

P0/P1/P2 untuk FR tetap mengikuti PRD. Penempatan T078 dan T080 sesudah pilot adalah urutan bukti bisnis, bukan perubahan prioritas FR. T070–T072 adalah evaluasi arsitektur dan target operasi, bukan FR baru yang otomatis harus dibangun. Kompetensi pelaksana yang diperlukan: produk/operasional untuk keputusan, backend/web, Flutter, QA, serta operasi platform; nama penanggung jawab belum ditunjuk.

### Penajaman dari rencana sebelumnya

1. **Kontrak dan fondasi antarmuka:** OpenAPI mempunyai T073; fondasi Flutter dan design system mempunyai T074–T075. T033 tidak lagi menjadi penampung seluruh pekerjaan antarmuka.
2. **Alur pelanggan P0:** Tracking minimal dari FR15 dan notifikasi layanan dari FR10/FR15 serta alur PRD mempunyai T076–T077. Aplikasi pelanggan lengkap tetap FR48/P2.
3. **Keputusan komersial:** DEC08 mempunyai T078 dan menunggu biaya pilot yang benar-benar diamati, bukan harga paket yang diasumsikan sudah disahkan.
4. **Pemisahan bukti:** T051 mencakup pelatihan dan UAT sebelum pilot; T052 memutuskan masuk pilot; T080 mengukur operasi empat minggu sesudahnya. Tidak ada syarat empat minggu operasi sebelum pilot boleh dimulai.
5. **Migration dan rilis:** T079 membuktikan upgrade, kompatibilitas rollout, smoke, dan rollback aplikasi. T050 tetap khusus pemulihan data dan replay pekerjaan.
6. **Dependensi uang:** T040 → T038 → T039 → T031, serta T035 + T038 → T037. Pembatalan final dan handover tidak diambil hanya karena tampil lebih awal dalam dokumen.

## 1 Cara menggunakan backlog

Status awal task implementasi adalah belum dikerjakan; perkembangan dicatat pada checkbox serta bukti pelaksanaan. Paket dokumentasi ini memberi spesifikasi, bukan bukti aplikasi selesai. ID T mengikat pekerjaan, FR mengikat kebutuhan produk, TR mengikat desain, SEC mengikat kontrol keamanan, dan U mengikat skenario UAT. Detail berada pada [prd.md](prd.md), [trd.md](trd.md), [erd.md](erd.md), [ui_guidelines.md](ui_guidelines.md), [agents.md](agents.md), serta [security.md](security.md).

Urutan mengikuti dependensi, bukan nomor semata. Beberapa tugas satu fase dapat berjalan bersamaan jika tidak saling menunggu. T037 memerlukan T038 karena penyerahan memeriksa saldo; ini contoh dependensi yang lebih penting daripada urutan tampilan. Penambahan cakupan memerlukan perbaikan estimasi dan acceptance.

Status awal seluruh task adalah **Belum dikerjakan**. Saat pelaksanaan, catat **Siap diambil**, **Dikerjakan**, **Terblokir** beserta penyebabnya, **Siap diverifikasi**, atau **Selesai** pada bukti task. Checkbox hanya menandai Selesai. Task siap diambil bila semua dependensi yang diperlukan telah diterima, keputusan yang memengaruhinya tersedia, dan lingkungan pengujiannya jelas. Subtask seperti T007.1 adalah rincian task induk, bukan tambahan dalam hitungan 80 task utama.

Estimasi mengasumsikan product owner atau analis paruh waktu, dua backend dan web developer, satu sampai dua Flutter developer, QA, dan dukungan desain serta operasi. Rentang minggu bukan komitmen kalender. Pengukuran empat minggu operasi memerlukan periode nyata; bila belum tercapai, task metrik tetap terbuka walaupun fitur telah selesai.

Rentang fase di bawah berasal dari baseline tim tersebut dan perlu diestimasi ulang setelah T001–T007 serta kapasitas pelaksana diketahui. T073–T079 menambah rincian pekerjaan yang perlu diperhitungkan; T080 menambah waktu observasi nyata. Pelaksanaan oleh satu orang atau satu agent mengikuti urutan dependensi dan tidak dianggap memenuhi jadwal tim secara otomatis.

### Siap mulai dan urutan pengambilan

| Urutan | Task | Langkah pertama dan hasil konkret | Syarat mulai |
|---|---|---|---|
| 1 | T001 | Catat keputusan tenant, badan usaha, cabang, pusat produksi, dan merchant beserta skenario waralaba | Tidak ada; keputusan melibatkan pemilik produk |
| 2 | T007 | Inventarisasi root Laravel, versi, dependency yang belum ada, lokasi mobile, Git, dan lingkungan; jalankan pipeline dasar pada database test PostgreSQL | Tidak ada; perubahan dependency mengikuti otorisasi proyek |
| 3 | T002, T003 | Turunkan BR05 serta contoh harga, kalender, kas, refund, dan penyerahan berpiutang menjadi acceptance fixture | T001 |
| 4 | T004, T005 | Tetapkan matrix perangkat/pilot dan kebijakan data/kanal layanan yang akan diuji | T001 |
| 5 | T006, T073 | Uji prototype kasir; susun kontrak API v1 dan katalog error untuk implementasi lintas kanal | Dependensi masing-masing: T002–T004 untuk T006; T007 untuk T073 |
| 6 | T008 → T010 → T011 → T013 | Bangun schema identitas, login, izin, dan tenant context; buktikan penolakan lintas tenant pada role runtime | T001 dan T007; keputusan schema telah tersedia |
| 7 | T014 → T017 → T018 | Bangun audit, command receipt, dan outbox sebelum transaksi bisnis final | T013 dan dependensi masing-masing |
| 8 | T012, T019–T027, T040 | Selesaikan cabang, storage, katalog, pelanggan, quote/order, unit fisik, serta fondasi laci kas | Ambil hanya task yang dependensinya terpenuhi |

T009, T015–T016, T020, dan fondasi mobile T074–T075 mengikuti dependensi pada detail masing-masing. Vertical slice prioritas adalah **pelanggan → quote → order/invoice → DP → tugas produksi → QC → handover → tutup kas**, dengan audit, isolasi tenant, dan retry diuji pada setiap penambahan. Dashboard dan ekspor mengikuti sumber transaksi yang sudah dapat direkonsiliasi.

### Keputusan yang menjadi prasyarat

| Keputusan | Pemilik keputusan menurut PRD | Task pengumpul keputusan | Dampak dan batas penerapan |
|---|---|---|---|
| DEC01 Organisasi dan waralaba | Product owner | T001 | Sebelum schema organisasi final T008; waralaba independen tidak otomatis satu tenant |
| DEC02 Merchant penerima dana | Product owner dan keuangan | T001 | Sebelum T060; akun platform dan merchant laundry tetap dibedakan |
| DEC03 Pajak, minimum, pembulatan, diskon | Operasional | T002 | Sebelum acceptance pricing T022/T026/T030; contoh BR05 tanpa pajak tetap fixture acuan |
| DEC04 Handover berpiutang dan kredit | Pemilik usaha | T003 | Sebelum T037 dan pilot; outstanding dan izin diperiksa server |
| DEC05 Printer, scanner, Android, iOS | Operasional | T004 | Sebelum pembelian perangkat, pemilihan plugin T074, dan UAT fisik T048 |
| DEC06 Kompensasi dan kondisi awal | Operasional | T003 | Sebelum UAT T028/T036/T039; pembuat dan penyetuju mengikuti batas yang disahkan |
| DEC07 Retensi, consent, hosting, dukungan | Product owner | T005 | Sebelum berbayar; rincian memengaruhi T019/T024/T044/T045/T050 |
| DEC08 Paket dan biaya add on | Bisnis | T078 | Setelah biaya pilot tersedia melalui T080 dan sebelum rilis berbayar |

Peran di tabel bukan penunjukan nama orang. Jika keputusan belum ada, tandai task terdampak Terblokir dan lanjutkan task independen; jangan mengisi keputusan bisnis dengan asumsi coding.

### Definisi selesai

Sebuah task baru dicentang bila perilaku acceptance terwujud, test sesuai risiko dijalankan, permission dan tenant boundary diuji, kontrak serta dokumen diperbarui, dan bukti hasil tersedia. Task yang memerlukan perangkat, sandbox provider, UAT, atau restore tidak selesai hanya dari screenshot UI. Rilis produksi memerlukan otorisasi sesuai lingkup yang berlaku; jangan meminta izin berulang untuk edit atau test lokal yang sudah diotorisasi.

### Bukti minimum per task

Catat ID task, penanggung jawab yang benar benar ditunjuk, FR atau NFR, perubahan kode atau migration, lingkungan, versi build, command pengujian, hasil nyata, gap, dan keputusan penerimaan. Jangan mengisi nama personel, angka benchmark, atau tanggal selesai yang belum terjadi.

Format pencatatan saat task diambil:

| Field | Isi yang harus tersedia |
|---|---|
| Identitas | ID task, status, pelaksana yang ditunjuk, reviewer/penerima bila diperlukan |
| Kesiapan | Dependensi diterima, keputusan terkait, sumber FR/NFR/TR/SEC/UI atau tabel ERD |
| Perubahan | File, migration, endpoint, permission, dan kanal yang benar-benar berubah |
| Verifikasi | Build, environment, dataset sintetis, perangkat/provider bila relevan, command dan hasil nyata |
| Penerimaan | Acceptance yang lulus, gap tersisa, bukti atau referensi hasil, keputusan lanjut/tunda |

Untuk fitur backend yang dipakai Flutter/web, task baru selesai setelah kontrak dan alur kanal terkait bekerja. Pembuktian PostgreSQL tidak diganti SQLite; bukti perangkat, provider, restore, atau observasi tidak diganti test unit. Penyusunan backlog ini sendiri tidak menutup task implementasi.

## Fase A Discovery dan keputusan

**Rentang usulan:** Minggu 1 sampai 2. **Hasil:** SOP dan keputusan yang memengaruhi schema, uang, perangkat, serta data jelas.

- [x] **T001 Validasi organisasi dan model cabang**
  Rujukan: DEC01 DEC02. Dependensi: Tidak ada. Selesai jika: SOP tenant, cabang, pusat produksi, badan usaha, dan merchant disepakati tanpa menganggap seluruh waralaba satu tenant.

  Bukti 31 Oktober 2026: Pengguna menyetujui baseline DEC01 dan merchant tenant DEC02 melalui pertanyaan sesi implementasi. SOP enam langkah dan status keputusan dicatat pada PRD bagian 11; sesuai BR01, TR01, serta batas uang laundry/platform pada ERD. Penerima keputusan: pengguna/pemilik produk. Tidak memerlukan test aplikasi karena task ini menetapkan keputusan bisnis.

- [x] **T002 Tetapkan aturan harga dan kalender**
  Rujukan: FR07 FR08 FR11 DEC03. Dependensi: T001. Selesai jika: Contoh minimum, kelipatan, express, harga cabang, cut off, libur, dan masa berlaku quote menjadi fixture acceptance.

  Status selesai 31 Oktober 2026, disetujui pengguna/pemilik produk. Fixture machine-readable berada pada [fixtures/pricing-calendar.json](fixtures/pricing-calendar.json). Nilai yang disahkan: rupiah integer dan tanpa pajak untuk P0; kiloan minimum 3 kg, step 0,1 kg, default Rp8.000/kg; bed cover Rp25.000/potong; override cabang Rp9.000/kg dengan periode efektif dan konflik periode ditolak; express +50% dari subtotal layanan setelah diskon; diskon contoh 10% hanya layanan; antar Rp5.000 tidak didiskon dan tidak kena express; quote 15 menit; cabang buka Senin–Sabtu 08.00–20.00, cut-off 15.00, tutup Minggu/libur; reguler 2 hari kerja dan express 1 hari kerja, dengan hari libur dilewati dan tenggat pukul 20.00 waktu cabang. Contoh BR05 menghasilkan total Rp71.600, DP Rp30.000, sisa Rp41.600; contoh express menghasilkan Rp104.900.

  Verifikasi: `PricingCalendarFixtureTest` lulus **9 test/22 assertion** untuk aritmetika BR05, express, pembulatan kuantitas, dan enam skenario tenggat termasuk cut-off dan libur. Seluruh suite lulus **112 test/593 assertion** saat T002 ditutup. Aktivasi pajak dan harga kontrak pelanggan tetap memerlukan keputusan lanjutan; harga tenant/cabang sudah diimplementasikan pada T022.

- [x] **T003 Tetapkan aturan uang kas dan kompensasi**
  Rujukan: FR31 FR33 FR34 FR35 DEC04 DEC06. Dependensi: T001. Selesai jika: Alur DP, refund, setoran, kembalian, piutang, penyerahan, dan maker checker mempunyai contoh angka serta penyetuju.

  Status selesai 31 Oktober 2026, disetujui pemilik usaha. DP boleh bertahap; contoh BR05 tagihan Rp71.600, DP Rp30.000, sisa Rp41.600. Metode P0 tunai dan transfer manual; gateway tetap P1. Piutang default Rp0 per pelanggan; owner dapat menetapkan limit per pelanggan dengan jatuh tempo 7 hari, dan penyerahan diblokir bila outstanding melewati limit. Refund hanya terhadap payment asal; contoh invoice Rp100.000, refund Rp20.000, sisa refundable Rp80.000. Refund dan kompensasi memerlukan owner; tindakan di atas Rp100.000 atau diskon di atas 10% memerlukan aktor berbeda. Shift memakai kas awal, ledger, setoran, kas fisik, dan selisih; contoh kas awal Rp200.000 + penjualan tunai Rp500.000 - biaya Rp50.000 = Rp650.000, fisik Rp648.000, selisih -Rp2.000 masih dalam toleransi Rp5.000. Selisih di atas toleransi dan reopen shift memerlukan alasan serta persetujuan owner. Kompensasi barang hilang/rusak memakai rewash atau penggantian lebih dahulu; kompensasi tunai dinilai per kasus dengan bukti kondisi awal dan persetujuan owner. Batas diskon: kasir 0%, supervisor maksimum 10%, owner sampai 100%, semuanya diaudit.

- [x] **T004 Pilih perangkat serta lokasi pilot**
  Rujukan: FR12 FR15 DEC05. Dependensi: T001. Selesai jika: Model printer, koneksi, ukuran nota, Android sasaran, dan minimal satu calon jaringan tiga cabang dicatat.

  Status selesai 31 Oktober 2026, disetujui operasional. Printer: Eppos 80 mm, portable Smartcom 80 mm, dan Eppos 58 mm; koneksi Bluetooth/USB/LAN; nota final 80 mm dengan fallback 58 mm; label bag 50 × 30 mm; Android 10+ target API 35 RAM 4 GB; kamera + scanner Bluetooth keyboard-wedge; iOS tidak masuk P0. Pilot aktual: Karya Laundry dengan 1 cabang + 1 pusat produksi. Acceptance tiga cabang pada teks awal diganti oleh pemilik; arsitektur dan data tetap mendukung banyak cabang + satu pusat produksi.

- [x] **T005 Sahkan kebijakan data dan dukungan**
  Rujukan: SEC04 SEC09 SEC10 SEC16 DEC07. Dependensi: T001. Selesai jika: Tujuan data, consent per kanal, kanal notifikasi layanan pilot, lokasi hosting, retensi, akses bantuan, ekspor, penghapusan, serta perlindungan cache perangkat mempunyai pemilik keputusan dan kebijakan yang dicatat. Nilai usulan pada security.md tidak dianggap kebijakan final tanpa keputusan.

  Status selesai 31 Oktober 2026, disetujui product owner. Tujuan data: layanan, pembayaran, dukungan, dan kewajiban legal; marketing terpisah dan opt-in per kanal. Kanal layanan pilot: WhatsApp resmi + email fallback; push tetap opsional. Hosting di Indonesia dengan database dan object storage privat. Retensi: ledger/dokumen transaksi 10 tahun, audit 5 tahun, log operasional 90 hari, foto kondisi 180 hari, ekspor sementara 24 jam, data impor 7 hari, backup 35 hari, cache perangkat maksimum 4 jam. Grant support maksimum 24 jam, terikat tenant/kasus, read-only, tanpa refund atau eskalasi role, dan diaudit. Ekspor/penghapusan memerlukan verifikasi identitas dan tetap menghormati kewajiban retensi ledger; daftar penghapusan diterapkan ulang setelah restore. Cache PII dienkripsi dan dipurge saat logout/ganti akun. Bukti test aplikasi belum ada karena task ini menetapkan kebijakan; implementasi consent/history menjadi T024, notifikasi T077, ekspor T046, dan retensi T045.

- [ ] **T006 Uji prototype kasir**
  Rujukan: FR13 NFR10. Dependensi: T002, T003, T004. Selesai jika: Staf dapat memahami jumlah fisik, berat tertagih, total, tenggat, dan status pending; masalah usability dicatat.

## Fase B Fondasi platform

**Rentang usulan:** Minggu 3 sampai 4. **Hasil:** Tenant boundary, identitas, CI, idempotensi, outbox, dan operasi dasar berfungsi.

- [x] **T007 Siapkan repositori toolchain dan CI**
  Rujukan: ADR01 ADR02 ADR04 ADR06 ADR07 ADR09 TR02 TR13 SEC04 SEC11 SEC14. Dependensi: Tidak ada. Selesai jika: Struktur root Laravel dipertahankan; versi kompatibel dipin; lokasi aplikasi mobile disepakati; environment dan secret terpisah; pipeline dasar benar benar berjalan pada PostgreSQL test terisolasi. Kebutuhan dependency yang belum terpasang dicatat dan ditambahkan hanya sesuai otorisasi proyek.

  Bukti 31 Oktober 2026, pelaksana coding agent sesuai instruksi pengguna: Git lokal branch `main`; pin PHP 8.4.27, Composer platform 8.4.27, Node 26.5.0/npm 12.0.1, Flutter 3.44.8/Dart 3.12.2, serta lockfile Composer/npm. Dependency langsung: Sanctum 4.3.3, Horizon 5.50.1, Livewire 4.4.8, Flysystem S3 3.35.3, Predis 3.7.0. `project:prepare` menghasilkan lingkungan lokal/test terisolasi dan mempertahankan file yang sudah ada; kredensial diabaikan Git.

  Verifikasi lokal: `docker compose --env-file .env.infrastructure up -d --wait` menghasilkan empat layanan healthy; `composer check` lulus 11 test/35 assertion dan audit bersih; `npm audit` bersih; `npm run build` berhasil. Test membuktikan PostgreSQL 18 pada `netclean_test`, runtime tanpa DDL/superuser/BYPASSRLS, Redis terpisah/noeviction, S3 read/write privat dengan anonymous 403, header keamanan, serta Horizon deny by default. Konfigurasi Nginx dan PHP-FPM diuji dengan `nginx -t` dan `php-fpm -t`; Pint dijalankan pada file PHP terkait.

  Batas bukti: Perintah pipeline dijalankan lokal; eksekusi workflow GitHub belum tersedia karena belum ada remote/commit. Image aplikasi penuh belum dibuild pada host dengan ruang partisi sistem terbatas; syntax runtime telah diperiksa, sedangkan build/rollout/rollback staging tetap bukti T079. Tidak ada commit, push, atau deploy produksi. Storage lokal dipasang pada partisi proyek yang memiliki ruang, bukan memaksa penulisan pada partisi sistem yang hampir penuh.

  **Temuan dan kontrol filesystem:** Pemeriksaan awal menemukan `.env.infrastructure`, `.env.local`, `.env.testing`, dan konfigurasi S3 tetap bermode 0777 meskipun `chmod(0600)` melaporkan sukses. Host memakai NTFS/ntfs-3g pada `/media/zahrfizh/Data` tanpa permission per file yang efektif. Pengguna memilih memperbaiki filesystem lebih dahulu dan menunda pemindahan kredensial ke direktori lain. `project:prepare` kini memeriksa mode aktual sebelum menulis secret, mempertahankan isi file lama, dan gagal dengan pesan yang dapat ditindaklanjuti jika filesystem tidak menerapkan mode privat. Jalur sukses dengan mode 0600/idempotensi serta jalur chmod palsu sukses/0777 mempunyai test.

  Atas persetujuan pengguna, entri UUID `BC908FAF908F6F28` ditambahkan ke `/etc/fstab` memakai ntfs-3g. Percobaan pertama menggabungkan `permissions` dengan forced-owner `uid=1000,gid=1000`; restart membuktikan unit aktif dan driver melaporkan global permission enforcement, tetapi probe aktual tetap 0777 karena forced-owner menonaktifkan perubahan permission per file. Entri lalu dikoreksi menjadi `permissions,nosuid,nodev,nofail` tanpa `uid/gid`, sesuai dokumentasi ntfs-3g untuk permission per file. Backup sebelum perubahan pertama: `/etc/fstab.netcleanweb.backup-20261031T033507Z`; backup konfigurasi percobaan pertama: `/etc/fstab.netcleanweb.backup-20261031T113500Z`. `findmnt --verify` melaporkan 0 parse error/0 error; tiga warning terkait swapfile serta identifikasi driver userspace ntfs-3g terhadap kernel/on-disk NTFS tetap tercatat. Driver ntfs-3g 2022.10.3 tersedia. Setelah restart kedua, probe aktual berhasil 0600; ownership proyek dinormalisasi ke pengguna lokal, source menjadi 0644, executable yang diperlukan 0755, file credential 0600, serta direktori credential/object storage 0700.

  Verifikasi penerimaan akhir: `project:prepare` lulus dan mempertahankan credential lama; Compose menghasilkan empat layanan healthy; `composer check` lulus **30 test/87 assertion**, manifest valid, serta audit Composer bersih; `npm run lint:api`, `npm audit`, dan `npm run build` lulus. Test permission mencakup file 0600, kedua direktori 0700, idempotensi isi, serta penolakan filesystem yang memalsukan keberhasilan chmod. Unit mount aktif berasal dari `/etc/fstab`, memakai ntfs-3g dan `permissions` tanpa forced-owner.

  Layanan Compose lokal aktif dan healthy sesudah penerimaan; data dan volume pengujian dipertahankan. Eksekusi workflow GitHub tetap menunggu remote/commit, sedangkan rollout/rollback staging tetap cakupan T079.

  - [x] **T007.1 Inventarisasi dan versioning:** Tetapkan alur Git/review, versi PHP/Laravel/PostgreSQL/Flutter, lockfile, lokasi mobile, dan dependency Sanctum/Horizon/Livewire sesuai kebutuhan yang disahkan.
  - [x] **T007.2 Lingkungan pengembangan dan test:** PostgreSQL 18/role, Redis terpisah, S3 privat, database test terisolasi, serta file credential 0600 dan direktori privat 0700 teruji.
  - [x] **T007.3 Pipeline dasar:** Jalankan validasi Composer, pemeriksaan format/lint yang tersedia, test backend, build web, dan audit dependency. Bukti pipeline disimpan, bukan hanya file workflow; job Flutter ditambahkan pada T074 ketika proyek mobile tersedia sehingga penyelesaian T007 tidak menunggu task turunannya.
  - [x] **T007.4 Fondasi runtime:** Tetapkan PHP-FPM/OPcache, proses API/worker/scheduler, pembatasan ingress dan aplikasi, serta baseline CSRF/CORS/cookie/header/debug sesuai kanal. Pengujian lengkap mengikuti T046 dan T079.

- [x] **T073 Susun kontrak OpenAPI dan protokol API v1**
  Rujukan: ADR05 TR03 TR15 SEC05 NFR04. Prioritas: P0. Dependensi: T007. Selesai jika: Kontrak dapat divalidasi dan menjadi acuan backend, Flutter, serta web; contoh dan test kontrak tersedia saat endpoint diimplementasikan.

  Status selesai: [openapi.yaml](openapi.yaml) mendefinisikan 46 path/56 operasi dengan penanda P0/P1, autentikasi/konteks, perangkat, MFA, reset password, provisioning tenant, entitlement/langganan, assignment membership idempoten, konfigurasi cabang, kalender cabang, preview tenggat, pelanggan/alamat, histori pelanggan, consent, katalog layanan/workflow, price book bertanggal, resolusi harga, publikasi layanan, request mutasi, replay, pagination, tipe data, error, dan fase rilis. `npm run lint:api` lulus memakai Swagger Parser 13.1.0; audit npm bersih dan validasi kontrak ditambahkan ke CI. Kontrak menandai endpoint sebagai rancangan; test kontrak terhadap endpoint nyata dilengkapi saat modulnya dibuat.

  - [x] **T073.1 Bentuk data dan scope:** Dokumentasikan `/api/v1`, UUID publik, integer rupiah, decimal string, waktu UTC, header konteks, pagination default 25/maksimum 100, dan batas sync batch usulan 50.
  - [x] **T073.2 Mutasi dan error:** Tetapkan `Idempotency-Key`/`client_command_id`, expected version, 201 baru/200 replay/202 asynchronous, 401/403/404/409/422/429, `Retry-After`, correlation ID, dan seluruh kode domain minimum TR03.
  - [x] **T073.3 Kontrak per fase:** Pisahkan endpoint P0 aktif dari rancangan endpoint P1; definisikan request/response dan pembatasan field per peran. Perubahan endpoint pada task berikutnya memperbarui kontrak dan test yang relevan dalam patch yang sama.

- [x] **T008 Buat migration organisasi dan identitas**
  Rujukan: FR02 FR05 TR04. Dependensi: T001, T007. Selesai jika: Tenant, user, membership, cabang, lokasi, role, permission, serta join assignment tersedia dengan FK tenant dan migration yang diuji. Schema `users` bawaan dievolusikan untuk public UUID dan kebutuhan identitas tanpa menghapus data atau perubahan aplikasi yang sudah ada. Lokasi tidak membentuk siklus; waktu memakai semantik UTC dan zona cabang.

  Status selesai: Tiga migration menambah public UUID/status/field MFA/version pengguna, tenants/branches/locations, membership/roles/permissions, serta empat join assignment/permission. Model domain berada pada `app/Modules/Identity`, identitas login tetap `app/Models/User`; tersedia factory, enum canonical, seeder permission yang idempoten, dan action reparent lokasi dengan lock/version/penolakan siklus. `IdentitySchemaTest` lulus 17 test/36 assertion pada PostgreSQL 18; rollback tiga migration dan migrate ulang juga berhasil pada `netclean_test` dengan koneksi migration. Backfill mempertahankan identitas/password dan waktu UTC pengguna lama. Ini bukan bukti authorization API atau RLS; T010/T011/T013 tetap terbuka.

- [x] **T009 Implementasikan provisioning tenant**
  Rujukan: FR01 TR01. Dependensi: T008, T073. Selesai jika: Request ulang menghasilkan tenant, cabang awal, membership pemilik, serta onboarding yang sama; proses terputus dapat dilanjutkan. Karena tenant belum tersedia saat awal pendaftaran, deduplikasi provisioning memakai scope identitas/control plane yang terbatas, bukan mensyaratkan command receipt tenant T017 dan membentuk dependensi melingkar. Trial serta entitlement diintegrasikan pada T015.

  Status selesai 31 Oktober 2026, pelaksana coding agent sesuai keputusan pengguna: pemilik sudah login lalu membuat organisasi. Endpoint `POST /api/v1/provisioning/tenants` memakai `Idempotency-Key` yang harus sama dengan `client_command_id`. `provisioning_requests` menyimpan unique `(user_id, client_command_id)`, hash payload, status, referensi tenant/cabang/membership, error code, dan RLS berbasis user; runtime tidak dapat menghapus.

  `ProvisioningScope` membuka transaksi control-plane dengan user context dan menetapkan tenant context setelah ID tenant dialokasikan, sehingga role runtime dapat membuat tenant tanpa melonggarkan policy SELECT lintas tenant. Service membuat tenant aktif, cabang awal, role `owner` sistem dengan seluruh permission saat ini, membership owner aktif, role assignment, dan branch assignment. Request replay mengembalikan tenant/cabang/membership yang sama; payload berbeda pada key sama menghasilkan 409 `IDEMPOTENCY_PAYLOAD_MISMATCH`; key yang sama untuk user berbeda menghasilkan tenant terpisah.

  Verifikasi: `ProvisioningTest` lulus **4 test/39 assertion** untuk provisioning + replay, konflik payload, scope key per pemilik, header tidak cocok, dan request tanpa autentikasi. `TenantRlsTest` membuktikan role runtime hanya dapat membuat tenant di dalam provisioning context. Seluruh suite lulus **97 test/544 assertion**; OpenAPI valid untuk 30 path/34 operasi; rollback dan migrate ulang migration provisioning berhasil.

  Gap: trial, entitlement, dan batas jumlah tenant menunggu T015; onboarding profil usaha/step terputus belum dipersist sebagai state terpisah; slug memakai suffix acak untuk menghindari tabrakan; belum ada rate limit khusus provisioning; token owner yang sudah terbit tidak dicabut saat menjadi owner sehingga kewajiban MFA baru berlaku pada login berikutnya; alur platform admin belum dibuat.

- [x] **T010 Implementasikan login MFA dan perangkat**
  Rujukan: FR03 TR05 SEC02 SEC04. Dependensi: T007, T008, T073. Selesai jika: Token mobile per perangkat mempunyai expiry eksplisit, hash server, daftar sesi, dan revocation yang berlaku pada request berikutnya; login/reset tidak membocorkan keberadaan akun; MFA pemilik/admin beserta recovery diuji. Session web menggunakan CSRF dan cookie aman; tidak mengasumsikan Sanctum menyediakan refresh token OAuth bawaan.

  Status selesai 31 Oktober 2026, pelaksana coding agent sesuai instruksi pengguna. Keputusan pengguna: TOTP memakai dependency terawat `spomky-labs/otphp` 11.5.0; device binding memakai UUID instalasi yang di-hash, bukan cryptographic proof-of-possession; token 12 jam; MFA wajib untuk pemilik tenant dan platform admin.

  Schema: `devices` menyimpan hash instalasi, label, `last_seen_at`, dan `revoked_at`; `personal_access_tokens` memakai model Sanctum kustom dengan FK perangkat non-null, `expires_at timestamptz` non-null, dan token SHA-256; `mfa_recovery_codes` menyimpan hash kode dan `used_at`; `users` mendapat `is_platform_admin` dan `mfa_confirmed_at`. Migration rollback dan migrate ulang empat migration berhasil pada PostgreSQL 18.

  API: `POST /api/v1/auth/tokens` mewajibkan `installation_id`, menerbitkan token 12 jam sekali tampil, menyimpan hash, dan mencatat perangkat. `GET /api/v1/auth/tokens` hanya mengembalikan sesi aktif milik pengguna; `DELETE /api/v1/auth/tokens/{deviceId}` menghapus token dan menandai perangkat revoked sehingga request berikutnya 401. Login memakai pencarian email case-insensitive, dummy hash untuk akun tidak dikenal, status aktif, pesan 401 generik, dan rate limit. Reset password selalu 202 dengan body sama; hanya akun aktif yang menerima notifikasi; reset mencabut token/perangkat, menghapus sesi database, dan `auth.session` membuat sesi web lama tidak berlaku.

  MFA: enrollment dan konfirmasi TOTP, recovery code acak 60-bit yang di-hash dan sekali pakai, penolakan replay TOTP melalui cache 90 detik, serta regenerasi recovery code dengan verifikasi password. Pemilik ditentukan dari role code kanonik `owner`; platform admin dari flag eksplisit. Pemilik/admin tanpa MFA terkonfirmasi tidak dapat memperoleh token API dan diarahkan ke setup melalui web. Sesi web memakai CSRF middleware, regenerasi session setelah login, cookie `HttpOnly`/`Secure` sesuai environment, dan penolakan akun nonaktif. Rate limit login, reset, dan MFA menghasilkan 429 dengan `Retry-After`; error API memakai envelope `ErrorEnvelope` dan `X-Correlation-ID`.

  Verifikasi: `php artisan test --compact` lulus **52 test/251 assertion**, termasuk 22 test/164 assertion T010 untuk hash token, expiry 12 jam, isolasi/revocation perangkat, non-enumerasi login/reset, MFA owner/admin, recovery sekali pakai, rotasi session, cookie aman, konteks pengguna, dan rate limit. `composer check` lulus dengan audit Composer bersih; `npm audit`, `npm run lint:api` (24 path/26 operasi), `npm run build`, dan `vendor/bin/pint --test` lulus. Rollback dan migrate ulang empat migration T010 berhasil pada PostgreSQL 18.

  Gap: pembuatan role `owner` tetap tanggung jawab provisioning T009; penugasan platform admin belum mempunyai alur operasional; UUID instalasi mengikat sesi ke perangkat secara administratif tetapi token curian tetap bearer; unique email case-insensitive belum menjadi constraint database sehingga deduplikasi harus diselesaikan T009; audit pemulihan MFA menyusul T014; pengiriman email produksi mengikuti T005/T044.

- [x] **T011 Implementasikan permission dan assignment**
  Rujukan: FR04 SEC03. Dependensi: T008, T010. Selesai jika: Policy objek, cabang, lokasi, dan permission version diuji pada role berbeda.

  Status selesai 31 Oktober 2026, pelaksana coding agent sesuai instruksi pengguna. `PermissionCode` memuat delapan kode izin kanonis. `TenantContext` immutable membawa identitas pengguna, tenant, membership, permission version, scope cabang/lokasi, dan daftar permission. `MembershipAuthorizer` memuat membership aktif serta tenant aktif, lalu meng-cache payload otorisasi dengan kunci membership+permission version; perubahan versi membuat cache lama tidak terpakai.

  `UpdateMembershipAssignments` memvalidasi role, cabang, dan lokasi berasal dari tenant yang sama, mengganti pivot secara atomik, lalu menaikkan `permission_version`. `SyncRolePermissions` mengganti permission role dan menaikkan versi seluruh membership yang memakai role tersebut. `BranchPolicy`, `LocationPolicy`, `MembershipPolicy`, dan `RolePolicy` terdaftar melalui Gate dan memeriksa pengguna, tenant objek, permission, serta assignment cabang/lokasi.

  Verifikasi: `AuthorizationTest` lulus **7 test/42 assertion** untuk owner versus viewer, cabang/lokasi yang ditugaskan versus tidak, penolakan cabang tenant lain, invalidasi cache setelah perubahan role, kenaikan versi setelah assignment berubah, membership/tenant nonaktif, penolakan assignment lintas tenant, dan penolakan permission tidak dikenal. Seluruh suite lulus **59 test/293 assertion**; OpenAPI tetap valid.

  Gap: binding `TenantContext` per request dan RLS database dilengkapi T013; pembuatan role `owner` serta assignment awal tetap T009; belum ada endpoint HTTP produksi yang memakai Policy sampai modul terkait dibuat.

- [x] **T012 Implementasikan konfigurasi cabang**
  Rujukan: FR05. Dependensi: T008, T011. Selesai jika: Cabang nonaktif tidak menerima order; histori dan zona waktu tetap benar.

  Status selesai 31 Oktober 2026, pelaksana coding agent sesuai instruksi pengguna. Endpoint `GET /api/v1/branches/{branchId}` dan `PATCH /api/v1/branches/{branchId}` berjalan di tenant context, memakai `BranchPolicy` view/manage, serta membatasi pencarian dengan tenant internal dan public UUID. Perubahan nama, zona waktu IANA, alamat, telepon, dan status aktif menaikkan version cabang, menulis audit `branch.configuration_updated` dengan before/after dan alasan, serta tidak mengubah tenant, public ID, kode, atau waktu pembuatan.

  `EnsureBranchAcceptsOrders` menolak cabang nonaktif dengan `BRANCH_INACTIVE` dan tenant tidak aktif dengan `TENANT_INACTIVE` (409), sehingga guard yang sama dapat dipakai task order berikutnya. Zona waktu divalidasi oleh rule `timezone`; histori cabang tetap tersimpan dan nonaktif tidak menghapus data.

  Verifikasi: `BranchConfigurationTest` lulus **5 test/31 assertion** untuk GET/PATCH, audit dan version, penolakan timezone tidak valid, viewer tanpa izin manage, cabang tenant lain tidak terlihat, serta guard cabang/tenant nonaktif. Seluruh suite lulus **87 test/465 assertion**; OpenAPI valid untuk 26 path/29 operasi; Pint lulus.

  Gap: endpoint order belum ada, sehingga guard `EnsureBranchAcceptsOrders` baru diuji langsung dan harus dipanggil oleh task penerimaan order; perubahan kode cabang masih memerlukan strategi alias pada task nota; belum ada endpoint daftar cabang atau transfer zona waktu massal.

- [x] **T013 Pasang tenant context RLS dan test isolasi**
  Rujukan: FR02 NFR09 SEC01. Dependensi: T008, T010, T011. Selesai jika: Role runtime tanpa konteks ditolak; test lintas tenant API, raw query, koneksi ulang, dan worker lulus.

  Status selesai 31 Oktober 2026, pelaksana coding agent sesuai instruksi pengguna. RLS diaktifkan pada `tenants`, `tenant_memberships`, `branches`, `locations`, `roles`, `role_permissions`, `membership_roles`, `branch_assignments`, dan `location_assignments`. Fungsi `app_current_tenant_id()`, `app_current_user_id()`, dan `app_identity_bootstrap()` membaca setting transaksi; policy tenant memakai `USING`/`WITH CHECK` sehingga runtime tanpa konteks tidak melihat atau menulis baris.

  `TenantScope` menjalankan callback dalam transaksi, memakai `set_config(..., true)` agar setting hanya berlaku pada transaksi tersebut, dan mengembalikan koneksi default setelah selesai. Mode `identity` hanya membuka tenant/membership aktif milik pengguna untuk bootstrap `/me/context`; mode `forTenant`/`forTenantId` membatasi ke satu tenant untuk request dan worker. `MyContextController` memakai identity scope. Middleware `tenant` membaca `X-Tenant-ID`, mencari tenant melalui identity scope, membangun `TenantContext`, dan menolak header tidak valid (422) atau tenant di luar membership (404).

  Verifikasi: `TenantRlsTest` lulus **6 test/32 assertion** untuk runtime tanpa konteks (0 baris dan insert 42501), isolasi raw query, identity scope hanya tenant aktif milik pengguna, cross-tenant insert ditolak, setting transaksi bersih setelah commit, worker scope eksplisit, serta API scoped route dengan token dan `X-Tenant-ID`. Seluruh suite lulus **65 test/325 assertion**; rollback dan migrate ulang migration RLS berhasil pada PostgreSQL 18.

  Gap: belum ada endpoint bisnis produksi yang memakai middleware `tenant`; route uji hanya didefinisikan dalam test. Tabel bisnis baru wajib menambahkan RLS saat dibuat. `FORCE ROW LEVEL SECURITY` belum dipakai karena migrator/owner membutuhkan DML untuk fixture; role runtime tetap non-owner tanpa BYPASSRLS. Worker wajib memanggil `TenantScope` secara eksplisit.

- [x] **T014 Bangun jejak audit**
  Rujukan: FR06 SEC12. Dependensi: T011, T013. Selesai jika: Perubahan sensitif memiliki actor, subject, alasan, dan correlation ID tanpa log secret.

  Status selesai 31 Oktober 2026, pelaksana coding agent sesuai instruksi pengguna. Tabel `audit_logs` bersifat append-only dan menyimpan tenant, actor user/membership, action, subject type allowlist, subject public ID, correlation ID, perubahan yang disamarkan, alasan, dan timestamp. `AuditRecorder` menolak subject type di luar allowlist, mengambil correlation ID dari konteks request, dan meredaksi kunci sensitif secara rekursif (`password`, `token`, `secret`, `mfa`, `recovery`, `authorization`, `api key`, `credential`).

  `UpdateMembershipAssignments` dan `SyncRolePermissions` menulis audit di transaksi yang sama dengan perubahan, menerima actor dan alasan. Actor opsional untuk proses sistem yang terdokumentasi. RLS tenant diterapkan pada `audit_logs`; role runtime hanya mendapat SELECT/INSERT dan tidak dapat UPDATE/DELETE. Akses tanpa konteks tenant tidak melihat baris, sedangkan penulisan di luar `TenantScope` gagal tertutup.

  Verifikasi: `AuditLogTest` lulus **5 test/24 assertion** untuk actor/subject/reason/correlation, audit perubahan role, redaksi nilai sensitif bersarang, allowlist subject, dan timestamp database. `TenantRlsTest` menambah bukti RLS audit append-only, isolasi runtime, serta insert dengan konteks tenant. Seluruh suite lulus **71 test/354 assertion**; rollback dan migrate ulang migration audit berhasil pada PostgreSQL 18.

  Gap: event login/MFA/reset belum dicatat sebagai audit tenant; `platform_audit_logs` dan role retensi terpisah belum dibuat; action di luar `TenantScope` pada role runtime akan gagal karena audit insert ditolak RLS; alasan tindakan sensitif belum diwajibkan oleh action.

- [x] **T015 Implementasikan paket dan kuota atomik**
  Rujukan: FR37. Dependensi: T009, T011, T013. Selesai jika: Dua permintaan untuk slot terakhir hanya mengaktifkan satu; fitur berbayar dicek server.

  Status selesai 31 Oktober 2026, pelaksana coding agent sesuai instruksi pengguna. Schema `saas_plans` versioned per code+revision, `saas_subscriptions` tenant-scoped dengan snapshot entitlement, serta `tenant_usage_counters` tenant+metric+period. Plan global hanya dapat dibaca runtime; subscription dan counter memakai RLS tenant, satu subscription efektif per tenant, dan runtime tanpa DELETE.

  `StartSubscription` memvalidasi limits non-negatif dan feature flag boolean lalu menyalin snapshot plan. `SubscriptionEntitlements` membaca snapshot aktif (trial/active/grace) dan menolak fitur berbayar dengan `FEATURE_NOT_AVAILABLE`; status read_only/cancelled ditolak `SUBSCRIPTION_INACTIVE`. `ConsumeQuota` memakai satu UPDATE bersyarat `used + amount <= limit` dengan `RETURNING`, sehingga dua permintaan slot terakhir hanya satu yang berhasil; release mengembalikan kuota dan transaksi pembungkus membatalkan increment bila callback gagal. Endpoint `GET /api/v1/me/subscription` mengekspos limit/fitur efektif untuk klien.

  Verifikasi: `SubscriptionQuotaTest` lulus **5 test/23 assertion** untuk snapshot, kuota slot terakhir + release, fitur berbayar, subscription nonaktif, dan validasi fitur plan. `TenantRlsTest` membuktikan subscription/counter memerlukan tenant context pada role runtime. Seluruh suite lulus **103 test/571 assertion**; OpenAPI valid untuk 31 path/35 operasi; Pint lulus.

  Gap: belum ada alur platform admin untuk membuat plan/assign subscription, harga final menunggu DEC08/T078, trial default belum diintegrasikan ke provisioning, integrasi pemakaian slot staf ke pembuatan membership menunggu task staf, dan uji dua proses nyata tetap menjadi T047. Kolom `reserved` tersedia tetapi alur reserve/commit terpisah belum dipakai.

- [ ] **T016 Implementasikan tagihan SaaS awal**
  Rujukan: FR38. Dependensi: T003, T015. Selesai jika: Pembayaran langganan manual tervalidasi mengaktifkan tepat sekali dan tidak masuk kas laundry.

- [x] **T017 Bangun command receipt idempotensi**
  Rujukan: FR18 TR03. Dependensi: T013, T014. Selesai jika: Key sama payload sama mengembalikan resource sama; payload berbeda 409; replay tetap memeriksa izin.

  Status selesai 31 Oktober 2026, pelaksana coding agent sesuai instruksi pengguna. Tabel `command_receipts` menyimpan unique `(tenant_id, client_command_id)`, command type, hash payload kanonis, actor membership, status processing/completed/rejected, result resource allowlist, completed timestamp, dan error code. CHECK constraint mewajibkan field hasil saat completed dan error code saat rejected. RLS tenant diterapkan; runtime mendapat SELECT/INSERT/UPDATE tanpa DELETE.

  `CanonicalPayload` menormalkan urutan key, UUID lowercase, dan mengecualikan `client_command_id`/`correlation_id`. `CommandReceiptService` mereservasi receipt, menangani unique conflict, mengunci receipt pada transaksi bisnis, mengembalikan hasil melalui resolver saat replay, dan memanggil ulang callback otorisasi sebelum replay. `ApiException` memberi envelope error domain. Endpoint `POST /memberships/{membershipId}/assignments` mewajibkan `Idempotency-Key` UUID yang sama dengan `client_command_id`, memakai tenant context, memeriksa Policy membership, meresolve public UUID role/cabang/lokasi dalam tenant, serta menulis audit pada transaksi yang sama.

  Verifikasi: `MembershipAssignmentIdempotencyTest` lulus **4 test/42 assertion** untuk replay key+payload sama, payload berbeda 409 `IDEMPOTENCY_PAYLOAD_MISMATCH`, header tidak cocok 422, dan replay ditolak 403 setelah izin actor dicabut. `TenantRlsTest` membuktikan receipt memerlukan tenant context pada role runtime. Seluruh suite lulus **76 test/400 assertion**; `npm run lint:api` valid untuk 25 path/27 operasi; rollback dan migrate ulang migration receipt berhasil pada PostgreSQL 18.

  Gap: reservasi berjalan di dalam transaksi request tenant, sehingga crash sebelum commit tidak meninggalkan receipt processing dan penolakan terminal bergantung pada transaksi; retensi/pruning receipt belum dibuat; baru command assignment membership yang terintegrasi, sedangkan order/payment menyusul task transaksi.

- [x] **T018 Bangun outbox antrean dan receipt consumer**
  Rujukan: TR09 TR10 SEC07. Dependensi: T013, T014, T017. Selesai jika: Crash sebelum atau sesudah publish dan replay consumer tidak mengulang efek database; event tanpa receipt dapat dipulihkan.

  Status selesai 31 Oktober 2026, pelaksana coding agent sesuai instruksi pengguna. Schema `outbox_events`, `event_deliveries`, dan `consumer_receipts` memakai RLS tenant, unique event/delivery/receipt, lease dan jadwal retry, serta grant runtime terbatas (event immutable, delivery dapat di-claim, receipt append-only). `OutboxWriter` menulis event dan delivery dalam transaksi bisnis, menolak event type di luar allowlist serta payload dengan kunci sensitif. `UpdateMembershipAssignments` menulis event `membership.assignments_updated` pada transaksi yang sama.

  `OutboxDispatcher` meng-claim delivery dengan `FOR UPDATE SKIP LOCKED`, lease, dan hanya memilih delivery tanpa consumer receipt; setelah commit baru job `operations` dikirim. `ProcessOutboxDelivery` menjalankan consumer di `TenantScope`, menulis efek database dan `consumer_receipts` pada transaksi yang sama, serta merilis lease atau menjadwalkan retry saat gagal. Replay job atau dispatch ulang tidak mengulang efek karena receipt unique; delivery yang terlanjur di-claim dan kehilangan job dapat diambil kembali setelah lease kedaluwarsa.

  Verifikasi: `OutboxTest` lulus **5 test/30 assertion** untuk event+delivery atomik, claim dan recovery tanpa receipt, receipt mencegah efek ganda, rollback efek consumer yang gagal, serta penolakan payload sensitif. `TenantRlsTest` membuktikan outbox event memerlukan tenant context pada role runtime. Seluruh suite lulus **82 test/434 assertion**; rollback dan migrate ulang migration outbox berhasil pada PostgreSQL 18.

  Gap: belum ada consumer bisnis yang terdaftar pada konfigurasi produksi; test consumer hanya membuktikan mekanisme. Event type masih terbatas pada `membership.assignments_updated`; retensi/pruning outbox, metrik backlog, dan scheduler dispatcher menyusul T020. Efek HTTP eksternal/provider tetap memerlukan intent dan rekonsiliasi pada task terkait.

- [ ] **T019 Bangun storage privat dan finalize upload**
  Rujukan: FR14 TR11 SEC09. Dependensi: T005, T011, T013. Selesai jika: Alur init/transfer/finalize memeriksa kepemilikan, batas foto usulan lima MB, MIME/magic bytes, dimensi, dan checksum; file tidak sah ditolak atau dikarantina. Orphan hanya dapat diakses uploader berizin, file terikat mengikuti parent, thumbnail mempunyai lifecycle, dan cleanup tidak menghapus lampiran aktif. Signed URL foto berumur pendek dibedakan dari gateway ekspor yang dapat mencabut akses segera.

- [ ] **T020 Siapkan observabilitas backup dan runbook dasar**
  Rujukan: TR14 NFR07 NFR08 SEC04 SEC12 SEC13 SEC15. Dependensi: T007, T013, T018, T019. Selesai jika: Probe eksternal, metrik latency/5xx/lock/koneksi/queue age/outbox tanpa receipt/storage/backup age, alarm, dan runbook insiden tersedia dengan redaksi log. Backup terenkripsi, WAL/PITR, file, dan pemulihan key direncanakan serta penanggung jawab operasi ditunjuk. Hasil restore dan waktu pemulihan baru dinyatakan lulus pada T050; usulan retensi mengikuti keputusan T005.

## Fase C Katalog kasir dan draft mobile

**Rentang usulan:** Minggu 5 sampai 7. **Hasil:** Pesanan dan invoice dapat diterima dengan harga benar serta draft lokal aman.

- [x] **T021 Bangun katalog layanan dan workflow**
  Rujukan: FR07. Dependensi: T002, T012, T013. Selesai jika: Unit, minimum, step, template, dan versi workflow tervalidasi; perubahan tidak mengubah snapshot lama.

  Status selesai 31 Oktober 2026, pelaksana coding agent sesuai instruksi pengguna. Schema `workflow_templates`, `workflow_steps`, dan `services` memakai UUID/version, FK gabungan tenant, RLS, unique kode/revisi/urutan, CHECK unit/minimum/step, serta integer-only untuk `pcs`, `pair`, dan `package`. Permission `catalog.view` dan `catalog.manage` ditambahkan ke enum dan seeder idempoten.

  Endpoint `GET/POST /api/v1/workflow-templates`, `GET /api/v1/workflow-templates/{id}`, `GET/POST /api/v1/services`, serta `GET/PATCH /api/v1/services/{id}` terisolasi tenant dan memakai cursor pagination. Revision workflow dibuat atomik, revision aktif lama dinonaktifkan, definisi template dan step lama dilindungi trigger immutable, dan `CaptureServiceSnapshot` menghasilkan salinan nama/unit/aturan/workflow/step untuk T026. Layanan baru selalu draft; field aktivasi ditolak sampai T022 menyediakan harga efektif valid sesuai FR07.

  Verifikasi: `CatalogTest` dan `CatalogAuthorizationTest` lulus **6 test/80 assertion** untuk CRUD terkontrol, unit diskret, step positif, revision, snapshot lama, trigger immutable, autentikasi, permission, dan isolasi tenant. `TenantRlsTest` menambah **1 test/6 assertion** untuk workflow/step/service pada role runtime. Rollback/migrate ulang berhasil; seluruh suite lulus **119 test/679 assertion**; OpenAPI valid untuk **35 path/42 operasi**; tujuh route katalog terdaftar.

  Gap lanjutan: publikasi layanan dan validasi tarif efektif dikerjakan T022; kalkulasi tenggat tersedia pada T025; snapshot dikonsumsi secara atomik saat order pada T026. Penghapusan katalog tidak dibuka agar referensi historis tetap aman.

- [x] **T022 Bangun daftar harga bertanggal**
  Rujukan: FR08. Dependensi: T002, T021. Selesai jika: Tenant default dan override cabang deterministik; periode ambigu ditolak pada request serentak.

  Status selesai 31 Oktober 2026, pelaksana coding agent sesuai instruksi pengguna. Schema `price_books` dan `price_entries` memakai RLS, UUID/version, FK gabungan tenant, serta unique parsial satu book aktif per scope tenant/cabang. Entry harga memakai periode `[effective_from,effective_until)`, tier regular/express, dan exclusion constraint `btree_gist` yang menolak overlap per book+layanan+tier; advisory transaction lock membuat pemeriksaan dan insert/penutupan periode berurutan. Definisi harga immutable; hanya `effective_until` yang dapat diubah untuk menutup atau memperpanjang periode tanpa mengubah snapshot transaksi lama.

  Endpoint `GET/POST /api/v1/price-books`, `GET /api/v1/price-books/{id}`, `POST /api/v1/price-books/{id}/entries`, `PATCH /api/v1/price-entries/{id}`, `GET /api/v1/prices/resolve`, dan `PATCH /api/v1/services/{id}/publication` berjalan dengan cursor pagination, permission katalog, dan tenant context. Resolusi memilih price book cabang aktif lebih dulu, lalu tenant; respons menjelaskan `source`, price book, dan entry yang dipakai. Publikasi layanan mensyaratkan workflow revision aktif dan tarif regular tenant yang efektif pada waktu sekarang, serta dicatat ke audit dengan alasan.

  Verifikasi: `PriceBookTest` lulus **7 test/74 assertion** untuk prioritas cabang lalu fallback tenant, transisi periode override, aktivasi layanan, penolakan periode overlap, penutupan dan perpanjangan entry, exclusion constraint bypass action, unique book aktif, isolasi tenant, dan permission. `PricePolicyTest` lulus **1 test/14 assertion**; `TenantRlsTest` menambah **1 test/6 assertion** untuk price book/entry runtime. Rollback/migrate ulang berhasil; seluruh suite lulus **128 test/773 assertion**; OpenAPI valid untuk **41 path/49 operasi**; enam route price book/resolusi dan satu route publikasi terdaftar.

  Gap lanjutan: harga khusus pelanggan (customer price book) tetap P1; quote dan snapshot harga dikonsumsi T026; perubahan tarif tidak otomatis menonaktifkan layanan ketika tarif tenant kedaluwarsa; UI katalog/harga menyusul T075.

- [x] **T023 Bangun pelanggan alamat dan pencarian**
  Rujukan: FR09. Dependensi: T011, T013. Selesai jika: Nomor ternormalisasi, kandidat duplikat per tenant, pagination, dan isolasi hasil diuji.

  Status selesai 31 Oktober 2026, pelaksana coding agent sesuai instruksi pengguna. Schema `customers` dan `customer_addresses` memakai RLS tenant, metadata UUID/version, FK gabungan tenant, index pencarian telepon, serta partial unique index satu alamat default per pelanggan. Nomor telepon dinormalisasi dari format `+62`, `62`, atau `8...` menjadi nomor Indonesia berawalan `0`; input tidak valid ditolak 422.

  Endpoint `GET/POST /api/v1/customers`, `GET /api/v1/customers/{customerId}`, `POST /api/v1/customers/{customerId}/addresses`, dan `GET /api/v1/customers/duplicates` berjalan dalam tenant context dengan permission `customers.view`/`customers.manage`. Pencarian nama dan telepon memakai cursor pagination default 25/maksimum 100. Duplikat dibatasi tenant dan tidak pernah membaca data tenant lain. Alamat pertama otomatis default; alamat default baru melepas default lama dalam transaksi dengan row lock.

  Verifikasi: `CustomerTest` lulus **5 test/37 assertion** untuk normalisasi telepon, alamat default, pencarian nama/telepon, cursor pagination, kandidat duplikat lintas tenant, isolasi detail/alamat, penolakan nomor tidak valid, dan viewer tanpa izin manage. Seluruh suite lulus **92 test/502 assertion**; OpenAPI valid untuk 29 path/33 operasi; Pint lulus.

  Gap: belum ada endpoint update/nonaktif pelanggan; pencarian masih substring sederhana tanpa trigram/fuzzy ranking; deteksi duplikat hanya nomor/nama persis, bukan skor kemiripan; import `external_ref` menyusul T043; consent pelanggan T024; perubahan PII belum diaudit karena kebijakan retensi/redaksi menunggu T005.

- [x] **T024 Bangun histori dan consent**
  Rujukan: FR10. Dependensi: T005, T023, T026. Selesai jika: Histori pesanan pelanggan dipaginasi dan dibatasi permission/tenant; consent tujuan dan kanal terpisah; penolakan marketing tidak menghilangkan nota layanan.

  Status selesai 31 Oktober 2026, pelaksana coding agent sesuai instruksi pengguna. Schema `customer_consents` append-only memakai RLS, FK gabungan tenant, enum purpose/channel/decision, index histori, dan grant runtime hanya SELECT/INSERT. Endpoint `GET /customers/{id}/orders` memakai cursor pagination `(created_at,id)` dan mensyaratkan `customers.view` + `orders.view`, sehingga histori tidak terbuka bagi pemilik izin pelanggan saja. Endpoint `GET/POST /customers/{id}/consents` mengembalikan keadaan terbaru per purpose+channel dan mencatat event baru dengan sumber/waktu; marketing dan service_contact terpisah sehingga penarikan marketing tidak menghapus akses nota layanan.

  Verifikasi: `CustomerHistoryConsentTest` lulus **3 test/28 assertion** untuk pagination lintas halaman, isolasi pelanggan/tenant, consent per tujuan/kanal, penarikan marketing, histori tetap tersedia, permission viewer, dan validasi. `TenantRlsTest` menambah **1 test/3 assertion** untuk customer consent runtime. Seluruh suite lulus **148 test/1009 assertion**; OpenAPI valid untuk **45 path/55 operasi**; tiga route histori/consent terdaftar.

  Gap lanjutan: endpoint saat ini menampilkan keadaan consent terbaru, bukan seluruh riwayat event; pengiriman notifikasi memakai consent ini baru di T077; ekspor/penghapusan mengikuti T045/T046; consent kanal push belum diaktifkan di pilot.

- [x] **T025 Bangun kalkulasi tenggat**
  Rujukan: FR11. Dependensi: T002, T012, T021. Selesai jika: Elapsed time berbeda dari hari kerja; janji awal dan revisi dapat ditelusuri.

  Status selesai 31 Oktober 2026, pelaksana coding agent sesuai instruksi pengguna. Schema `branch_calendars` memakai RLS, FK gabungan tenant, tujuh baris jadwal mingguan, satu cut-off, dan hari libur; unique parsial mencegah duplikasi jadwal/tanggal. Layanan mendapat `sla_mode` (`working_days` atau `elapsed_hours`) serta turnaround reguler/express dengan CHECK sesuai mode. Endpoint `GET/PUT /api/v1/branches/{id}/calendar` dan `POST /api/v1/deadlines/preview` berjalan dalam tenant/branch scope; perubahan kalender atomik, menaikkan version cabang, dan menulis audit.

  `CalculateDeadline` membedakan tenggat berbasis hari kerja dari elapsed wall-clock. Mode hari kerja memakai cut-off, jadwal mingguan, dan libur; mode elapsed memakai jam tanpa cut-off sehingga layanan 24 jam tidak dianggap satu hari kerja. `RecordDeadlinePromise`, `ReviseDeadlinePromise`, dan `GetDeadlinePromise` menyimpan janji awal serta revisi append-only pada audit dengan actor, alasan, correlation ID, waktu lama/baru; T026 akan memakai promise ID ini saat membuat order.

  Verifikasi: `DeadlineTest` lulus **5 test/72 assertion** untuk konfigurasi/audit kalender, cut-off, libur, express, elapsed, validasi, isolasi tenant, dan permission. `DeadlinePromiseTest` lulus **2 test/19 assertion** untuk trace janji awal/revisi, stale revision, promise tidak ditemukan, dan alasan wajib. `TenantRlsTest` menambah **1 test/3 assertion** untuk kalender runtime. Rollback/migrate dua migration berhasil; seluruh suite lulus **137 test/884 assertion**; OpenAPI valid untuk **43 path/52 operasi**; tiga route kalender/tenggat terdaftar.

  Gap lanjutan: kapasitas produksi jaringan, appointment pickup, dan perubahan janji berbasis order/audit UI tetap T040/T075; T026 menyimpan due snapshot pada order/order event, sedangkan promise trace audit belum diekspos sebagai endpoint publik.

- [x] **T026 Bangun quote dan acceptance atomik**
  Rujukan: FR13 FR16 BR02 BR04 BR05 BR08 TR03 TR04. Dependensi: T017, T018, T021, T022, T023, T025, T073. Selesai jika: Harga dihitung server memakai decimal dan half up, measured/billable terpisah, quote cocok dengan payload dan dikunci agar hanya dikonsumsi sekali. Order, line, invoice, unit fisik awal, nomor atomik, snapshot harga/workflow/tenggat, audit, outbox, dan hasil receipt commit bersama. Contoh BR05 menghasilkan Rp71.600; retry sesudah commit mengembalikan hasil yang sama tanpa memvalidasi ulang expiry quote lama. DP merupakan command terpisah pada T038.

  Status selesai 31 Oktober 2026, pelaksana coding agent sesuai instruksi pengguna. `POST /api/v1/orders/quotes` menghitung ulang harga server dari price book aktif, minimum/step, tier, diskon layanan, express, dan antar; quote menyimpan hash input, pricing snapshot, expiry 15 menit, due_at, dan sumber harga. `POST /api/v1/orders` memakai `Idempotency-Key`/`client_command_id`, mengunci quote `FOR UPDATE`, memeriksa hash payload dan expiry, lalu membuat order, order line, invoice, invoice line, bag awal, bag content, order event accepted, nomor atomik, audit, dan outbox dalam satu transaksi. Unique quote pada order dan `consumed_at` mencegah konsumsi ganda.

  Verifikasi: `OrderAcceptanceTest` lulus **5 test/80 assertion** untuk BR05 Rp71.600, measured/billable 2.300/3.000, invoice lines, bag contents, replay setelah expiry, single-use quote, payload mismatch, expiry, permission, branch scope, dan idempotency header. `OrderPolicyTest` lulus **1 test/8 assertion**; `TenantRlsTest` menambah **1 test/6 assertion** untuk quote/order runtime. Rollback/migrate migration order berhasil; seluruh suite lulus **144 test/978 assertion**; OpenAPI valid untuk **43 path/52 operasi**.

  Gap lanjutan: DP/payment T038, dokumen nota/cetak T029, endpoint daftar/detail order, revisi jadwal T040, serta label/repack T027. Diskon saat ini berupa input server-validated yang masuk snapshot; kebijakan otorisasi promo menunggu T028.

- [ ] **T027 Bangun bag content label dan repack**
  Rujukan: FR12. Dependensi: T004, T026. Selesai jika: Label unik, satu bag satu order, total fisik sesuai measured quantity, dan repack menjaga jumlah.

- [ ] **T028 Bangun bukti kondisi awal**
  Rujukan: FR14. Dependensi: T019, T026. Selesai jika: Foto dan catatan tertaut pada pesanan; gagal upload tidak tampil sebagai tersimpan.

- [ ] **T076 Bangun tracking pelanggan minimal dan token publik**
  Rujukan: FR12 FR15 TR11 SEC02 SEC09; UI bagian 2. Prioritas: P0. Dependensi: T013, T026, T073. Selesai jika: Nota dapat merujuk halaman tracking dengan token acak yang disimpan sebagai hash, mempunyai expiry/revocation, dan dilindungi rate limit. Halaman hanya menampilkan status minimal, perkiraan pengambilan, serta kontak outlet; tidak menampilkan telepon/alamat pelanggan, foto kondisi, rincian uang, atau data pesanan lain. Token tidak valid/dicabut dan manipulasi ID diuji; QR mentah tidak memuat PII. Ini bukan aplikasi pelanggan P2.

  Keputusan disetujui 31 Oktober 2026: token 30 hari setelah handover; rate limit 30/menit per IP dan 10/menit per token; halaman hanya status, estimasi tenggat, kontak outlet, dan jumlah bag; revoke saat order dibatalkan atau token dibuat ulang.

- [ ] **T029 Bangun nota digital dan cetak ulang**
  Rujukan: FR15 TR11. Dependensi: T004, T026, T027, T076. Selesai jika: DTO nota berasal dari transaksi tersimpan dengan nomor, total, pembayaran, sisa, tenggat, dan tracking yang konsisten. Digital/cetak sama; print attempt dan retry terpisah dari command order/payment. Printer gagal tidak menciptakan order kedua; satu konfigurasi cetak yang disepakati dicoba pada perangkat fisik. Matrix printer/koneksi/ukuran kertas lengkap dinilai pada T048 tanpa menjadikan T048 prasyarat task ini.

- [ ] **T030 Bangun diskon revisi dan persetujuan**
  Rujukan: FR16. Dependensi: T003, T011, T026. Selesai jika: Batas diskon server, versi lama, alasan, persetujuan, dan adjustment tersedia.

  Keputusan disetujui 31 Oktober 2026: diskon persentase layanan saja, delivery tidak didiskon; kasir 0%, supervisor maksimum 10%, owner sampai 100%; diskon >10% memerlukan owner berbeda aktor, alasan, dan approval terikat quote. Revisi kuantitas/layanan hanya sebelum `ready`, tarif dihitung server, snapshot lama disimpan, selisih menjadi adjustment; setelah ready/handover hanya debit/credit note. Adjustment >Rp100.000 atau >10% memerlukan owner berbeda aktor dan `expected_version`.

  Progres 31 Oktober 2026: permission `discounts.standard`/`discounts.override`, tabel `discount_approvals`, auto-approval diskon ≤10%, approval owner berbeda aktor untuk >10%, dan blokir acceptance tanpa approval sudah diimplementasikan. `DiscountApprovalTest` lulus **4 test/19 assertion**; seluruh suite lulus **152 test/1028 assertion**; OpenAPI **46 path/56 operasi**. Belum selesai: endpoint revisi kuantitas/layanan/tarif, `expected_version`, snapshot lama di order event, dan debit/credit note.

- [ ] **T031 Bangun pembatalan terkontrol**
  Rujukan: FR17. Dependensi: T003, T026, T027, T030, T039. Selesai jika: Uang dan barang tetap tertelusur; status produksi membatasi pembatalan dan refund memakai modulnya.

- [ ] **T074 Bangun fondasi Flutter staf dan penyimpanan lokal terlindungi**
  Rujukan: ADR07 TR08 SEC08 SEC10 NFR05 NFR09. Prioritas: P0. Dependensi: T004, T005, T007, T010, T013, T073. Selesai jika: Proyek Android staf dapat dibuild menggunakan toolchain yang dikunci; analyze/test/build Flutter berjalan di CI. View/ViewModel/repository/service/model immutable dipisahkan dengan Riverpod dan Drift sesuai TRD. API client menangani konteks, auth, error, dan pagination. Token memakai storage aman OS; cache dipisahkan tenant/user, perlindungan PII lokal dipilih dan diuji, upgrade schema serta recovery key teruji. Logout/pergantian akun/scope mengunci atau purge cache yang relevan tanpa membuka draft ke akun berikutnya. Batas verifikasi offline usulan empat jam mengikuti kebijakan T005, bukan dianggap jaminan remote wipe.

- [ ] **T075 Bangun design system dan shell navigasi web/mobile**
  Rujukan: NFR10 TR02 SEC11; UI UX01–UX03 dan bagian 2–5, 9, 11. Prioritas: P0. Dependensi: T006, T011, T015, T074. Selesai jika: Token visual semantik dan komponen input/tombol/daftar/status/dialog/scan/ringkasan uang digunakan pada web dan Flutter. Konteks tenant/cabang/peran/koneksi/draft terlihat; shell platform dibedakan dari tenant; navigasi mengikuti izin/entitlement. Loading, refresh, empty, error/retry, forbidden, offline, stale, pending, dan konflik mempunyai perilaku yang mempertahankan input. Layout mobile/tablet/web, target sentuh 48 × 48 logical pixels, teks 200 persen, kontras, fokus keyboard, serta label pembaca layar diverifikasi pada komponen inti. Format rupiah, decimal lokal, dan zona cabang tidak ambigu.

- [ ] **T032 Bangun cache draft lokal dan replay**
  Rujukan: NFR05 NFR09 FR18 TR08 SEC10. Dependensi: T005, T010, T017, T023, T026, T074. Selesai jika: Draft, UUID command, payload immutable, versi, dependensi, serta antrean foto tahan restart dan terisolasi. Layar sinkronisasi menunjukkan pending/berhasil/konflik/ditolak; 401 menghentikan replay, 403 mengunci scope, 409 meminta resolusi, 429 menunggu, dan timeout mengulang key yang sama. Quote baru memerlukan persetujuan dan command baru; command turunan gagal tidak dikirim. Draft belum mempunyai nomor nota server; uang, transfer, dan handover final tidak disahkan offline.

- [ ] **T033 Bangun layar kasir dan admin dasar**
  Rujukan: FR01 FR05 FR07 FR09 FR13 FR15 NFR10 TR02. Dependensi: T006, T011, T012, T021, T022, T023, T024, T025, T026, T028, T029, T032, T075. Selesai jika: Flutter kasir mempunyai beranda, pelanggan, pesanan baru/detail, scan, dan sinkronisasi; web pemilik mempunyai pengelolaan awal organisasi/cabang/staf/katalog/pelanggan dan shell admin. Alur ringkas dapat dipakai tanpa menu jaringan, memakai komponen standar, dan tidak menampilkan data tenant lama saat berpindah konteks. Layar transaksi lanjutan dilengkapi pada task modul T034–T045, bukan dianggap selesai oleh shell ini.

## Fase D Produksi uang dan pengelolaan P0

**Rentang usulan:** Minggu 7 sampai 9. **Hasil:** Alur penerimaan sampai penyerahan dan kas dapat direkonsiliasi.

- [ ] **T034 Bangun antrian dan transisi produksi**
  Rujukan: FR19 FR20. Dependensi: T021, T025, T027, T033. Selesai jika: Prasyarat, scope lokasi, version, operator, dan waktu server diterapkan; mode ringkas konsisten.

- [ ] **T035 Bangun QC dan readiness**
  Rujukan: FR22. Dependensi: T034. Selesai jika: Ready ditolak bila bagian wajib belum selesai atau QC gagal; partially ready jelas.

- [ ] **T036 Bangun rewash dan kasus komplain**
  Rujukan: FR23. Dependensi: T028, T034, T035, T039. Selesai jika: Pekerjaan ulang tertaut ke attempt asal dan tidak menaikkan omzet; bukti serta resolusi tersimpan. Kegagalan QC attempt aktif tidak ditutupi hasil lulus lama; kompensasi memanggil adjustment/refund yang diotorisasi, bukan mengubah uang dari modul komplain.

- [ ] **T037 Bangun pengambilan dan penyerahan sebagian**
  Rujukan: FR24 BR07 TR07. Dependensi: T003, T027, T035, T038. Selesai jika: Rak/lokasi dan QC diverifikasi, P0 memakai bag utuh, penerima serta bukti dicatat, dan syarat uang atau credit approval aktual diperiksa di primary. Dua perangkat tidak menyerahkan unit sama; partial handover menyisakan unit yang tepat. Content, custody event, status order turunan, audit, dan receipt berubah atomik; order completed bukan tombol bebas.

- [ ] **T038 Bangun pembayaran DP dan alokasi**
  Rujukan: FR31 TR06 SEC06. Dependensi: T003, T017, T026, T040. Selesai jika: Total contoh Rp71.600, DP Rp30.000, sisa Rp41.600; tunai dan transfer manual tervalidasi mendukung pembayaran bertahap/gabungan metode. Payment, allocation, cash entry jika tunai, audit, outbox, serta receipt commit bersama. Uang diserahkan/kembalian dibedakan dari dana bersih; dana titipan menjadi unallocated credit. Piutang memerlukan permission dan credit approval. Tidak ada payment sah dari attempt pending; gateway diaktifkan pada T060/P1.

- [ ] **T039 Bangun refund adjustment dan reversal**
  Rujukan: FR33 BR06 TR06 SEC06; ERD bagian 11. Dependensi: T003, T011, T038. Selesai jika: Adjustment terpisah dari refund dan allocation reversal; seluruh rumus saldo diuji tanpa clamp yang menyembunyikan overallocated. Payment asal dikunci, requested/approved/processing mencadangkan refundable amount, dan dua refund tidak melewati batas. Pembuat/penyetuju, event status, cash out jika tunai, serta bukti sukses tersedia. Credit note Rp20.000 atas invoice lunas Rp100.000 memakai refund allocation atau reversal yang benar tanpa pengurangan ganda. Integrasi provider unknown mengikuti T060; reservasi tidak dilepas hanya karena timeout.

- [ ] **T040 Bangun laci dan shift kasir**
  Rujukan: FR34. Dependensi: T003, T008, T011, T017. Selesai jika: Satu shift terbuka per register, saldo dari ledger dapat dihitung ulang, tutup dan reopen mempunyai bukti. Posting payment serta refund diintegrasikan pada T038 dan T039, lalu diuji bersama pada T047.

- [ ] **T041 Bangun biaya dan perpindahan kas**
  Rujukan: FR35. Dependensi: T040. Selesai jika: Modal dan setoran bukan omzet; perpindahan internal mempunyai posting berpasangan.

- [ ] **T042 Bangun laporan outlet dan piutang**
  Rujukan: FR36. Dependensi: T025, T038, T039, T040, T041. Selesai jika: Basis tanggal, zona waktu, watermark, dan jumlah detail cocok dengan ringkasan.

- [ ] **T043 Bangun impor ekspor dan proteksi unduhan**
  Rujukan: FR40 SEC05 SEC09. Dependensi: T019, T021, T022, T023, T042. Selesai jika: Template pelanggan dan katalog, pemetaan, dry run, checksum/sumber impor, deduplikasi, dan error per baris tersedia; raw import mengikuti retensi. Ekspor asynchronous mempunyai progress/status/expiry, pembatasan kuota, perlindungan CSV formula injection, dan otorisasi ulang saat generate/download. Akses dicabut segera dihormati gateway download, bukan hanya menunggu signed URL storage kedaluwarsa.

- [ ] **T044 Bangun grant bantuan sementara**
  Rujukan: FR41 SEC03. Dependensi: T005, T010, T011, T014. Selesai jika: Grant terbatas tenant dan waktu; tidak dapat melakukan transaksi uang atau eskalasi role.

- [ ] **T045 Bangun admin tenant dan lifecycle data**
  Rujukan: FR42 SEC12 SEC16. Dependensi: T005, T015, T016, T020, T043, T044. Selesai jika: Web platform menyediakan kesehatan/pemakaian tenant dengan PII minimal, pengelolaan paket/tagihan/grant, dan proses permintaan data terverifikasi. Read only, ekspor, retensi, penghapusan, serta bukti pemrosesan mengikuti kebijakan; histori ledger tidak dihapus melalui CRUD. Daftar penghapusan sah dapat diterapkan ulang sesudah restore agar data tidak muncul kembali; prosedur dibuktikan pada T050.

- [ ] **T077 Bangun notifikasi layanan dan delivery intent**
  Rujukan: FR10 FR15 FR22 TR09 TR10 SEC08 SEC09 SEC16; UI bagian 9. Prioritas: P0. Dependensi: T005, T018, T024, T029, T035. Selesai jika: Kanal layanan pilot yang disepakati dapat mengirim nota dan pemberitahuan siap berdasarkan event committed; consent tujuan/kanal diperiksa dan penolakan marketing tidak menghalangi pesan layanan yang sah. `notification_deliveries` mempunyai business key unik, reference tetap, status queued/sending/sent/failed/unknown, backoff, dan rekonsiliasi hasil tidak pasti. Replay tidak menggandakan intent/efek database; kegagalan provider tidak menggagalkan order atau mengklaim pesan telah diterima. Isi pesan dan tracking membatasi PII; adapter diuji dan kanal dibuktikan pada lingkungan provider uji sebelum pilot.

## Fase E Pilot dan pengujian

**Rentang usulan baseline:** Minggu 10 sampai 12 untuk persiapan/UAT dan keputusan masuk pilot, setelah dependensi siap. T080 memerlukan sedikitnya empat minggu operasi pilot sesudah T052; waktu tersebut tidak dipadatkan ke rentang persiapan. **Hasil:** Risiko inti diuji, pilot diotorisasi berdasarkan bukti, lalu hasil operasional dan komersial diukur sebelum berbayar.

- [ ] **T046 Jalankan uji authorization menyeluruh**
  Rujukan: U01 U11 U12 NFR09 SEC01 SEC02 SEC03 SEC05 SEC08 SEC11 SEC14. Dependensi: T013, T015, T031, T033, T036, T037, T043, T044, T045, T076, T077. Selesai jika: API, raw query/runtime role, cache, job, ekspor, file, tracking, bantuan, token dicabut, dan koneksi antar tenant memiliki bukti uji negatif. Manipulasi tenant/cabang/lokasi/nominal, replay setelah izin hilang, session/CSRF/CORS/CSP, pembatasan resource, serta redaksi error diuji sesuai kanal; UUID atau menu tersembunyi tidak dianggap cukup.

- [ ] **T047 Jalankan uji concurrency dan ledger**
  Rujukan: U02 U03 U04 U05 U06 U09 U10 U11 NFR09 SEC06. Dependensi: T015, T017, T026, T027, T030, T031, T035, T036, T037, T038, T039, T040, T041. Selesai jika: Race, rollback, replay, saldo, dan sisa barang lulus pada PostgreSQL dengan data sintetis dan dua proses nyata. Cakupan mencakup slot kuota terakhir, konsumsi quote, hash key ditolak, timeout sesudah commit, snapshot harga, repack, QC/rewash, handover ganda, refund reserved, credit note/reversal, kembalian, dan posting shift. Kegagalan salah satu efek membatalkan transaksi terkait tanpa menghapus histori.

- [ ] **T048 Jalankan uji Flutter dan perangkat**
  Rujukan: U13 U15 NFR05 NFR06 NFR10 SEC10; UI bagian 13. Dependensi: T004, T029, T032, T033, T034, T035, T037, T074, T075. Selesai jika: Cold start, interaksi, jank, memori, ukuran build, teks 200 persen, pembaca layar, scan/fallback manual, printer, dan offline dicatat pada perangkat sasaran. Restart saat draft/unggah tertunda, akun/tenant berganti, akses dicabut, cache terkunci, serta retry/konflik diuji. Keyboard, fokus dialog, kontras, dan layout web/tablet ikut diperiksa; screenshot saja tidak menutup acceptance.

- [ ] **T049 Jalankan beban spike dan soak pilot**
  Rujukan: U15 NFR01 NFR02 NFR03 NFR04 TR13. Dependensi: T018, T020, T034, T038, T042, T043, T076, T077. Selesai jika: Profil pilot 50 tenant/100 ribu order/20 RPS/50 virtual users/soak delapan jam dijalankan pada dataset sintetis; mix endpoint dan pacing dicatat. p95 server baca/mutasi, payload JSON, 5xx terpisah dari 409/429, lock, koneksi, queue age, dan gap terhadap target dilaporkan. Spike/soak memakai lingkungan uji dan mencakup degradasi ekspor/notifikasi tanpa merusak transaksi inti; angka bukan jaminan kapasitas produksi.

- [ ] **T050 Latihan restore dan replay pekerjaan**
  Rujukan: U14 NFR08 NFR09 SEC07 SEC13 SEC15 SEC16. Dependensi: T018, T019, T020, T038, T039, T045, T077. Selesai jika: Restore dilakukan pada lingkungan terisolasi dan RPO/RTO diukur; tenant/FK, ledger, file/checksum, key yang diperlukan, dan status pembayaran manual cocok. Worker dimatikan sebelum/sesudah commit dan publish; lease, event tanpa receipt, serta notifikasi unknown dapat dipulihkan tanpa efek database ganda. Daftar penghapusan sah diterapkan ulang; bukti insiden, waktu deteksi/pulih, dan perbaikan runbook dicatat. Restore global tidak diklaim sebagai restore selektif tenant.

- [ ] **T079 Verifikasi migration upgrade dan rollout P0**
  Rujukan: TR13 TR15 SEC11 SEC14. Prioritas: P0. Dependensi: T020, T031, T033, T036, T037, T038, T039, T043, T045, T050. Selesai jika: Fresh migration serta upgrade dari dataset sintetis versi sebelumnya lulus pada PostgreSQL dengan role migration/runtime yang benar. Constraint/RLS/index yang relevan diuji, FK siklik P1 tidak dipaksakan aktif di P0, dan schema expand kompatibel dengan build lama selama rollout. Image immutable, satu pelaksana migration, restart worker/scheduler, smoke alur inti, serta rollback aplikasi yang kompatibel dengan data dibuktikan di staging. Bukti mencatat build dan langkah nyata; task ini tidak mengotorisasi deploy produksi dan tidak menganggap rollback image membatalkan data.

- [ ] **T051 Pelatihan dan UAT outlet pilot**
  Rujukan: FR01 FR13 FR24 FR31 FR34 NFR10; PRD bagian 8 dan UI bagian 13. Dependensi: T009, T016, T029, T033, T037, T040, T042, T046, T047, T048, T077. Selesai jika: Staf pilot dapat menjalankan penerimaan, bayar, produksi/QC, pengambilan, dan tutup kas pada build uji; SOP printer/offline/pending/komplain dipahami dan penerimaan UAT dicatat. Baseline waktu input, ketepatan waktu, adopsi, serta selisih kas diambil sebelum operasi pilot. Hasil empat minggu tetap menjadi T080 yang terbuka sampai periode nyata tersedia.

- [ ] **T052 Putuskan rilis pilot P0 berdasarkan bukti**
  Rujukan: Seluruh FR P0 NFR01–NFR10 SEC01–SEC16 sesuai fase. Dependensi: T045, T046, T047, T048, T049, T050, T051, T073, T074, T075, T076, T077, T079. Selesai jika: Semua FR P0 mempunyai implementasi dan bukti yang diwajibkan; gerbang G01–G07/G09 sesuai fase dinilai, gap terhadap target NFR dicatat beserta keputusan penerimaan, dan pemilik keputusan mengotorisasi pilot. Cakupan pilot, tenant/lokasi, build, dukungan, observasi, serta rollback jelas. Checkbox ini hanya menutup keputusan masuk pilot; rilis berbayar menunggu T080 dan T078.

- [ ] **T080 Ukur hasil dan biaya empat minggu operasi pilot**
  Rujukan: PRD bagian 8, DEC08 NFR07; TR13 TR14. Prioritas: Gerbang berbayar sesudah pilot. Dependensi: T020, T042, T051, T052. Selesai jika: Sedikitnya empat minggu operasi nyata dicatat dengan baseline, periode, sampel, serta definisi metrik yang jelas. Ukur median penerimaan pesanan ulang target ≤60 detik; ketepatan waktu target ≥95 persen memakai janji awal dan mengecualikan pembatalan dengan revisi/rewash dilaporkan tersendiri; seluruh selisih kas mempunyai alasan/penanggung jawab; target ≥80 persen staf aktif sesuai peran; serta seluruh order terkonfirmasi mempunyai snapshot wajib. Catat availability pilot target 99,5 persen dan biaya infrastruktur, provider, storage, onboarding, serta dukungan. Hasil/gap dilaporkan apa adanya; task tidak selesai dari simulasi atau karena tanggal target sudah lewat.

- [ ] **T078 Tetapkan paket add on dan keputusan rilis berbayar**
  Rujukan: FR37 FR38 DEC07 DEC08 SEC14 SEC16; PRD bagian 2, 8, 11. Prioritas: Gerbang berbayar sesudah pilot. Dependensi: T005, T015, T016, T045, T052, T080. Selesai jika: Pemilik bisnis menetapkan harga, kuota, biaya add on/provider, periode, masa tenggang, serta dukungan berdasarkan biaya pilot; versi paket dan proses tagihan dapat merepresentasikan keputusan tersebut. DEC07 dan kontrol keamanan fase P0 mempunyai keputusan/bukti yang diperlukan; target/gap pilot, dukungan, rollback, serta kewenangan rilis ditinjau sebelum keputusan berbayar dicatat. Tidak ada harga atau SLA komersial yang diklaim disahkan dari angka hipotesis dokumentasi. Gateway dan perubahan paket lanjutan tetap mengikuti T060–T061/P1.

## Fase F Jaringan multi cabang P1

**Rentang usulan:** Minggu 13 sampai 18. **Hasil:** Transfer, kurir, gateway, stok, kontrak, dan konsolidasi layak dipakai jaringan.

- [ ] **T053 Bangun penugasan pusat produksi**
  Rujukan: FR25. Dependensi: T012, T027, T034, T052. Selesai jika: Lokasi dapat berbeda dari cabang penjualan; perubahan tugas tidak memindahkan omzet asal.

- [ ] **T054 Bangun batch dan kapasitas**
  Rujukan: FR21. Dependensi: T034, T035, T053. Selesai jika: Alokasi fisik tidak melampaui sisa dan unit kapasitas konsisten; attempt rewash tertelusur.

- [ ] **T055 Bangun dispatch manifest**
  Rujukan: FR26. Dependensi: T017, T027, T053. Selesai jika: Bag dalam perjalanan tidak dapat didispatch lagi; isi terkunci dan custody tercatat. Jika pelacakan per potong telah divalidasi, `garments` P1 beserta identitas, relasi content, dan scan item dimodelkan tanpa mencampurkan jumlah potong dengan kg; fitur opsional ini tidak dipaksakan pada penerimaan kiloan P0.

- [ ] **T056 Bangun penerimaan dan selisih manifest**
  Rujukan: FR27. Dependensi: T055. Selesai jika: Dua dari tiga bag diterima menghasilkan status sebagian; penerimaan ganda ditolak.

- [ ] **T057 Bangun permintaan antar jemput**
  Rujukan: FR28. Dependensi: T023, T025, T026, T053. Selesai jika: Slot, zona, biaya estimasi, dan perubahan harga sesudah timbang dapat disetujui pelanggan.

- [ ] **T058 Bangun tugas kurir dan bukti**
  Rujukan: FR29. Dependensi: T011, T019, T056, T057. Selesai jika: Kurir hanya melihat tugas aktif; gagal antar dan bukti dapat disinkronkan tanpa duplikasi.

- [ ] **T059 Bangun COD dan setoran kurir**
  Rujukan: FR30. Dependensi: T038, T040, T041, T058. Selesai jika: Dana diterima serta tanggung jawab setor berbeda; setoran tidak menggandakan penjualan atau payment.

- [ ] **T060 Integrasikan gateway dan webhook**
  Rujukan: FR32 TR06 SEC04 SEC06. Dependensi: T001, T017, T018, T038, T039, T046. Selesai jika: Merchant mapping hasil DEC02, signature resmi provider, reference tetap, inbox durable sebelum 2xx, pending/unknown, replay, urutan terbalik, refund reserved/unknown, serta rekonsiliasi lulus sandbox. Merchant/reference/amount/currency/status divalidasi; callback mobile tidak menetapkan lunas dan pending terlambat tidak menurunkan settlement. HTTP provider tidak berada dalam lock transaksi panjang. Kanal laundry dan merchant/tagihan platform terpisah; antarmuka pembayaran menampilkan hasil server yang sah.

- [ ] **T061 Bangun upgrade downgrade dan penghentian**
  Rujukan: FR39. Dependensi: T015, T016, T045, T060. Selesai jika: Entitlement atomik, periode efektif, excess limit, read only, ekspor, dan histori tetap benar.

- [ ] **T062 Bangun inventori pembelian dan opname**
  Rujukan: FR43. Dependensi: T011, T053. Selesai jika: Mutasi menjadi sumber stok; penerimaan pembelian dan selisih opname menghasilkan posting yang dapat dihitung ulang.

- [ ] **T063 Bangun kontrak dan invoice gabungan B2B**
  Rujukan: FR45. Dependensi: T022, T038, T039, T042, T053. Selesai jika: Tarif kontrak, siklus, batas kredit, dan unique sumber layanan mencegah tagihan ganda.

- [ ] **T064 Bangun laporan jaringan**
  Rujukan: FR46. Dependensi: T042, T053, T056, T059, T060, T063. Selesai jika: Omzet asal, penerimaan, biaya internal, dan produksi dapat ditelusuri serta konsolidasi tidak ganda.

- [ ] **T065 Bangun change feed dan bootstrap terkontrol**
  Rujukan: TR08 NFR05 SEC08 SEC10. Dependensi: T013, T018, T032, T053. Selesai jika: Commit ordering, tombstone, cursor scope, permission change, dan snapshot pagination diuji tanpa kehilangan perubahan.

- [ ] **T066 Jalankan UAT jaringan dan rilis P1**
  Rujukan: U07 U08 U11 U16 Seluruh FR P1 NFR01–NFR10 sesuai fase. Dependensi: T054, T056, T058, T059, T060, T061, T062, T063, T064, T065, T078. Selesai jika: UAT minimal tiga cabang dengan pusat produksi, manifest parsial/selisih, kurir/COD, gateway, inventory, B2B, konsolidasi, isolation, dan perubahan scope sync lulus dengan bukti. Profil pertumbuhan TR13 mencatat 500 tenant/satu juta order/100 RPS/300 virtual users dan spike 300 RPS lima menit; hasil/gap dan drill insiden dinilai sebelum kemampuan jaringan dijual. Kontrak API, migration/rollout, runbook, paket efektif, dan keputusan rilis P1 diperbarui; angka sizing bukan janji kapasitas.

## Fase G Ekspansi P2 dan kapasitas

**Rentang usulan:** Sesudah validasi P1. **Hasil:** Pengayaan hanya dibangun jika nilai dan biaya operasinya terbukti.

- [ ] **T067 Validasi dan bangun prabayar serta loyalitas**
  Rujukan: FR44. Dependensi: T039, T063, T066. Selesai jika: Nilai bisnis disetujui; wallet unit berbeda terpisah; debit serentak, reversal, dan expiry diuji.

- [ ] **T068 Validasi API mitra dan IoT**
  Rujukan: FR47. Dependensi: T011, T018, T053, T066. Selesai jika: Scope mitra dan perangkat terpisah; dedup telemetry tersedia dan tidak dapat mengesahkan uang atau handover.

- [ ] **T069 Validasi aplikasi pelanggan dan white label**
  Rujukan: FR48. Dependensi: T005, T024, T060, T066. Selesai jika: Binding user ke customer diverifikasi; konfigurasi merek mempertahankan kontras dan tidak membuat fork bisnis.

- [ ] **T070 Evaluasi SSO dan database tenant khusus**
  Rujukan: ADR03 SEC01 NFR07. Dependensi: T046, T050, T066. Selesai jika: Kebutuhan enterprise nyata, biaya, migrasi, routing, dan restore selektif dibuktikan sebelum dijual.

- [ ] **T071 Evaluasi Octane dari profiling**
  Rujukan: ADR04 TR16 NFR01 NFR06. Dependensi: T049, T066. Selesai jika: Benchmark sama menunjukkan manfaat; state tenant, worker restart, dan memori lulus; keputusan ADR dicatat.

- [ ] **T072 Naikkan target operasi dengan bukti**
  Rujukan: NFR07 NFR08. Dependensi: T050, T066, T070. Selesai jika: Availability 99,9 persen, RTO pertumbuhan, kapasitas, on call, dan biaya operasional mendukung komitmen yang akan dijual.


## Matriks keterlacakan seluruh FR

| FR | Prioritas | Task utama | Kebutuhan |
|---|---|---|---|
| FR01 | P0 | T009 | Pendaftaran dan penyediaan tenant |
| FR02 | P0 | T013 | Isolasi organisasi |
| FR03 | P0 | T010 T074 | Login dan perangkat |
| FR04 | P0 | T011 | Keanggotaan dan izin |
| FR05 | P0 | T012 | Profil dan cabang |
| FR06 | P0 | T014 | Jejak audit |
| FR07 | P0 | T021 | Katalog layanan |
| FR08 | P0 | T022 | Daftar harga cabang |
| FR09 | P0 | T023 | Data pelanggan |
| FR10 | P0 | T024 T077 | Riwayat dan persetujuan kontak |
| FR11 | P0 | T025 | Kalender dan janji selesai |
| FR12 | P0 | T027 T076 | Label dan identifikasi fisik |
| FR13 | P0 | T026 | Pembuatan pesanan |
| FR14 | P0 | T028 | Kondisi awal barang |
| FR15 | P0 | T029 T076 T077 | Konfirmasi dan nota |
| FR16 | P0 | T030 | Diskon dan revisi |
| FR17 | P0 | T031 | Pembatalan dan pengecualian |
| FR18 | P0 | T017 T032 | Pencegahan pesanan ganda |
| FR19 | P0 | T034 | Antrian pengerjaan |
| FR20 | P0 | T034 | Tahap produksi |
| FR21 | P1 | T054 | Batch dan kapasitas |
| FR22 | P0 | T035 | Pemeriksaan kualitas |
| FR23 | P0 | T036 | Pencucian ulang dan komplain |
| FR24 | P0 | T037 | Rak dan serah terima |
| FR25 | P1 | T053 | Penugasan lokasi produksi |
| FR26 | P1 | T055 | Manifest pengiriman |
| FR27 | P1 | T056 | Penerimaan dan selisih transfer |
| FR28 | P1 | T057 | Permintaan antar jemput |
| FR29 | P1 | T058 | Tugas kurir |
| FR30 | P1 | T059 | Kas kurir dan barang titipan |
| FR31 | P0 | T038 | DP pelunasan dan piutang |
| FR32 | P1 | T060 | Gateway dan rekonsiliasi |
| FR33 | P0 | T039 | Refund dan koreksi |
| FR34 | P0 | T040 | Shift kasir |
| FR35 | P0 | T041 | Biaya dan transaksi nonpenjualan |
| FR36 | P0 | T042 | Pelaporan operasional |
| FR37 | P0 | T015 T078 | Paket dan entitlement |
| FR38 | P0 | T016 T078 | Tagihan SaaS |
| FR39 | P1 | T061 | Upgrade downgrade dan berhenti |
| FR40 | P0 | T043 | Impor dan ekspor |
| FR41 | P0 | T044 | Bantuan dan akses sementara |
| FR42 | P0 | T045 | Pemantauan dan penghentian layanan |
| FR43 | P1 | T062 | Stok bahan dan pembelian |
| FR44 | P2 | T067 | Paket prabayar dan loyalitas |
| FR45 | P1 | T063 | Pelanggan kontrak B2B |
| FR46 | P1 | T064 | Konsolidasi dan analitik |
| FR47 | P2 | T068 | Integrasi mitra dan IoT |
| FR48 | P2 | T069 | Aplikasi pelanggan dan white label |

## Matriks NFR dan bukti penerimaan

Angka adalah target dari PRD, bukan hasil pengujian. Catat build, lingkungan, dataset, distribusi beban, perangkat, sampel, dan hasil sebelum memberi status lulus. Gap yang diterima harus menyebut pemilik keputusan dan dampaknya; target tidak diubah diam-diam agar hasil tampak lulus.

| NFR | Target yang harus dibuktikan | Task implementasi atau bukti |
|---|---|---|
| NFR01 | Baca API inti 25 record p95 ≤300 ms sisi server, tanpa RTT klien | T073 T023 T034 T042 T049 T066 T071 |
| NFR02 | Konfirmasi order/penerimaan tunai p95 ≤600 ms, tanpa waktu provider | T026 T038 T049 T066 |
| NFR03 | 5xx <0,5 persen pada profil uji; 409 bisnis/429 dipisahkan | T020 T049 T066 |
| NFR04 | 25 record ≤75 KB JSON tak terkompresi; foto terpisah | T073 T019 T023 T033 T049 |
| NFR05 | Android 10+/RAM 4 GB; interaksi p95 ≤2 detik pada 10 Mbps/RTT 100 ms; cache awal ≤1 detik | T004 T032 T074 T048 T065 |
| NFR06 | Cold start release p95 ≤3 detik, jank <5 persen, resident memory ≤200 MB pada alur 15 menit, unduhan per perangkat ≤40 MB | T074 T075 T048 |
| NFR07 | Availability pilot 99,5 persen/bulan; target pertumbuhan 99,9 persen dengan pengukuran eksternal | T020 T080 T072 |
| NFR08 | RPO ≤15 menit; RTO pilot ≤4 jam, pertumbuhan ≤1 jam; restore nyata | T020 T050 T070 T072 |
| NFR09 | Nol akses lintas tenant dan nol efek uang/barang ganda pada seluruh uji kritis | T013 T017 T018 T032 T046 T047 T050 T065 T066 |
| NFR10 | Aksesibilitas, audit, dan pemulihan error sesuai UI/security | T006 T014 T033 T075 T046 T048 T051 |

## Matriks UAT lintas modul

| UAT | Skenario inti | Task pembuktian |
|---|---|---|
| U01 | Manipulasi tenant/cabang/file/job/ekspor ditolak | T013 T046 |
| U02 | BR05 menghasilkan total Rp71.600, DP Rp30.000, sisa Rp41.600 | T026 T038 T047 T051 |
| U03 | Harga baru tidak mengubah transaksi terkonfirmasi | T022 T030 T047 |
| U04 | Timeout setelah commit dan tiga retry tetap satu order | T017 T026 T032 T047 |
| U05 | Tahap wajib/QC yang belum lulus menolak ready | T034 T035 T036 T047 |
| U06 | Dua perangkat tidak menyerahkan unit yang sama | T037 T047 T048 |
| U07 | Dua dari tiga bag diterima, sisa tetap tertelusur | T055 T056 T066 |
| U08 | Webhook berulang/terbalik tidak menggandakan dana | T060 T066 |
| U09 | Refund bersamaan tidak melewati nilai tersedia | T039 T047 T060 |
| U10 | Kas sesuai ledger; setoran bukan penjualan kedua | T040 T041 T047 T059 T066 |
| U11 | Kuota slot terakhir atomik; downgrade mempertahankan histori | T015 T047 untuk P0; T061 T066 untuk P1 |
| U12 | Ekspor terisolasi dan akses support kedaluwarsa | T043 T044 T046 |
| U13 | Draft tahan restart dan konflik tidak menimpa transaksi | T032 T074 T048 |
| U14 | Replay tidak menggandakan efek dan restore dibuktikan | T018 T050 |
| U15 | Profil perangkat/beban memenuhi target atau mencatat gap yang diterima | T048 T049 T066 |
| U16 | Produksi lintas lokasi tidak menggandakan omzet | T042 T064 T066 |

## Gerbang pengujian lintas modul

| Gate | Bukti | Task terkait |
|---|---|---|
| G01 Isolasi | Tenant A tidak dapat mengakses B lewat API, file, ekspor, cache, job, atau role database runtime | T013 T046 |
| G02 Harga | Minimum dan pembulatan BR05 tepat; harga lama tetap; quote usang tertangani | T022 T026 T030 T047 |
| G03 Ledger | DP, reversal, refund reserved, shift, kembalian, dan setoran dapat dihitung ulang | T038 T039 T040 T041 T047 |
| G04 Penguasaan barang | QC, partial handover, repack, transfer, dan selisih menjaga jumlah fisik | T027 T035 T037 T055 T056 |
| G05 Retry | Timeout sesudah commit dan replay worker tidak menggandakan efek | T017 T018 T047 T050 |
| G06 Mobile | Draft tahan restart, akun terisolasi, konflik aman, target perangkat serta UI diuji | T032 T074 T075 T048 T065 |
| G07 Keandalan | Probe, backup, PITR, RPO, RTO, file, replay, dan rekonsiliasi dibuktikan; availability diukur selama operasi | T020 T050 T080 T072 |
| G08 Multi cabang | Minimal tiga cabang, pusat produksi, transfer parsial, kurir/COD, gateway, inventory, B2B, sync, dan konsolidasi diuji | T053 T054 T055 T056 T058 T059 T060 T062 T063 T064 T065 T066 |
| G09 Kontrak dan rollout | Kontrak API konsisten; upgrade schema, peran runtime, smoke, serta rollback aplikasi kompatibel dibuktikan | T073 T079 T052 T066 |
| G10 Komersial | Empat minggu hasil pilot, biaya nyata, kebijakan data, harga paket, dukungan, dan keputusan berbayar tersedia | T005 T080 T078 |

G01–G07/G09 diterapkan untuk bagian P0 sebelum T052; komponen P1 pada G04/G06 dinilai sebelum T066 dan peningkatan kapasitas P2 sesuai T070–T072. Availability operasional pada G07 tetap diukur melalui T080. G08 merupakan gerbang jaringan, G10 gerbang berbayar. Gate bukan pengganti task atau bukti acceptance masing-masing.

## Cakupan desain teknis

| Desain | Task implementasi atau bukti |
|---|---|
| TR01 | T009 T013 |
| TR02 | T007 T033 T074 T075 |
| TR03 | T073 T017 T026 |
| TR04 | T008 T027 T038 |
| TR05 | T010 T011 |
| TR06 | T038 T039 T060 |
| TR07 | T034 T037 T053 T056 |
| TR08 | T074 T032 T065 |
| TR09 | T018 T077 T050 |
| TR10 | T018 T077 T049 |
| TR11 | T019 T029 T076 T048 |
| TR12 | T042 T043 T064 |
| TR13 | T007 T079 T049 T052 T080 T066 |
| TR14 | T020 T050 T080 |
| TR15 | T073 T046 T047 T048 T049 T050 T079 |
| TR16 | T070 T071 T072 |

### Cakupan keputusan arsitektur

| ADR | Task penerapan atau evaluasi |
|---|---|
| ADR01 Modular monolith Laravel | T007; seluruh modul memakai action dan Policy sesuai TR02 |
| ADR02 PostgreSQL 18 | T007 T008 T013 T047 T079 |
| ADR03 Database/schema bersama | T013 T046 T050; opsi khusus dievaluasi pada T070 |
| ADR04 PHP-FPM dan OPcache dahulu | T007 T049; Octane hanya setelah evaluasi T071 |
| ADR05 REST JSON/OpenAPI | T073 dan pembaruan kontrak pada setiap task endpoint |
| ADR06 Redis queue/cache terpisah dan Horizon | T007 T018 T020 T049 T050 |
| ADR07 Flutter MVVM/Riverpod/Drift | T074 T032 T033 T048 T065 |
| ADR08 Outbox/inbox durable | T018 T050 T060 T077 |
| ADR09 Blade/Livewire terpilih | T007 T075 T033 T045; aturan bisnis tetap memakai action yang sama |

## Cakupan kontrol keamanan

| Kontrol | Task pemilik implementasi atau bukti |
|---|---|
| SEC01 | T013 T046 |
| SEC02 | T010 T076 T046 |
| SEC03 | T011 T044 T046 |
| SEC04 | T007 T010 T020 |
| SEC05 | T073 T026 T043 T046 |
| SEC06 | T038 T039 T047 T060 |
| SEC07 | T018 T077 T050 T071 |
| SEC08 | T074 T032 T046 T065 |
| SEC09 | T019 T028 T043 T076 |
| SEC10 | T074 T032 T048 T065 |
| SEC11 | T007 T075 T033 T046 T079 |
| SEC12 | T014 T020 T045 |
| SEC13 | T020 T050 |
| SEC14 | T007 T073 T046 T079 T052 T078 |
| SEC15 | T020 T050 T052 T066 |
| SEC16 | T005 T024 T045 T050 T077 T078 |

## Cakupan schema inti dan tabel pendukung

Setiap task modul memiliki migration, model/relasi yang diperlukan, Policy/action, kontrak, UI terkait, dan pengujian sesuai risikonya. Tabel berikut memetakan kepemilikan agar tabel pendukung ERD tidak tertinggal; ini bukan instruksi membuat seluruh tabel sekaligus. Kelas G/D/L/C/J, FK gabungan, indeks, serta fase tetap mengikuti [erd.md](erd.md).

| Kelompok schema | Task pemilik | Batas fase dan acceptance penting |
|---|---|---|
| `tenants`, `users`, `tenant_memberships`, `roles`, `permissions`, `membership_roles`, `role_permissions` | T008 T009 T010 T011 | P0; identity bootstrap terbatas, membership multi-tenant, actor histori dipertahankan |
| `branches`, `locations`, `branch_assignments`, `location_assignments` | T008 T011 T012 | P0; scope cabang/lokasi berbeda, lokasi tanpa siklus, master nonaktif tidak menghapus histori |
| `devices`, `personal_access_tokens`, `sessions` dan tabel pemulihan framework | T010 | P0; schema sesuai package terpasang, token dihash dan sesi dapat dicabut |
| `customers`, `customer_addresses`, `customer_consents` | T023 T024 | P0; telepon bukan unique global, default address tunggal, consent append only |
| `services`, `workflow_templates`, `workflow_steps` | T021 | P0; unit/step valid, versi workflow terpakai tidak berubah makna |
| `price_books`, `price_entries`, `branch_calendars` | T022 T025; kontrak T063 | P0 tenant/cabang; customer pricing P1; overlap periode ditolak termasuk pada race |
| `quotes`, `orders`, `order_lines`, `order_events`, `number_counters` | T026 T030 T031 | P0; quote single-use, snapshot, nomor atomik, event revisi tidak mengubah fakta terbit |
| `bags`, `bag_contents`, `bag_repack_events`, `custody_events` | T027 T037; transfer T055 T056 | P0 untuk penerimaan/repack/handover; FK active transfer baru dipasang pada P1 setelah target ada |
| `garments` | T055 bila pelacakan per potong divalidasi | P1 opsional; bukan prasyarat penerimaan kiloan P0 atau tambahan berat fisik |
| `production_tasks`, `production_task_events`, `qc_results`, `complaints` | T034 T035 T036 | P0; FK QC ke content/task yang sama, attempt rewash dan histori terjaga |
| `production_batches`, `batch_allocations` | T054 | P1; alokasi fisik sesuai unit dan tidak melampaui sisa task attempt |
| `transfers`, `transfer_lines` | T055 T056 | P1; satu perjalanan aktif per bag, penerimaan aktual/parsial menentukan header |
| `deliveries`, `delivery_events` | T057 T058 | P1; pickup sebelum timbang belum merupakan order final |
| `handovers`, `handover_lines`, `credit_approvals` | T037 T038 | P0; unit yang sama tidak diserahkan dua kali; persetujuan kredit bukan payment |
| `invoices`, `invoice_lines`, `invoice_adjustments` | T026 T030 T039; B2B T063 | P0; terbit immutable, unique sumber layanan mencegah tagihan ganda |
| `payments`, `payment_allocations`, `allocation_reversals`, `refunds`, `refund_allocations`, `refund_events` | T038 T039 | P0; rumus ERD bagian 11, reservasi refund, koreksi append only, sumber payment yang sama |
| `merchant_accounts`, `payment_attempts`, `payment_webhook_inbox` | T060 | P1; merchant/reference unik, inbox autentik sebelum 2xx, attempt pending bukan uang |
| `cash_registers`, `cash_sessions`, `cash_session_events`, `cash_entries`, `expenses`, `cash_transfers` | T040 T041; posting T038 T039 | P0; satu shift open/register, sumber posting unik, transfer berpasangan, bukti close/reopen tetap |
| `courier_cash_entries`, `courier_cash_settlements` | T059 | P1; kewajiban setor terpisah dari payment pelanggan |
| `command_receipts` | T017 | P0; unique tenant/command lintas action, hash durable, completed bersama efek bisnis |
| `outbox_events`, `event_deliveries`, `consumer_receipts` | T018 | P0; payload immutable, lease pulih, receipt bersama efek, dispatch bukan bukti sukses |
| `notification_deliveries` | T077 | P0; intent unik per business key/kanal, hasil provider unknown dapat direkonsiliasi |
| `public_tracking_tokens` | T076 | P0; token hash, expiry/revocation, rate limit, dan akses publik minimal tanpa PII |
| `attachments`, `order_attachments`, `handover_attachments`, `complaint_attachments`, `delivery_attachments` | T019 T028 T036 T037 T058 | Join parent sesuai fase; delivery P1; file privat/orphan/finalize dan checksum teruji |
| `audit_logs`, `platform_audit_logs`, `security_events` | T014 T020 T044 T045 | P0; append only, control plane terpisah, redaksi dan retensi berizin |
| `report_exports`, `import_batches`, `import_rows`, `report_daily_summaries` | T042 T043 | P0; ringkasan dapat direbuild, import dedup/dry run, export generate/download diotorisasi ulang |
| `support_access_grants` | T044 | P0; baca terbatas tenant/kasus/waktu tanpa kewenangan uang atau eskalasi role |
| `saas_plans`, `saas_subscriptions`, `saas_subscription_invoices`, `saas_subscription_payments`, `tenant_usage_counters` | T015 T016 T078; lifecycle T061 | P0 kecuali upgrade/downgrade P1; kuota atomik dan uang platform terpisah dari laundry |
| `tenant_sync_sequences`, `sync_changes` | T065 | P1; urutan commit, tombstone, generation/scope cursor, bootstrap snapshot konsisten |
| `inventory_items`, `inventory_balances`, `stock_movements`, `purchase_orders`, `purchase_order_lines`, `stock_opnames`, `stock_opname_lines` | T062 | P1; ledger sumber stok, projection di bawah lock, penerimaan bukan sekadar purchase order |
| `customer_contracts` | T063 | P1; harga, siklus, kredit, dan unique sumber invoice gabungan |
| `customer_wallets`, `customer_wallet_entries` | T067 | P2 tervalidasi; satuan money/kg/points terpisah, concurrency dan reversal teruji |
| `customer_auth_links` | T069 | P2 tervalidasi; binding user/customer dalam tenant diverifikasi, bukan nomor global saja |

Schema telemetry IoT, federasi waralaba, kebutuhan medis khusus, atau mesin tambahan tidak dianggap sudah dirancang lengkap oleh kolom JSON. Rincian baru mengikuti validasi T068/T070 dan keputusan arsitektur/produk yang relevan.

## Latihan insiden sebelum rilis jaringan

1. Simulasikan request naik dari 30 menjadi 180 RPS pada lingkungan uji, atau profil setara yang disetujui sesuai sizing. Angka adalah skenario, bukan kapasitas yang dijamin.
2. Periksa alarm p95, queue age, koneksi, lock, serta provider; tunda ekspor sebelum membebani transaksi inti.
3. Tambah replica API jika app menjadi hambatan dan database masih sehat; tambah worker hanya pada kelas yang tertinggal serta dalam batas provider.
4. Putuskan koneksi provider, matikan worker sesudah commit, dan verifikasi pending atau replay tidak menghasilkan efek ganda.
5. Lakukan failover atau restore sesuai runbook dan cocokkan uang, barang, file, serta event yang belum selesai.
6. Catat waktu deteksi, pemulihan, kehilangan data aktual, dan perbaikan runbook; jangan menandai latihan lulus tanpa bukti.

## Urutan kerja awal yang dapat langsung diambil

Mulai **T001 dan T007**; keduanya tidak mempunyai dependensi. T002–T005 mengikuti keputusan T001, T006 mengikuti fixture dan perangkat, sedangkan T073 mengikuti toolchain. Berikutnya selesaikan jalur **T008 → T010 → T011 → T013 → T014 → T017 → T018** dengan task fondasi lain sesuai dependensinya. Urutan terperinci dan hasil pertama tersedia pada tabel Siap mulai; fitur transaksi dibangun sebagai vertical slice sampai rekonsiliasi kas, bukan seluruh dashboard terlebih dahulu.

## Keputusan dan perubahan rencana

DEC01–DEC08 tercantum pada PRD. Jika belum diputuskan, catat opsi, pemilik keputusan, dampak, dan pekerjaan independen yang tetap dapat dilakukan. Perubahan aturan bisnis memperbarui PRD; schema memperbarui ERD; kontrak dan runtime memperbarui TRD; state tampilan memperbarui UI; kontrol memperbarui security. Jangan mencentang task hanya karena dokumennya telah ditulis.

### Catatan penyusunan versi 1.2

- Seluruh tujuh dokumen dibaca dan dibandingkan dengan struktur aplikasi, dependency langsung, migration, test contoh, serta route aplikasi yang tersedia pada 31 Oktober 2026.
- T001–T072 tetap mempunyai arti dan keterlacakan semula; acceptance dan dependensi diperinci. T073–T080 merupakan pemecahan kebutuhan yang sudah tertulis, tanpa menambah FR atau menggeser prioritas FR01–FR48.
- Penyelesaian T051/T052 diperjelas menjadi UAT pra-pilot dan keputusan masuk pilot; bukti empat minggu dipindahkan ke T080, sementara keputusan komersial/DEC08 berada pada T078.
- Seluruh task implementasi, perangkat, provider, UAT, deployment, benchmark, restore, dan observasi tetap terbuka sampai bukti masing-masing tersedia. Pemeriksaan dokumen tidak mengesahkan keputusan bisnis atau rilis aplikasi.
