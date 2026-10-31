<?php

namespace Database\Seeders;

use App\Modules\Identity\Models\Permission;
use Illuminate\Database\Seeder;

class IdentityPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'branches.view' => 'Lihat cabang yang ditugaskan',
            'branches.manage' => 'Kelola cabang',
            'locations.view' => 'Lihat lokasi yang ditugaskan',
            'locations.manage' => 'Kelola lokasi',
            'memberships.view' => 'Lihat staf organisasi',
            'memberships.manage' => 'Kelola keanggotaan dan penugasan',
            'roles.manage' => 'Kelola peran dan izin',
            'customers.view' => 'Lihat dan cari pelanggan tenant',
            'customers.manage' => 'Buat dan ubah pelanggan serta alamat',
            'catalog.view' => 'Lihat layanan dan workflow tenant',
            'catalog.manage' => 'Kelola layanan dan revisi workflow',
            'orders.view' => 'Lihat quote dan order tenant',
            'orders.manage' => 'Buat quote dan terima order',
            'discounts.standard' => 'Berikan diskon layanan sampai batas standar',
            'discounts.override' => 'Menyetujui diskon di atas batas standar',
            'cash.view' => 'Lihat laci dan shift kas',
            'cash.manage' => 'Buka dan tutup shift kas',
            'cash.reopen' => 'Setujui pembukaan kembali shift',
            'payments.view' => 'Lihat pembayaran tenant',
            'payments.manage' => 'Catat pembayaran dan alokasi invoice',
            'credit.manage' => 'Setujui piutang pelanggan',
            'refunds.request' => 'Ajukan refund atas pembayaran',
            'refunds.approve' => 'Setujui dan selesaikan refund',
            'expenses.manage' => 'Catat biaya operasional',
            'expenses.approve' => 'Setujui biaya operasional',
            'cancellations.approve' => 'Setujui pembatalan pesanan siap',
            'reports.view' => 'Lihat laporan outlet dan piutang',
            'reports.export' => 'Buat dan unduh ekspor laporan',
            'imports.manage' => 'Impor pelanggan dan katalog',
            'support.grant' => 'Buat dan cabut grant bantuan sementara',
            'data_requests.manage' => 'Ajukan permintaan penghapusan data pelanggan',
            'production.view' => 'Lihat antrean produksi',
            'production.manage' => 'Rencanakan dan jalankan tahap produksi',
            'production.qc' => 'Catat hasil quality control',
            'audit.view' => 'Lihat jejak audit',
        ];

        foreach ($permissions as $code => $description) {
            Permission::query()->updateOrCreate(['code' => $code], ['description' => $description]);
        }
    }
}
