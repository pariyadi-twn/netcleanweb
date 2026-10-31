<?php

namespace App\Providers;

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
use App\Modules\Order\Models\Order;
use App\Policies\BranchPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\LocationPolicy;
use App\Policies\MembershipPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PriceBookPolicy;
use App\Policies\PriceEntryPolicy;
use App\Policies\RolePolicy;
use App\Policies\ServicePolicy;
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
    }
}
