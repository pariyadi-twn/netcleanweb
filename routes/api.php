<?php

use App\Http\Controllers\Api\V1\Auth\DeviceTokenController;
use App\Http\Controllers\Api\V1\Auth\MfaController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetLinkController;
use App\Http\Controllers\Api\V1\Identity\BranchController;
use App\Http\Controllers\Api\V1\Identity\UpdateMembershipAssignmentsController;
use App\Http\Controllers\Api\V1\MyContextController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/tokens', [DeviceTokenController::class, 'store'])->middleware('throttle:login');
    Route::post('/auth/password-reset-links', [PasswordResetLinkController::class, 'store'])->middleware('throttle:password-reset');
    Route::post('/auth/password-resets', [PasswordResetController::class, 'store'])->middleware('throttle:password-reset');

    Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
        Route::get('/auth/tokens', [DeviceTokenController::class, 'index']);
        Route::delete('/auth/tokens/{device}', [DeviceTokenController::class, 'destroy']);

        Route::get('/me/context', MyContextController::class);

        Route::get('/branches/{branch}', [BranchController::class, 'show'])->middleware('tenant');
        Route::patch('/branches/{branch}', [BranchController::class, 'update'])->middleware('tenant');

        Route::post('/memberships/{membership}/assignments', UpdateMembershipAssignmentsController::class)
            ->middleware('tenant');

        Route::post('/me/mfa', [MfaController::class, 'store']);
        Route::post('/me/mfa/confirmation', [MfaController::class, 'confirm']);
        Route::post('/me/mfa/recovery-codes', [MfaController::class, 'regenerate'])->middleware('throttle:mfa');
    });
});
