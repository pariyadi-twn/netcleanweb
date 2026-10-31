<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Authorization\MembershipAuthorizer as Authorizer;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Authorization\TenantScope;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Identity\Enums\TenantStatus;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Support\Models\SupportAccessGrant;
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

        $supportGrantId = trim((string) $request->header('X-Support-Grant-ID', ''));
        if ($supportGrantId !== '') {
            return $this->support($request, $next, $user, $supportGrantId);
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

    private function support(Request $request, Closure $next, User $user, string $publicId): Response
    {
        if (! $user->is_platform_admin) {
            abort(403);
        }
        if (! in_array($request->method(), ['GET', 'HEAD'], true) || ! Str::isUuid($publicId)) {
            abort(403);
        }

        $grant = $this->scope->identity(
            $user,
            fn () => SupportAccessGrant::query()
                ->where('public_id', Str::lower($publicId))
                ->where('platform_user_id', $user->id)
                ->first(),
        );

        if ($grant === null || ! $grant->isActive()) {
            abort(403);
        }

        return $this->scope->forTenantId($grant->tenant_id, $user->id, function () use ($request, $next, $user, $grant): Response {
            $tenant = Tenant::query()->findOrFail($grant->tenant_id);
            if ($tenant->status !== TenantStatus::Active) {
                abort(403);
            }

            $branchIds = Branch::query()
                ->where('tenant_id', $grant->tenant_id)
                ->orderBy('id')
                ->pluck('id')
                ->map(fn (int|string $id): int => (int) $id)
                ->values()
                ->all();

            $context = new TenantContext(
                userId: $user->id,
                tenantId: $tenant->id,
                tenantPublicId: $tenant->public_id,
                membershipId: 0,
                membershipPublicId: '',
                permissionVersion: 0,
                branchIds: $branchIds,
                locationIds: [],
                permissions: array_values(array_intersect($grant->allowed_actions, SupportAccessGrant::READ_ACTIONS)),
            );
            app()->instance(TenantContext::class, $context);

            try {
                $grant->forceFill([
                    'last_used_at' => now(),
                    'use_count' => $grant->use_count + 1,
                    'version' => $grant->version + 1,
                ])->save();

                app(AuditRecorder::class)->record(
                    tenantId: $grant->tenant_id,
                    action: AuditAction::SupportAccessUsed,
                    subjectType: AuditRecorder::SUBJECT_SUPPORT_GRANT,
                    subjectPublicId: $grant->public_id,
                    changes: [
                        'case_reference' => $grant->case_reference,
                        'method' => $request->method(),
                        'path' => $request->path(),
                        'allowed_actions' => $context->permissions,
                    ],
                    reason: $grant->case_reference,
                    actor: $user,
                );

                return $next($request);
            } finally {
                app()->forgetInstance(TenantContext::class);
            }
        });
    }
}
