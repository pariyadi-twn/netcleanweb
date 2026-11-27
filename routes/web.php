<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\MfaSetupController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Owner\BranchController;
use App\Http\Controllers\Owner\CatalogController;
use App\Http\Controllers\Owner\CustomerController;
use App\Http\Controllers\Owner\FinanceController;
use App\Http\Controllers\Owner\OrderController;
use App\Http\Controllers\Owner\OrganizationController;
use App\Http\Controllers\Owner\ProductionController;
use App\Http\Controllers\Owner\ReportController;
use App\Http\Controllers\Owner\RoleController;
use App\Http\Controllers\Owner\SettingsController;
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

    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:register')->name('register.store');

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

        Route::middleware(['tenant.web', 'web.console'])->group(function (): void {
            Route::get('/dashboard', DashboardController::class)->name('dashboard');
            Route::get('/settings', SettingsController::class)->name('settings.index');

            Route::get('/organization', [OrganizationController::class, 'index'])->name('organization.index');
            Route::put('/organization', [OrganizationController::class, 'update'])->name('organization.update');

            Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
            Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
            Route::get('/roles/{role}', [RoleController::class, 'edit'])->name('roles.edit');
            Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');

            Route::get('/branches', [BranchController::class, 'index'])->name('branches.index');
            Route::get('/branches/{branch}', [BranchController::class, 'edit'])->name('branches.edit');
            Route::put('/branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
            Route::post('/branches/{branch}/locations', [BranchController::class, 'storeLocation'])->name('branches.locations.store');

            Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
            Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
            Route::get('/staff/{membership}', [StaffController::class, 'edit'])->name('staff.edit');
            Route::put('/staff/{membership}', [StaffController::class, 'update'])->name('staff.update');

            Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
            Route::post('/catalog/services', [CatalogController::class, 'storeService'])->name('catalog.services.store');
            Route::post('/catalog/services/{service}/publication', [CatalogController::class, 'toggleService'])->name('catalog.services.publication');
            Route::post('/catalog/price-books', [CatalogController::class, 'storePriceBook'])->name('catalog.price-books.store');
            Route::post('/catalog/price-books/{priceBook}/entries', [CatalogController::class, 'storePriceEntry'])->name('catalog.price-entries.store');

            Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
            Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');

            Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
            Route::get('/orders/create', [OrderController::class, 'create'])->name('orders.create');
            Route::post('/orders/quote', [OrderController::class, 'storeQuote'])->name('orders.quote');
            Route::post('/orders/accept', [OrderController::class, 'acceptQuote'])->name('orders.accept');
            Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
            Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
            Route::post('/orders/{order}/handover', [OrderController::class, 'handover'])->name('orders.handover');
            Route::post('/orders/{order}/credit', [OrderController::class, 'credit'])->name('orders.credit');
            Route::post('/orders/{order}/revisions/{revision}/decision', [OrderController::class, 'decideRevision'])->name('orders.revisions.decision');
            Route::post('/orders/{order}/complaints', [OrderController::class, 'storeComplaint'])->name('orders.complaints.store');
            Route::post('/orders/{order}/complaints/{complaint}/rewash', [OrderController::class, 'rewash'])->name('orders.complaints.rewash');
            Route::post('/orders/{order}/complaints/{complaint}/resolution', [OrderController::class, 'resolveComplaint'])->name('orders.complaints.resolve');
            Route::get('/orders/{order}/receipt', [OrderController::class, 'receipt'])->name('orders.receipt');
            Route::post('/orders/{order}/payments', [OrderController::class, 'recordPayment'])->name('orders.payments.store');
            Route::post('/orders/{order}/production-tasks', [ProductionController::class, 'plan'])->name('orders.production.plan');
            Route::post('/orders/{order}/production-complete', [ProductionController::class, 'complete'])->name('orders.production.complete');

            Route::get('/production', [ProductionController::class, 'index'])->name('production.index');
            Route::get('/production/tasks/{task}', [ProductionController::class, 'show'])->name('production.tasks.show');
            Route::post('/production/tasks/{task}/transition', [ProductionController::class, 'transition'])->name('production.tasks.transition');
            Route::post('/production/tasks/{task}/qc', [ProductionController::class, 'qc'])->name('production.tasks.qc');

            Route::get('/finance', [FinanceController::class, 'index'])->name('finance.index');
            Route::get('/finance/cash', [FinanceController::class, 'cash'])->name('finance.cash.index');
            Route::post('/finance/cash/registers', [FinanceController::class, 'storeRegister'])->name('finance.cash.registers.store');
            Route::post('/finance/cash/{register}/open', [FinanceController::class, 'openCash'])->name('finance.cash.open');
            Route::post('/finance/cash/{session}/close', [FinanceController::class, 'closeCash'])->name('finance.cash.close');
            Route::post('/finance/cash/{session}/reopen', [FinanceController::class, 'reopenCash'])->name('finance.cash.reopen');
            Route::post('/finance/cash/transfer', [FinanceController::class, 'transfer'])->name('finance.cash.transfer');
            Route::get('/finance/payments', [FinanceController::class, 'payments'])->name('finance.payments.index');
            Route::post('/finance/payments/{payment}/refunds', [FinanceController::class, 'requestRefund'])->name('finance.payments.refund');
            Route::get('/finance/expenses', [FinanceController::class, 'expenses'])->name('finance.expenses.index');
            Route::post('/finance/expenses', [FinanceController::class, 'storeExpense'])->name('finance.expenses.store');
            Route::post('/finance/expenses/{expense}/decision', [FinanceController::class, 'decideExpense'])->name('finance.expenses.decision');
            Route::get('/finance/refunds', [FinanceController::class, 'refunds'])->name('finance.refunds.index');
            Route::post('/finance/refunds/{refund}/decision', [FinanceController::class, 'decideRefund'])->name('finance.refunds.decision');
            Route::post('/finance/refunds/{refund}/status', [FinanceController::class, 'transitionRefund'])->name('finance.refunds.status');

            Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('/reports/imports', [ReportController::class, 'imports'])->name('reports.imports');
            Route::post('/reports/imports', [ReportController::class, 'storeImport'])->name('reports.imports.store');
            Route::get('/reports/outlet', [ReportController::class, 'outlet'])->name('reports.outlet');
            Route::get('/reports/receivables', [ReportController::class, 'receivables'])->name('reports.receivables');
            Route::post('/reports/exports', [ReportController::class, 'storeExport'])->name('reports.exports.store');
            Route::get('/reports/exports/{export}/download', [ReportController::class, 'downloadExport'])->name('reports.exports.download');
            Route::get('/reports/exports/{export}', [ReportController::class, 'showExport'])->name('reports.exports.show');
        });

        Route::middleware('platform.admin')->prefix('platform')->group(function (): void {
            Route::get('/', PlatformDashboardController::class)->name('platform.dashboard');
            Route::post('/data-requests/{dataRequest}/verify', [PlatformWebDataRequestController::class, 'verify'])->name('platform.data-requests.verify');
            Route::post('/data-requests/{dataRequest}/reject', [PlatformWebDataRequestController::class, 'reject'])->name('platform.data-requests.reject');
            Route::post('/data-requests/{dataRequest}/process', [PlatformWebDataRequestController::class, 'process'])->name('platform.data-requests.process');
        });
    });
});
