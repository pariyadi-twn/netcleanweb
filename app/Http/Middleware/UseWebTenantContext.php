<?php

namespace App\Http\Middleware;

use App\Modules\Identity\Authorization\MembershipAuthorizer;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Authorization\TenantScope;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class UseWebTenantContext
{
    public function __construct(
        private readonly TenantScope $scope,
        private readonly MembershipAuthorizer $authorizer,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            throw new AuthenticationException('Unauthenticated.');
        }

        $tenantPublicId = (string) $request->session()->get('tenant.public_id', '');

        if ($tenantPublicId === '') {
            $request->session()->keep(['status', 'recovery_codes']);

            return redirect()->route('tenants.select');
        }

        if (! Str::isUuid($tenantPublicId)) {
            $request->session()->forget('tenant.public_id');

            abort(404);
        }

        $resolution = $this->scope->identity($user, function () use ($tenantPublicId, $user): array {
            $tenantId = Tenant::query()->where('public_id', $tenantPublicId)->value('id');

            if ($tenantId === null) {
                return ['tenant_id' => null, 'membership_status' => null];
            }

            return [
                'tenant_id' => (int) $tenantId,
                'membership_status' => TenantMembership::query()
                    ->where('tenant_id', $tenantId)
                    ->where('user_id', $user->id)
                    ->first()?->status,
            ];
        });

        if ($resolution['tenant_id'] === null || $resolution['membership_status'] === null) {
            $request->session()->forget('tenant.public_id');

            abort(404);
        }

        if ($resolution['membership_status'] !== MembershipStatus::Active) {
            $request->session()->forget('tenant.public_id');

            abort(403);
        }

        $tenantId = $resolution['tenant_id'];

        return $this->scope->forTenantId((int) $tenantId, $user->id, function () use ($request, $next, $user, $tenantId): Response {
            $tenant = Tenant::query()->findOrFail($tenantId);
            $context = $this->authorizer->contextFor($user, $tenant);

            if ($context === null) {
                $request->session()->forget('tenant.public_id');

                abort(403);
            }

            app()->instance(TenantContext::class, $context);

            try {
                return $next($request);
            } finally {
                app()->forgetInstance(TenantContext::class);
            }
        });
    }
}
