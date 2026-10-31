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
            'audit.view' => 'Lihat jejak audit',
        ];

        foreach ($permissions as $code => $description) {
            Permission::query()->updateOrCreate(['code' => $code], ['description' => $description]);
        }
    }
}
