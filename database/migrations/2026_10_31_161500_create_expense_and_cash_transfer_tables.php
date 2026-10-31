<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->bigInteger('version')->default(1);
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->bigInteger('branch_id');
            $table->bigInteger('cash_session_id')->nullable();
            $table->string('category', 64);
            $table->text('description');
            $table->bigInteger('amount_rupiah');
            $table->timestampTz('occurred_at');
            $table->string('approval_status', 24)->default('draft');
            $table->bigInteger('requested_by_membership_id');
            $table->bigInteger('approved_by_membership_id')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'branch_id', 'occurred_at']);
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches')->restrictOnDelete();
            $table->foreign(['tenant_id', 'cash_session_id'])->references(['tenant_id', 'id'])->on('cash_sessions')->restrictOnDelete();
            $table->foreign(['tenant_id', 'requested_by_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
            $table->foreign(['tenant_id', 'approved_by_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
        });

        Schema::create('cash_transfers', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->bigInteger('version')->default(1);
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->bigInteger('from_register_id')->nullable();
            $table->bigInteger('to_register_id')->nullable();
            $table->bigInteger('from_cash_session_id')->nullable();
            $table->bigInteger('to_cash_session_id')->nullable();
            $table->bigInteger('amount_rupiah');
            $table->string('kind', 24);
            $table->string('status', 24)->default('confirmed');
            $table->string('reference', 150)->nullable();
            $table->text('reason');
            $table->bigInteger('requested_by_membership_id');
            $table->bigInteger('confirmed_by_membership_id');
            $table->timestampTz('confirmed_at');
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'kind', 'confirmed_at']);
            $table->foreign(['tenant_id', 'from_register_id'])->references(['tenant_id', 'id'])->on('cash_registers')->restrictOnDelete();
            $table->foreign(['tenant_id', 'to_register_id'])->references(['tenant_id', 'id'])->on('cash_registers')->restrictOnDelete();
            $table->foreign(['tenant_id', 'from_cash_session_id'])->references(['tenant_id', 'id'])->on('cash_sessions')->restrictOnDelete();
            $table->foreign(['tenant_id', 'to_cash_session_id'])->references(['tenant_id', 'id'])->on('cash_sessions')->restrictOnDelete();
            $table->foreign(['tenant_id', 'requested_by_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
            $table->foreign(['tenant_id', 'confirmed_by_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE expenses ADD CONSTRAINT expenses_amount_check CHECK (amount_rupiah > 0), ADD CONSTRAINT expenses_status_check CHECK (approval_status IN ('draft', 'approved', 'rejected')), ADD CONSTRAINT expenses_approval_check CHECK ((approval_status = 'approved' AND approved_by_membership_id IS NOT NULL AND approved_at IS NOT NULL AND cash_session_id IS NOT NULL) OR (approval_status <> 'approved' AND approved_at IS NULL))");
        DB::statement("ALTER TABLE cash_transfers ADD CONSTRAINT cash_transfers_amount_check CHECK (amount_rupiah > 0), ADD CONSTRAINT cash_transfers_kind_check CHECK (kind IN ('inter_register', 'bank_deposit', 'owner_funding')), ADD CONSTRAINT cash_transfers_status_check CHECK (status IN ('pending', 'confirmed', 'reversed')), ADD CONSTRAINT cash_transfers_register_check CHECK (from_register_id IS NOT NULL OR to_register_id IS NOT NULL), ADD CONSTRAINT cash_transfers_kind_route_check CHECK ((kind = 'inter_register' AND from_register_id IS NOT NULL AND to_register_id IS NOT NULL AND from_register_id <> to_register_id) OR (kind = 'bank_deposit' AND from_register_id IS NOT NULL AND to_register_id IS NULL) OR (kind = 'owner_funding' AND from_register_id IS NULL AND to_register_id IS NOT NULL))");

        DB::statement('ALTER TABLE command_receipts DROP CONSTRAINT command_receipts_result_resource_check');
        DB::statement("ALTER TABLE command_receipts ADD CONSTRAINT command_receipts_result_resource_check CHECK (result_resource_type IS NULL OR result_resource_type IN ('tenant_membership', 'order', 'payment', 'refund', 'allocation_reversal', 'expense', 'cash_transfer'))");

        foreach (['expenses', 'cash_transfers'] as $name) {
            DB::statement("ALTER TABLE {$name} ENABLE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY {$name}_tenant_scope ON {$name} FOR ALL USING (tenant_id = app_current_tenant_id()) WITH CHECK (tenant_id = app_current_tenant_id())");
        }

        DB::statement('REVOKE DELETE ON expenses, cash_transfers FROM netclean_runtime');
        DB::statement('GRANT SELECT, INSERT, UPDATE ON expenses, cash_transfers TO netclean_runtime');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE command_receipts DROP CONSTRAINT command_receipts_result_resource_check');
        DB::statement("ALTER TABLE command_receipts ADD CONSTRAINT command_receipts_result_resource_check CHECK (result_resource_type IS NULL OR result_resource_type IN ('tenant_membership', 'order', 'payment', 'refund', 'allocation_reversal'))");
        Schema::dropIfExists('cash_transfers');
        Schema::dropIfExists('expenses');
    }
};
