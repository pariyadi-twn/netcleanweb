<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('saas_plans', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->string('code', 40);
            $table->integer('revision');
            $table->string('name', 100);
            $table->bigInteger('base_price_rupiah')->default(0);
            $table->jsonb('features')->default(DB::raw("'{}'::jsonb"));
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->unique(['code', 'revision']);
            $table->index('is_active');
        });

        Schema::create('saas_subscriptions', function (Blueprint $table): void {
            $this->metadata($table);
            $table->foreignId('plan_id')->constrained('saas_plans')->restrictOnDelete();
            $table->string('status', 24);
            $table->timestampTz('current_period_start');
            $table->timestampTz('current_period_end');
            $table->jsonb('entitlements_snapshot')->default(DB::raw("'{}'::jsonb"));
            $table->timestampTz('grace_until')->nullable();
        });

        DB::statement('CREATE UNIQUE INDEX saas_subscriptions_one_effective ON saas_subscriptions (tenant_id) WHERE status <> \'cancelled\'');
        DB::statement("ALTER TABLE saas_subscriptions ADD CONSTRAINT saas_subscriptions_status_check CHECK (status IN ('trial', 'active', 'grace', 'read_only', 'cancelled')), ADD CONSTRAINT saas_subscriptions_period_check CHECK (current_period_end > current_period_start)");
        DB::statement('ALTER TABLE saas_plans ADD CONSTRAINT saas_plans_revision_check CHECK (revision > 0), ADD CONSTRAINT saas_plans_price_check CHECK (base_price_rupiah >= 0)');

        Schema::create('tenant_usage_counters', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('metric_code', 80);
            $table->date('period');
            $table->bigInteger('used')->default(0);
            $table->bigInteger('reserved')->default(0);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->unique(['tenant_id', 'metric_code', 'period']);
        });

        DB::statement('ALTER TABLE tenant_usage_counters ADD CONSTRAINT tenant_usage_counters_nonnegative_check CHECK (used >= 0 AND reserved >= 0)');

        foreach (['saas_subscriptions', 'tenant_usage_counters'] as $name) {
            DB::statement("ALTER TABLE {$name} ENABLE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY {$name}_tenant_scope ON {$name} FOR ALL USING (tenant_id = app_current_tenant_id()) WITH CHECK (tenant_id = app_current_tenant_id())");
        }

        DB::statement('REVOKE INSERT, UPDATE, DELETE ON saas_plans FROM netclean_runtime');
        DB::statement('GRANT SELECT ON saas_plans TO netclean_runtime');
        DB::statement('REVOKE DELETE ON saas_subscriptions FROM netclean_runtime');
        DB::statement('GRANT SELECT, INSERT, UPDATE ON saas_subscriptions TO netclean_runtime');
        DB::statement('REVOKE DELETE ON tenant_usage_counters FROM netclean_runtime');
        DB::statement('GRANT SELECT, INSERT, UPDATE ON tenant_usage_counters TO netclean_runtime');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_usage_counters');
        Schema::dropIfExists('saas_subscriptions');
        Schema::dropIfExists('saas_plans');
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
