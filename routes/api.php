<?php

use App\Http\Controllers\Api\V1\Auth\DeviceTokenController;
use App\Http\Controllers\Api\V1\Auth\MfaController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetLinkController;
use App\Http\Controllers\Api\V1\Catalog\PriceBookController;
use App\Http\Controllers\Api\V1\Catalog\PriceEntryController;
use App\Http\Controllers\Api\V1\Catalog\PriceResolutionController;
use App\Http\Controllers\Api\V1\Catalog\ServiceController;
use App\Http\Controllers\Api\V1\Catalog\ServicePublicationController;
use App\Http\Controllers\Api\V1\Catalog\WorkflowTemplateController;
use App\Http\Controllers\Api\V1\Customer\CustomerAddressController;
use App\Http\Controllers\Api\V1\Customer\CustomerConsentController;
use App\Http\Controllers\Api\V1\Customer\CustomerController;
use App\Http\Controllers\Api\V1\Customer\CustomerDuplicateController;
use App\Http\Controllers\Api\V1\Customer\CustomerOrderController;
use App\Http\Controllers\Api\V1\Identity\BranchController;
use App\Http\Controllers\Api\V1\Identity\ProvisionTenantController;
use App\Http\Controllers\Api\V1\Identity\UpdateMembershipAssignmentsController;
use App\Http\Controllers\Api\V1\MyContextController;
use App\Http\Controllers\Api\V1\Operations\BranchCalendarController;
use App\Http\Controllers\Api\V1\Operations\DeadlinePreviewController;
use App\Http\Controllers\Api\V1\Order\BagController;
use App\Http\Controllers\Api\V1\Order\DiscountApprovalController;
use App\Http\Controllers\Api\V1\Order\OrderController;
use App\Http\Controllers\Api\V1\Order\OrderRevisionController;
use App\Http\Controllers\Api\V1\Order\QuoteController;
use App\Http\Controllers\Api\V1\Order\ReceiptController;
use App\Http\Controllers\Api\V1\Order\TrackingTokenController;
use App\Http\Controllers\Api\V1\PublicTrackingController;
use App\Http\Controllers\Api\V1\Saas\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/tokens', [DeviceTokenController::class, 'store'])->middleware('throttle:login');
    Route::post('/auth/password-reset-links', [PasswordResetLinkController::class, 'store'])->middleware('throttle:password-reset');
    Route::post('/auth/password-resets', [PasswordResetController::class, 'store'])->middleware('throttle:password-reset');
    Route::get('/tracking/{token}', PublicTrackingController::class)->middleware('throttle:tracking');

    Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
        Route::get('/auth/tokens', [DeviceTokenController::class, 'index']);
        Route::delete('/auth/tokens/{device}', [DeviceTokenController::class, 'destroy']);

        Route::get('/me/context', MyContextController::class);
        Route::get('/me/subscription', SubscriptionController::class)->middleware('tenant');
        Route::post('/provisioning/tenants', ProvisionTenantController::class);

        Route::get('/branches/{branch}', [BranchController::class, 'show'])->middleware('tenant');
        Route::patch('/branches/{branch}', [BranchController::class, 'update'])->middleware('tenant');
        Route::get('/branches/{branch}/calendar', [BranchCalendarController::class, 'show'])->middleware('tenant');
        Route::put('/branches/{branch}/calendar', [BranchCalendarController::class, 'update'])->middleware('tenant');
        Route::post('/deadlines/preview', DeadlinePreviewController::class)->middleware('tenant');

        Route::get('/customers', [CustomerController::class, 'index'])->middleware('tenant');
        Route::post('/customers', [CustomerController::class, 'store'])->middleware('tenant');
        Route::get('/customers/duplicates', CustomerDuplicateController::class)->middleware('tenant');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->middleware('tenant');
        Route::post('/customers/{customer}/addresses', [CustomerAddressController::class, 'store'])->middleware('tenant');
        Route::get('/customers/{customer}/orders', CustomerOrderController::class)->middleware('tenant');
        Route::get('/customers/{customer}/consents', [CustomerConsentController::class, 'index'])->middleware('tenant');
        Route::post('/customers/{customer}/consents', [CustomerConsentController::class, 'store'])->middleware('tenant');

        Route::get('/services', [ServiceController::class, 'index'])->middleware('tenant');
        Route::post('/services', [ServiceController::class, 'store'])->middleware('tenant');
        Route::patch('/services/{service}/publication', ServicePublicationController::class)->middleware('tenant');
        Route::get('/services/{service}', [ServiceController::class, 'show'])->middleware('tenant');
        Route::patch('/services/{service}', [ServiceController::class, 'update'])->middleware('tenant');

        Route::get('/price-books', [PriceBookController::class, 'index'])->middleware('tenant');
        Route::post('/price-books', [PriceBookController::class, 'store'])->middleware('tenant');
        Route::get('/price-books/{priceBook}', [PriceBookController::class, 'show'])->middleware('tenant');
        Route::post('/price-books/{priceBook}/entries', [PriceEntryController::class, 'store'])->middleware('tenant');
        Route::patch('/price-entries/{priceEntry}', [PriceEntryController::class, 'update'])->middleware('tenant');
        Route::get('/prices/resolve', PriceResolutionController::class)->middleware('tenant');

        Route::post('/orders/quotes', [QuoteController::class, 'store'])->middleware('tenant');
        Route::post('/orders/quotes/{quote}/discount-approval', DiscountApprovalController::class)->middleware('tenant');
        Route::post('/orders', [OrderController::class, 'store'])->middleware('tenant');
        Route::post('/orders/{order}/revisions', [OrderRevisionController::class, 'store'])->middleware('tenant');
        Route::post('/orders/{order}/revisions/{revision}/decision', [OrderRevisionController::class, 'decide'])->middleware('tenant');
        Route::post('/orders/{order}/tracking-token', [TrackingTokenController::class, 'store'])->middleware('tenant');
        Route::delete('/orders/{order}/tracking-token', [TrackingTokenController::class, 'destroy'])->middleware('tenant');
        Route::get('/orders/{order}/bags', [BagController::class, 'index'])->middleware('tenant');
        Route::get('/orders/{order}/bags/{bag}/label', [BagController::class, 'label'])->middleware('tenant');
        Route::post('/orders/{order}/repack', [BagController::class, 'repack'])->middleware('tenant');
        Route::post('/orders/{order}/receipt', [ReceiptController::class, 'show'])->middleware('tenant');
        Route::post('/orders/{order}/receipt-prints', [ReceiptController::class, 'print'])->middleware('tenant');

        Route::get('/workflow-templates', [WorkflowTemplateController::class, 'index'])->middleware('tenant');
        Route::post('/workflow-templates', [WorkflowTemplateController::class, 'store'])->middleware('tenant');
        Route::get('/workflow-templates/{workflowTemplate}', [WorkflowTemplateController::class, 'show'])->middleware('tenant');

        Route::post('/memberships/{membership}/assignments', UpdateMembershipAssignmentsController::class)
            ->middleware('tenant');

        Route::post('/me/mfa', [MfaController::class, 'store']);
        Route::post('/me/mfa/confirmation', [MfaController::class, 'confirm']);
        Route::post('/me/mfa/recovery-codes', [MfaController::class, 'regenerate'])->middleware('throttle:mfa');
    });
});
