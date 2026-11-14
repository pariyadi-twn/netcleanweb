<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\AssignCorrelationId;
use App\Http\Middleware\EnsureMfaIsConfirmed;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RecordRequestMetrics;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\UseTenantContext;
use App\Http\Middleware\UseWebTenantContext;
use App\Support\Http\ApiError;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append([AssignCorrelationId::class, SecurityHeaders::class, RecordRequestMetrics::class]);
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'mfa.confirmed' => EnsureMfaIsConfirmed::class,
            'tenant' => UseTenantContext::class,
            'tenant.web' => UseWebTenantContext::class,
            'platform.admin' => EnsurePlatformAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ApiException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiError::response($exception->errorCode, $exception->getMessage(), $exception->status, $exception->fieldErrors);
        });

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiError::response('VALIDATION_FAILED', $exception->getMessage(), 422, $exception->errors());
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiError::response('UNAUTHENTICATED', 'Unauthenticated.', 401);
        });

        $exceptions->render(function (AccessDeniedHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiError::response('FORBIDDEN', 'This action is unauthorized.', 403);
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiError::response('NOT_FOUND', 'The requested resource was not found.', 404);
        });

        $exceptions->render(function (MethodNotAllowedHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiError::response('METHOD_NOT_ALLOWED', 'The method is not allowed for this route.', 405);
        });

        $exceptions->render(function (ThrottleRequestsException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiError::response(
                'RATE_LIMITED',
                'Too many attempts. Please try again later.',
                429,
                headers: $exception->getHeaders(),
            );
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiError::response('HTTP_ERROR', $exception->getMessage() ?: 'The request could not be completed.', $exception->getStatusCode());
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiError::response('SERVER_ERROR', 'An unexpected error occurred.', 500);
        });
    })->create();
