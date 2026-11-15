<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Actions\ProvisionTenant;
use App\Modules\Identity\Authorization\ProvisioningScope;
use Database\Seeders\IdentityPermissionSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(
        Request $request,
        IdentityPermissionSeeder $permissionSeeder,
        ProvisionTenant $provisionTenant,
        ProvisioningScope $scope,
    ): RedirectResponse {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email:rfc', 'max:254', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'max:1024', 'confirmed'],
            'organization_name' => ['required', 'string', 'max:150'],
            'timezone' => ['required', 'string', 'timezone'],
            'branch_name' => ['required', 'string', 'max:150'],
            'branch_address' => ['required', 'string', 'max:2000'],
            'branch_phone' => ['nullable', 'string', 'max:32'],
            'terms' => ['accepted'],
        ]);

        $permissionSeeder->run();

        $user = DB::transaction(function () use ($validated): User {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);
            $user->forceFill([
                'email_verified_at' => now(),
                'status' => 'active',
                'is_platform_admin' => false,
            ])->save();

            return $user;
        });

        try {
            $scope->run($user, fn (): array => $provisionTenant->handle($user, [
                'organization_name' => $validated['organization_name'],
                'timezone' => $validated['timezone'],
                'branch_name' => $validated['branch_name'],
                'branch_code' => 'MAIN',
                'branch_timezone' => $validated['timezone'],
                'branch_address' => $validated['branch_address'],
                'branch_phone' => $validated['branch_phone'] ?? null,
            ], (string) Str::uuid()));
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'email' => ['Akun dibuat, tetapi organisasi belum dapat disiapkan. Hubungi admin dengan email ini.'],
            ]);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        if ($user->requiresMfa()) {
            return redirect()
                ->route('mfa.setup')
                ->with('status', 'Akun dan organisasi berhasil dibuat. Aktifkan MFA untuk melanjutkan.');
        }

        return redirect()
            ->route('tenants.select')
            ->with('status', 'Akun dan organisasi berhasil dibuat.');
    }
}
