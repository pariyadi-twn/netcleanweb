<?php

namespace App\Http\Middleware;

use App\Modules\Identity\Authorization\MembershipAuthorizer as Authorizer;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Authorization\TenantScope;
use App\Modules\Identity\Models\Tenant;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class UseTenantContext
{
    public function __construct(
        private readonly TenantScope $scope,
        private readonly Authorizer $authorizer,
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

        $tenantPublicId = (string) $request->header('X-Tenant-ID', '');

        if (! Str::isUuid($tenantPublicId)) {
            throw ValidationException::withMessages([
                'tenant' => ['A valid X-Tenant-ID header is required.'],
            ]);
        }

        $tenantId = $this->scope->identity(
            $user,
            fn () => Tenant::query()->where('public_id', $tenantPublicId)->value('id'),
        );

        if ($tenantId === null) {
            abort(404);
        }

        return $this->scope->forTenantId((int) $tenantId, $user->id, function () use ($request, $next, $user, $tenantId): Response {
            $tenant = Tenant::query()->findOrFail($tenantId);
            $context = $this->authorizer->contextFor($user, $tenant);

            if ($context === null) {
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
