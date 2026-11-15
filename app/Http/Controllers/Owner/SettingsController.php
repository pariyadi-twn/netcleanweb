<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __invoke(Request $request, TenantContext $context): View
    {
        $sections = array_values(array_filter([
            [
                'label' => 'Organisasi',
                'description' => 'Identitas usaha dan zona waktu.',
                'route' => 'organization.index',
                'permission' => PermissionCode::MembershipsManage,
            ],
            [
                'label' => 'Cabang & lokasi',
                'description' => 'Profil cabang, alamat, dan lokasi kerja.',
                'route' => 'branches.index',
                'permission' => PermissionCode::BranchesView,
            ],
            [
                'label' => 'Staf',
                'description' => 'Penugasan staf ke cabang dan lokasi.',
                'route' => 'staff.index',
                'permission' => PermissionCode::MembershipsView,
            ],
            [
                'label' => 'Peran & izin',
                'description' => 'Atur akses setiap peran staf.',
                'route' => 'roles.index',
                'permission' => PermissionCode::RolesManage,
            ],
            [
                'label' => 'Katalog & harga',
                'description' => 'Layanan, workflow, dan price book.',
                'route' => 'catalog.index',
                'permission' => PermissionCode::CatalogView,
            ],
        ], fn (array $section): bool => $context->can($section['permission'])));

        abort_if($sections === [], 403);

        return view('owner.settings.index', [
            'user' => $request->user(),
            'sections' => $sections,
            'mfaMode' => config('identity.mfa.mode', 'disabled'),
        ]);
    }
}
