<?php

use App\Http\Controllers\Api\V1\Auth\DeviceTokenController;
use App\Http\Controllers\Api\V1\Auth\MfaController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetLinkController;
use App\Http\Controllers\Api\V1\Cash\CashRegisterController;
use App\Http\Controllers\Api\V1\Cash\CashSessionController;
use App\Http\Controllers\Api\V1\Cash\CashTransferController;
use App\Http\Controllers\Api\V1\Cash\ExpenseController;
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
use App\Http\Controllers\Api\V1\Customer\DataRequestController;
use App\Http\Controllers\Api\V1\Identity\BranchController;
use App\Http\Controllers\Api\V1\Identity\ProvisionTenantController;
use App\Http\Controllers\Api\V1\Identity\UpdateMembershipAssignmentsController;
use App\Http\Controllers\Api\V1\MyContextController;
use App\Http\Controllers\Api\V1\Operations\BranchCalendarController;
use App\Http\Controllers\Api\V1\Operations\DeadlinePreviewController;
use App\Http\Controllers\Api\V1\Ops\HealthController;
use App\Http\Controllers\Api\V1\Ops\MetricsController;
use App\Http\Controllers\Api\V1\Order\BagController;
use App\Http\Controllers\Api\V1\Order\CancelOrderController;
use App\Http\Controllers\Api\V1\Order\DiscountApprovalController;
use App\Http\Controllers\Api\V1\Order\OrderConditionController;
use App\Http\Controllers\Api\V1\Order\OrderController;
use App\Http\Controllers\Api\V1\Order\OrderRevisionController;
use App\Http\Controllers\Api\V1\Order\QuoteController;
use App\Http\Controllers\Api\V1\Order\ReceiptController;
use App\Http\Controllers\Api\V1\Order\TrackingTokenController;
use App\Http\Controllers\Api\V1\Payment\AllocationReversalController;
use App\Http\Controllers\Api\V1\Payment\CreditApprovalController;
use App\Http\Controllers\Api\V1\Payment\InvoicePaymentController;
use App\Http\Controllers\Api\V1\Payment\RefundController;
use App\Http\Controllers\Api\V1\Platform\PlatformDataRequestController;
use App\Http\Controllers\Api\V1\Platform\PlatformPlanController;
use App\Http\Controllers\Api\V1\Platform\PlatformTenantController;
use App\Http\Controllers\Api\V1\Platform\SubscriptionBillingController;
use App\Http\Controllers\Api\V1\PublicTrackingController;
use App\Http\Controllers\Api\V1\Reporting\ImportController;
use App\Http\Controllers\Api\V1\Reporting\OutletReportController;
use App\Http\Controllers\Api\V1\Reporting\ReceivablesReportController;
use App\Http\Controllers\Api\V1\Reporting\ReportExportController;
use App\Http\Controllers\Api\V1\Saas\SubscriptionController;
use App\Http\Controllers\Api\V1\Support\SupportGrantController;
use App\Http\Controllers\Api\V1\Uploads\AttachmentController;
use App\Http\Controllers\Api\V1\Uploads\OrderAttachmentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/tokens', [DeviceTokenController::class, 'store'])->middleware('throttle:login');
    Route::post('/auth/password-reset-links', [PasswordResetLinkController::class, 'store'])->middleware('throttle:password-reset');
    Route::post('/auth/password-resets', [PasswordResetController::class, 'store'])->middleware('throttle:password-reset');
    Route::get('/tracking/{token}', PublicTrackingController::class)->middleware('throttle:tracking');
    Route::get('/health', HealthController::class)->middleware('throttle:60,1');
    Route::get('/ops/metrics', MetricsController::class);

    Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
        Route::get('/auth/tokens', [DeviceTokenController::class, 'index']);
        Route::delete('/auth/tokens/{device}', [DeviceTokenController::class, 'destroy']);

        Route::get('/me/context', MyContextController::class);
        Route::get('/support/received-grants', [SupportGrantController::class, 'received']);
        Route::post('/platform/tenants/{tenant}/subscription-invoices', [SubscriptionBillingController::class, 'store']);
        Route::post('/platform/subscription-invoices/{invoice}/payments', [SubscriptionBillingController::class, 'verify']);
        Route::get('/platform/tenants', [PlatformTenantController::class, 'index']);
        Route::get('/platform/tenants/{tenant}', [PlatformTenantController::class, 'show']);
        Route::get('/platform/plans', [PlatformPlanController::class, 'index']);
        Route::post('/platform/plans', [PlatformPlanController::class, 'store']);
        Route::post('/platform/tenants/{tenant}/subscription', [PlatformPlanController::class, 'assign']);
        Route::get('/platform/data-requests', [PlatformDataRequestController::class, 'index']);
        Route::post('/platform/data-requests/{dataRequest}/verify', [PlatformDataRequestController::class, 'verify']);
        Route::post('/platform/data-requests/{dataRequest}/reject', [PlatformDataRequestController::class, 'reject']);
        Route::post('/platform/data-requests/{dataRequest}/process', [PlatformDataRequestController::class, 'process']);
        Route::get('/me/subscription', SubscriptionController::class)->middleware('tenant');
        Route::post('/provisioning/tenants', ProvisionTenantController::class);

        Route::get('/branches/{branch}', [BranchController::class, 'show'])->middleware('tenant');
        Route::patch('/branches/{branch}', [BranchController::class, 'update'])->middleware('tenant');
        Route::get('/branches/{branch}/calendar', [BranchCalendarController::class, 'show'])->middleware('tenant');
        Route::put('/branches/{branch}/calendar', [BranchCalendarController::class, 'update'])->middleware('tenant');
        Route::get('/branches/{branch}/cash-registers', [CashRegisterController::class, 'index'])->middleware('tenant');
        Route::post('/branches/{branch}/cash-registers', [CashRegisterController::class, 'store'])->middleware('tenant');
        Route::post('/cash-registers/{register}/sessions', [CashSessionController::class, 'store'])->middleware('tenant');
        Route::get('/cash-sessions/{session}', [CashSessionController::class, 'show'])->middleware('tenant');
        Route::post('/cash-sessions/{session}/close', [CashSessionController::class, 'close'])->middleware('tenant');
        Route::post('/cash-sessions/{session}/reopen', [CashSessionController::class, 'reopen'])->middleware('tenant');
        Route::post('/expenses', [ExpenseController::class, 'store'])->middleware('tenant');
        Route::post('/expenses/{expense}/decision', [ExpenseController::class, 'decide'])->middleware('tenant');
        Route::post('/cash-transfers', [CashTransferController::class, 'store'])->middleware('tenant');
        Route::get('/cash-sessions/{session}/events', [CashSessionController::class, 'events'])->middleware('tenant');
        Route::post('/deadlines/preview', DeadlinePreviewController::class)->middleware('tenant');
        Route::get('/imports/templates/{type}', [ImportController::class, 'template'])->middleware('tenant');
        Route::post('/imports/{type}', [ImportController::class, 'store'])->middleware('tenant');
        Route::get('/imports/{import}', [ImportController::class, 'show'])->middleware('tenant');
        Route::get('/imports/{import}/rows', [ImportController::class, 'rows'])->middleware('tenant');
        Route::post('/reports/exports', [ReportExportController::class, 'store'])->middleware('tenant');
        Route::get('/reports/exports/{export}', [ReportExportController::class, 'show'])->middleware('tenant');
        Route::get('/reports/exports/{export}/download', [ReportExportController::class, 'download'])->middleware('tenant');
        Route::get('/reports/outlet', OutletReportController::class)->middleware('tenant');
        Route::get('/data-requests', [DataRequestController::class, 'index'])->middleware('tenant');
        Route::post('/data-requests', [DataRequestController::class, 'store'])->middleware('tenant');
        Route::get('/support/grants', [SupportGrantController::class, 'index'])->middleware('tenant');
        Route::post('/support/grants', [SupportGrantController::class, 'store'])->middleware('tenant');
        Route::post('/support/grants/{grant}/revoke', [SupportGrantController::class, 'revoke'])->middleware('tenant');
        Route::get('/reports/receivables', ReceivablesReportController::class)->middleware('tenant');

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
        Route::post('/orders/{order}/cancel', [CancelOrderController::class, 'store'])->middleware('tenant');
        Route::post('/orders/{order}/revisions', [OrderRevisionController::class, 'store'])->middleware('tenant');
        Route::post('/orders/{order}/revisions/{revision}/decision', [OrderRevisionController::class, 'decide'])->middleware('tenant');
        Route::post('/orders/{order}/tracking-token', [TrackingTokenController::class, 'store'])->middleware('tenant');
        Route::delete('/orders/{order}/tracking-token', [TrackingTokenController::class, 'destroy'])->middleware('tenant');
        Route::get('/orders/{order}/bags', [BagController::class, 'index'])->middleware('tenant');
        Route::get('/orders/{order}/bags/{bag}/label', [BagController::class, 'label'])->middleware('tenant');
        Route::post('/orders/{order}/repack', [BagController::class, 'repack'])->middleware('tenant');
        Route::post('/invoices/{invoice}/payments', [InvoicePaymentController::class, 'store'])->middleware('tenant');
        Route::post('/invoices/{invoice}/credit-approval', [CreditApprovalController::class, 'store'])->middleware('tenant');
        Route::post('/payments/{payment}/refunds', [RefundController::class, 'store'])->middleware('tenant');
        Route::post('/refunds/{refund}/decision', [RefundController::class, 'decide'])->middleware('tenant');
        Route::post('/refunds/{refund}/status', [RefundController::class, 'transition'])->middleware('tenant');
        Route::post('/payment-allocations/{allocation}/reversals', [AllocationReversalController::class, 'store'])->middleware('tenant');
        Route::post('/orders/{order}/receipt', [ReceiptController::class, 'show'])->middleware('tenant');
        Route::post('/orders/{order}/receipt-prints', [ReceiptController::class, 'print'])->middleware('tenant');
        Route::get('/orders/{order}/condition', [OrderConditionController::class, 'show'])->middleware('tenant');
        Route::post('/orders/{order}/condition', [OrderConditionController::class, 'store'])->middleware('tenant');
        Route::get('/orders/{order}/attachments', [OrderAttachmentController::class, 'index'])->middleware('tenant');
        Route::post('/orders/{order}/attachments', [OrderAttachmentController::class, 'store'])->middleware('tenant');

        Route::post('/attachments/uploads', [AttachmentController::class, 'store'])->middleware('tenant');
        Route::put('/attachments/{attachment}/content', [AttachmentController::class, 'transfer'])->middleware('tenant');
        Route::post('/attachments/{attachment}/finalize', [AttachmentController::class, 'finalize'])->middleware('tenant');
        Route::get('/attachments/{attachment}/url', [AttachmentController::class, 'url'])->middleware('tenant');

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
