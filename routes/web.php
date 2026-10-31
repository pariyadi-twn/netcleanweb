<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\MfaSetupController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login')->name('login.store');

    Route::get('/mfa-challenge', [AuthenticatedSessionController::class, 'challenge'])->name('mfa.challenge');
    Route::post('/mfa-challenge', [AuthenticatedSessionController::class, 'verify'])->middleware('throttle:mfa')->name('mfa.challenge.verify');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:password-reset')->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:password-reset')->name('password.update');
});

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/mfa-setup', [MfaSetupController::class, 'show'])->name('mfa.setup');
    Route::post('/mfa-setup', [MfaSetupController::class, 'confirm'])->middleware('throttle:mfa')->name('mfa.setup.confirm');

    Route::middleware(['auth.session', 'mfa.confirmed'])->group(function (): void {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
    });
});
