# T006 Prototype Kasir — Protokol Uji Usability

Status: **siap diuji, menunggu partisipan staf**. Belum ada temuan yang boleh dianggap hasil uji.

## Tujuan

Memastikan kasir memahami jumlah fisik, berat tertagih, total, tenggat, status pending, DP, dan sisa tagihan sebelum layar produksi T033 dibangun.

## Prototype

- Route lokal/staging: `GET /prototype/kasir` (hanya non-production, perlu login aktif).
- Data mengikuti BR05: kiloan 2,3 kg menjadi 3 kg, bed cover 2 pcs, diskon 10%, antar Rp5.000, total Rp71.600, DP Rp30.000, sisa Rp41.600.
- Prototype tidak menyimpan pesanan, pembayaran, atau antrean. Angka final tetap dihitung server.

## Partisipan

- Minimal dua kasir pilot dan satu supervisor.
- Satu sesi per partisipan, 20–30 menit, tanpa bantuan moderator saat mengerjakan tugas.
- Gunakan data fiktif. Jangan memakai nama, nomor, atau alamat pelanggan nyata.

## Tugas

1. Dari keadaan awal, jelaskan berapa berat fisik dan berat yang akan ditagih.
2. Ubah berat menjadi 3,4 kg dan jelaskan perubahan berat tertagih.
3. Aktifkan express dan antar; jelaskan komponen yang berubah dan yang tidak didiskon.
4. Masukkan DP Rp30.000 dan sebutkan sisa tagihan.
5. Temukan tenggat dan status pesanan sebelum disimpan.
6. Kembalikan formulir ke contoh BR05.

## Yang Diamati

- Waktu menyelesaikan setiap tugas.
- Kesalahan input atau salah membaca satuan/angka.
- Apakah partisipan memahami perbedaan fisik, ditagih, total, DP, dan sisa.
- Apakah status draft/pending dan tenggat terlihat tanpa membuka pengaturan.
- Pertanyaan atau istilah yang membingungkan.
- Tingkat keyakinan partisipan (1–5) setelah setiap tugas.

## Format Temuan

| ID | Partisipan | Tugas | Temuan | Dampak | Rekomendasi | Prioritas |
|---|---|---|---|---|---|---|
| U-001 | | | | | | |

## Kriteria Lulus untuk T006

- Semua partisipan dapat menjelaskan berat tertagih dan total tanpa dibantu.
- Tidak ada kesalahan yang mengubah total/DP tanpa disadari.
- Tenggat dan status pending ditemukan pada percobaan pertama atau setelah satu koreksi.
- Temuan dicatat dan dipetakan ke T033/T075 sebelum task ditutup.
