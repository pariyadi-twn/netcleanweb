<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\MfaSetupController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Owner\BranchController;
use App\Http\Controllers\Owner\CatalogController;
use App\Http\Controllers\Owner\CustomerController;
use App\Http\Controllers\Owner\StaffController;
use App\Http\Controllers\Platform\PlatformDashboardController;
use App\Http\Controllers\Platform\PlatformWebDataRequestController;
use App\Http\Controllers\Prototype\CashierPrototypeController;
use App\Http\Controllers\TenantSelectionController;
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

    if (! app()->isProduction()) {
        Route::get('/prototype/kasir', CashierPrototypeController::class)->name('prototype.kasir');
    }

    Route::middleware(['auth.session', 'mfa.confirmed'])->group(function (): void {
        Route::get('/tenants/select', [TenantSelectionController::class, 'index'])->name('tenants.select');
        Route::post('/tenants/select', [TenantSelectionController::class, 'store'])->name('tenants.store');

        Route::middleware('tenant.web')->group(function (): void {
            Route::get('/dashboard', DashboardController::class)->name('dashboard');

            Route::get('/branches', [BranchController::class, 'index'])->name('branches.index');
            Route::get('/branches/{branch}', [BranchController::class, 'edit'])->name('branches.edit');
            Route::put('/branches/{branch}', [BranchController::class, 'update'])->name('branches.update');

            Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
            Route::get('/staff/{membership}', [StaffController::class, 'edit'])->name('staff.edit');
            Route::put('/staff/{membership}', [StaffController::class, 'update'])->name('staff.update');

            Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
            Route::post('/catalog/services', [CatalogController::class, 'storeService'])->name('catalog.services.store');
            Route::post('/catalog/services/{service}/publication', [CatalogController::class, 'toggleService'])->name('catalog.services.publication');
            Route::post('/catalog/price-books', [CatalogController::class, 'storePriceBook'])->name('catalog.price-books.store');
            Route::post('/catalog/price-books/{priceBook}/entries', [CatalogController::class, 'storePriceEntry'])->name('catalog.price-entries.store');

            Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
            Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
        });

        Route::middleware('platform.admin')->prefix('platform')->group(function (): void {
            Route::get('/', PlatformDashboardController::class)->name('platform.dashboard');
            Route::post('/data-requests/{dataRequest}/verify', [PlatformWebDataRequestController::class, 'verify'])->name('platform.data-requests.verify');
            Route::post('/data-requests/{dataRequest}/reject', [PlatformWebDataRequestController::class, 'reject'])->name('platform.data-requests.reject');
            Route::post('/data-requests/{dataRequest}/process', [PlatformWebDataRequestController::class, 'process'])->name('platform.data-requests.process');
        });
    });
});
