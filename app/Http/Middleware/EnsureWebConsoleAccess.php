<?php

namespace App\Http\Middleware;

use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWebConsoleAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(TenantContext::class);

        abort_unless($context->can(PermissionCode::ReportsView), 403);

        return $next($request);
    }
}
