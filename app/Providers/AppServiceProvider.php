<?php

namespace App\Providers;

use App\Modules\Cash\Models\CashRegister;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Cash\Models\CashTransfer;
use App\Modules\Cash\Models\Expense;
use App\Modules\Catalog\Models\PriceBook;
use App\Modules\Catalog\Models\PriceEntry;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\WorkflowTemplate;
use App\Modules\Customer\Models\Customer;
use App\Modules\Identity\Enums\UserStatus;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Location;
use App\Modules\Identity\Models\PersonalAccessToken;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Lifecycle\Models\DataRequest;
use App\Modules\Order\Models\Order;
use App\Modules\Payment\Models\CreditApproval;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\PaymentAllocation;
use App\Modules\Payment\Models\Refund;
use App\Modules\Reporting\Models\ImportBatch;
use App\Modules\Reporting\Models\ReportExport;
use App\Modules\Support\Models\SupportAccessGrant;
use App\Policies\BranchPolicy;
use App\Policies\CashRegisterPolicy;
use App\Policies\CashSessionPolicy;
use App\Policies\CashTransferPolicy;
use App\Policies\CreditApprovalPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\DataRequestPolicy;
use App\Policies\ExpensePolicy;
use App\Policies\ImportBatchPolicy;
use App\Policies\LocationPolicy;
use App\Policies\MembershipPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PaymentAllocationPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PriceBookPolicy;
use App\Policies\PriceEntryPolicy;
use App\Policies\RefundPolicy;
use App\Policies\ReportExportPolicy;
use App\Policies\RolePolicy;
use App\Policies\ServicePolicy;
use App\Policies\SupportAccessGrantPolicy;
use App\Policies\WorkflowTemplatePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // paksa HTTPS jika aplikasi berjalan di environment production
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Gate::policy(Branch::class, BranchPolicy::class);
        Gate::policy(CashRegister::class, CashRegisterPolicy::class);
        Gate::policy(CashSession::class, CashSessionPolicy::class);
        Gate::policy(Expense::class, ExpensePolicy::class);
        Gate::policy(CashTransfer::class, CashTransferPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(CreditApproval::class, CreditApprovalPolicy::class);
        Gate::policy(Refund::class, RefundPolicy::class);
        Gate::policy(PaymentAllocation::class, PaymentAllocationPolicy::class);
        Gate::policy(ImportBatch::class, ImportBatchPolicy::class);
        Gate::policy(ReportExport::class, ReportExportPolicy::class);
        Gate::policy(DataRequest::class, DataRequestPolicy::class);
        Gate::policy(SupportAccessGrant::class, SupportAccessGrantPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Location::class, LocationPolicy::class);
        Gate::policy(TenantMembership::class, MembershipPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(PriceBook::class, PriceBookPolicy::class);
        Gate::policy(PriceEntry::class, PriceEntryPolicy::class);
        Gate::policy(Service::class, ServicePolicy::class);
        Gate::policy(WorkflowTemplate::class, WorkflowTemplatePolicy::class);

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        Sanctum::authenticateAccessTokensUsing(function (PersonalAccessToken $accessToken, bool $isValid): bool {
            if (! $isValid) {
                return false;
            }

            $user = $accessToken->tokenable;

            return $user !== null
                && $user->status === UserStatus::Active
                && $accessToken->device !== null
                && ! $accessToken->device->isRevoked();
        });

        RateLimiter::for('login', function (Request $request): array {
            $email = Str::lower((string) $request->input('email', ''));

            return [
                Limit::perMinute(5)->by('login:email:'.$email),
                Limit::perMinute(20)->by('login:ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('password-reset', function (Request $request): array {
            $email = Str::lower((string) $request->input('email', ''));

            return [
                Limit::perMinute(3)->by('password-reset:email:'.$email),
                Limit::perMinute(10)->by('password-reset:ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('mfa', fn (Request $request) => Limit::perMinute(5)->by('mfa:'.($request->user()?->id ?? $request->ip())));

        RateLimiter::for('tracking', function (Request $request): array {
            $token = (string) $request->route('token');

            return [
                Limit::perMinute(30)->by('tracking:ip:'.$request->ip()),
                Limit::perMinute(10)->by('tracking:token:'.hash('sha256', $token)),
            ];
        });
    }
}
