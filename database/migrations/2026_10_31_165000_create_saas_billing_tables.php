<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_subscription_invoices', function (Blueprint $table): void {
            $this->metadata($table);
            $table->bigInteger('subscription_id');
            $table->string('platform_invoice_number', 64);
            $table->string('kind', 24)->default('subscription');
            $table->bigInteger('amount_rupiah');
            $table->string('status', 24)->default('draft');
            $table->timestampTz('period_start');
            $table->timestampTz('period_end');
            $table->timestampTz('issued_at')->nullable();
            $table->timestampTz('due_at')->nullable();
            $table->unique(['tenant_id', 'platform_invoice_number']);
            $table->unique(['tenant_id', 'subscription_id', 'period_start', 'period_end', 'kind'], 'saas_subscription_invoices_period_unique');
            $table->foreign(['tenant_id', 'subscription_id'])->references(['tenant_id', 'id'])->on('saas_subscriptions')->restrictOnDelete();
        });

        Schema::create('saas_subscription_payments', function (Blueprint $table): void {
            $this->metadata($table);
            $table->bigInteger('subscription_invoice_id');
            $table->bigInteger('amount_rupiah');
            $table->string('reference', 150);
            $table->timestampTz('verified_at');
            $table->bigInteger('verified_by_user_id')->nullable();
            $table->unique(['tenant_id', 'reference']);
            $table->foreign(['tenant_id', 'subscription_invoice_id'])->references(['tenant_id', 'id'])->on('saas_subscription_invoices')->restrictOnDelete();
            $table->foreign('verified_by_user_id')->references('id')->on('users')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE saas_subscription_invoices ADD CONSTRAINT saas_subscription_invoices_amount_check CHECK (amount_rupiah >= 0), ADD CONSTRAINT saas_subscription_invoices_status_check CHECK (status IN ('draft', 'issued', 'paid', 'void')), ADD CONSTRAINT saas_subscription_invoices_period_check CHECK (period_end > period_start)");
        DB::statement('ALTER TABLE saas_subscription_payments ADD CONSTRAINT saas_subscription_payments_amount_check CHECK (amount_rupiah > 0)');

        DB::statement('ALTER TABLE saas_subscription_invoices ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE saas_subscription_payments ENABLE ROW LEVEL SECURITY');
        foreach (['saas_subscription_invoices', 'saas_subscription_payments'] as $table) {
            DB::statement("CREATE POLICY {$table}_tenant_scope ON {$table} FOR ALL USING (tenant_id = app_current_tenant_id()) WITH CHECK (tenant_id = app_current_tenant_id())");
            DB::statement("CREATE POLICY {$table}_platform_scope ON {$table} FOR ALL USING (app_identity_bootstrap() AND EXISTS (SELECT 1 FROM users u WHERE u.id = app_current_user_id() AND u.is_platform_admin)) WITH CHECK (app_identity_bootstrap() AND EXISTS (SELECT 1 FROM users u WHERE u.id = app_current_user_id() AND u.is_platform_admin))");
        }
        DB::statement('CREATE POLICY saas_subscriptions_platform_scope ON saas_subscriptions FOR ALL USING (app_identity_bootstrap() AND EXISTS (SELECT 1 FROM users u WHERE u.id = app_current_user_id() AND u.is_platform_admin)) WITH CHECK (app_identity_bootstrap() AND EXISTS (SELECT 1 FROM users u WHERE u.id = app_current_user_id() AND u.is_platform_admin))');
        DB::statement('CREATE POLICY tenants_platform_scope ON tenants FOR SELECT USING (app_identity_bootstrap() AND EXISTS (SELECT 1 FROM users u WHERE u.id = app_current_user_id() AND u.is_platform_admin))');

        DB::statement('REVOKE DELETE ON saas_subscription_invoices, saas_subscription_payments FROM netclean_runtime');
        DB::statement('GRANT SELECT, INSERT, UPDATE ON saas_subscription_invoices, saas_subscription_payments TO netclean_runtime');
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenants_platform_scope ON tenants');
        DB::statement('DROP POLICY IF EXISTS saas_subscriptions_platform_scope ON saas_subscriptions');
        Schema::dropIfExists('saas_subscription_payments');
        Schema::dropIfExists('saas_subscription_invoices');
    }

    private function metadata(Blueprint $table): void
    {
        $table->id()->generatedAs();
        $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
        $table->timestampTz('created_at')->useCurrent();
        $table->timestampTz('updated_at')->useCurrent();
        $table->bigInteger('version')->default(1);
        $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
        $table->unique(['tenant_id', 'id']);
    }
};
