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
            'audit.view' => 'Lihat jejak audit',
        ];

        foreach ($permissions as $code => $description) {
            Permission::query()->updateOrCreate(['code' => $code], ['description' => $description]);
        }
    }
}
