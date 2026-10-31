<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->bigInteger('payment_attempt_id')->nullable();
            $table->bigInteger('customer_id')->nullable();
            $table->bigInteger('received_branch_id');
            $table->bigInteger('cash_session_id')->nullable();
            $table->string('method', 24);
            $table->bigInteger('amount_rupiah');
            $table->char('currency', 3)->default('IDR');
            $table->bigInteger('cash_received_rupiah');
            $table->bigInteger('change_rupiah')->default(0);
            $table->bigInteger('unallocated_rupiah')->default(0);
            $table->timestampTz('received_at');
            $table->bigInteger('received_by_membership_id')->nullable();
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'received_branch_id', 'received_at']);
            $table->foreign(['tenant_id', 'customer_id'])->references(['tenant_id', 'id'])->on('customers')->restrictOnDelete();
            $table->foreign(['tenant_id', 'received_branch_id'])->references(['tenant_id', 'id'])->on('branches')->restrictOnDelete();
            $table->foreign(['tenant_id', 'cash_session_id'])->references(['tenant_id', 'id'])->on('cash_sessions')->restrictOnDelete();
            $table->foreign(['tenant_id', 'received_by_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
        });

        Schema::create('payment_allocations', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->bigInteger('payment_id');
            $table->bigInteger('invoice_id');
            $table->bigInteger('amount_rupiah');
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'payment_id', 'invoice_id']);
            $table->index(['tenant_id', 'invoice_id']);
            $table->foreign(['tenant_id', 'payment_id'])->references(['tenant_id', 'id'])->on('payments')->restrictOnDelete();
            $table->foreign(['tenant_id', 'invoice_id'])->references(['tenant_id', 'id'])->on('invoices')->restrictOnDelete();
        });

        Schema::create('credit_approvals', function (Blueprint $table): void {
            $this->metadata($table);
            $table->bigInteger('invoice_id');
            $table->bigInteger('approved_by_membership_id');
            $table->bigInteger('limit_rupiah');
            $table->timestampTz('expires_at')->nullable();
            $table->string('status', 24)->default('active');
            $table->text('reason');
            $table->unique(['tenant_id', 'invoice_id']);
            $table->foreign(['tenant_id', 'invoice_id'])->references(['tenant_id', 'id'])->on('invoices')->restrictOnDelete();
            $table->foreign(['tenant_id', 'approved_by_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_method_check CHECK (method IN ('cash', 'bank_manual')), ADD CONSTRAINT payments_currency_check CHECK (currency = 'IDR'), ADD CONSTRAINT payments_amount_check CHECK (amount_rupiah > 0 AND cash_received_rupiah >= amount_rupiah AND change_rupiah >= 0 AND unallocated_rupiah >= 0 AND change_rupiah = cash_received_rupiah - amount_rupiah)");
        DB::statement('ALTER TABLE payment_allocations ADD CONSTRAINT payment_allocations_amount_check CHECK (amount_rupiah > 0)');
        DB::statement("ALTER TABLE credit_approvals ADD CONSTRAINT credit_approvals_limit_check CHECK (limit_rupiah >= 0), ADD CONSTRAINT credit_approvals_status_check CHECK (status IN ('active', 'consumed', 'revoked'))");

        DB::statement('ALTER TABLE command_receipts DROP CONSTRAINT command_receipts_result_resource_check');
        DB::statement("ALTER TABLE command_receipts ADD CONSTRAINT command_receipts_result_resource_check CHECK (result_resource_type IS NULL OR result_resource_type IN ('tenant_membership', 'order', 'payment'))");

        foreach (['payments', 'payment_allocations', 'credit_approvals'] as $name) {
            DB::statement("ALTER TABLE {$name} ENABLE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY {$name}_tenant_scope ON {$name} FOR ALL USING (tenant_id = app_current_tenant_id()) WITH CHECK (tenant_id = app_current_tenant_id())");
        }

        DB::statement('REVOKE UPDATE, DELETE ON payments, payment_allocations FROM netclean_runtime');
        DB::statement('REVOKE DELETE ON credit_approvals FROM netclean_runtime');
        DB::statement('GRANT SELECT, INSERT ON payments, payment_allocations TO netclean_runtime');
        DB::statement('GRANT SELECT, INSERT, UPDATE ON credit_approvals TO netclean_runtime');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE command_receipts DROP CONSTRAINT command_receipts_result_resource_check');
        DB::statement("ALTER TABLE command_receipts ADD CONSTRAINT command_receipts_result_resource_check CHECK (result_resource_type IS NULL OR result_resource_type IN ('tenant_membership', 'order'))");
        Schema::dropIfExists('credit_approvals');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
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
