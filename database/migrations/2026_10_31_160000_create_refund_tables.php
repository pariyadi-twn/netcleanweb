<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allocation_reversals', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->bigInteger('payment_allocation_id');
            $table->bigInteger('amount_rupiah');
            $table->text('reason');
            $table->bigInteger('approved_by_membership_id');
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'payment_allocation_id']);
            $table->foreign(['tenant_id', 'payment_allocation_id'])->references(['tenant_id', 'id'])->on('payment_allocations')->restrictOnDelete();
            $table->foreign(['tenant_id', 'approved_by_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
        });

        Schema::create('refunds', function (Blueprint $table): void {
            $this->metadata($table);
            $table->bigInteger('payment_id');
            $table->bigInteger('cash_session_id')->nullable();
            $table->bigInteger('amount_rupiah');
            $table->string('status', 24)->default('requested');
            $table->string('external_ref', 150)->nullable();
            $table->bigInteger('requested_by_membership_id');
            $table->bigInteger('approved_by_membership_id')->nullable();
            $table->text('reason');
            $table->timestampTz('succeeded_at')->nullable();
            $table->unique(['tenant_id', 'payment_id', 'id']);
            $table->index(['tenant_id', 'payment_id', 'status']);
            $table->foreign(['tenant_id', 'payment_id'])->references(['tenant_id', 'id'])->on('payments')->restrictOnDelete();
            $table->foreign(['tenant_id', 'cash_session_id'])->references(['tenant_id', 'id'])->on('cash_sessions')->restrictOnDelete();
            $table->foreign(['tenant_id', 'requested_by_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
            $table->foreign(['tenant_id', 'approved_by_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE payment_allocations ADD CONSTRAINT payment_allocations_payment_identity_unique UNIQUE (tenant_id, payment_id, id)');

        Schema::create('refund_allocations', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->bigInteger('refund_id');
            $table->bigInteger('payment_id');
            $table->bigInteger('payment_allocation_id');
            $table->bigInteger('amount_rupiah');
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'refund_id', 'payment_allocation_id']);
            $table->foreign(['tenant_id', 'refund_id', 'payment_id'])->references(['tenant_id', 'id', 'payment_id'])->on('refunds')->restrictOnDelete();
            $table->foreign(['tenant_id', 'payment_allocation_id', 'payment_id'])->references(['tenant_id', 'id', 'payment_id'])->on('payment_allocations')->restrictOnDelete();
        });

        Schema::create('refund_events', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->bigInteger('refund_id');
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->bigInteger('actor_membership_id');
            $table->string('provider_ref', 150)->nullable();
            $table->text('reason')->nullable();
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'refund_id', 'created_at']);
            $table->foreign(['tenant_id', 'refund_id'])->references(['tenant_id', 'id'])->on('refunds')->restrictOnDelete();
            $table->foreign(['tenant_id', 'actor_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE allocation_reversals ADD CONSTRAINT allocation_reversals_amount_check CHECK (amount_rupiah > 0)');
        DB::statement("ALTER TABLE refunds ADD CONSTRAINT refunds_amount_check CHECK (amount_rupiah > 0), ADD CONSTRAINT refunds_status_check CHECK (status IN ('requested', 'approved', 'processing', 'succeeded', 'failed', 'rejected', 'cancelled')), ADD CONSTRAINT refunds_succeeded_check CHECK ((status = 'succeeded' AND succeeded_at IS NOT NULL) OR (status <> 'succeeded' AND succeeded_at IS NULL))");
        DB::statement('ALTER TABLE refund_allocations ADD CONSTRAINT refund_allocations_amount_check CHECK (amount_rupiah > 0)');
        DB::statement("ALTER TABLE refund_events ADD CONSTRAINT refund_events_status_check CHECK (to_status IN ('requested', 'approved', 'processing', 'succeeded', 'failed', 'rejected', 'cancelled'))");

        DB::statement('ALTER TABLE command_receipts DROP CONSTRAINT command_receipts_result_resource_check');
        DB::statement("ALTER TABLE command_receipts ADD CONSTRAINT command_receipts_result_resource_check CHECK (result_resource_type IS NULL OR result_resource_type IN ('tenant_membership', 'order', 'payment', 'refund', 'allocation_reversal'))");

        foreach (['allocation_reversals', 'refunds', 'refund_allocations', 'refund_events'] as $name) {
            DB::statement("ALTER TABLE {$name} ENABLE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY {$name}_tenant_scope ON {$name} FOR ALL USING (tenant_id = app_current_tenant_id()) WITH CHECK (tenant_id = app_current_tenant_id())");
        }

        DB::statement('REVOKE UPDATE, DELETE ON allocation_reversals, refund_allocations, refund_events FROM netclean_runtime');
        DB::statement('REVOKE DELETE ON refunds FROM netclean_runtime');
        DB::statement('GRANT SELECT, INSERT ON allocation_reversals, refund_allocations, refund_events TO netclean_runtime');
        DB::statement('GRANT SELECT, INSERT, UPDATE ON refunds TO netclean_runtime');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE command_receipts DROP CONSTRAINT command_receipts_result_resource_check');
        DB::statement("ALTER TABLE command_receipts ADD CONSTRAINT command_receipts_result_resource_check CHECK (result_resource_type IS NULL OR result_resource_type IN ('tenant_membership', 'order', 'payment'))");
        Schema::dropIfExists('refund_events');
        Schema::dropIfExists('refund_allocations');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('allocation_reversals');
        DB::statement('ALTER TABLE payment_allocations DROP CONSTRAINT payment_allocations_payment_identity_unique');
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
