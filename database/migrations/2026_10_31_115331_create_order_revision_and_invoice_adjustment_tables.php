<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_revisions', function (Blueprint $table): void {
            $this->metadata($table);
            $table->bigInteger('order_id');
            $table->string('status', 16);
            $table->bigInteger('expected_version');
            $table->text('reason');
            $table->text('decision_reason')->nullable();
            $table->bigInteger('requested_by_membership_id');
            $table->bigInteger('approved_by_membership_id')->nullable();
            $table->jsonb('payload');
            $table->bigInteger('difference_rupiah');
            $table->bigInteger('current_total_rupiah');
            $table->bigInteger('invoice_adjustment_id')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->timestampTz('applied_at')->nullable();
            $table->index(['tenant_id', 'order_id', 'status']);
            $table->index(['tenant_id', 'status']);
            $table->foreign(['tenant_id', 'order_id'])->references(['tenant_id', 'id'])->on('orders')->restrictOnDelete();
            $table->foreign(['tenant_id', 'requested_by_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
            $table->foreign(['tenant_id', 'approved_by_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
        });

        Schema::create('invoice_adjustments', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->bigInteger('invoice_id');
            $table->bigInteger('order_revision_id')->nullable();
            $table->string('kind', 24);
            $table->bigInteger('amount_rupiah');
            $table->text('reason');
            $table->text('decision_reason')->nullable();
            $table->bigInteger('requested_by_membership_id');
            $table->bigInteger('approved_by_membership_id');
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'order_revision_id']);
            $table->index(['tenant_id', 'invoice_id', 'created_at']);
            $table->foreign(['tenant_id', 'invoice_id'])->references(['tenant_id', 'id'])->on('invoices')->restrictOnDelete();
            $table->foreign(['tenant_id', 'order_revision_id'])->references(['tenant_id', 'id'])->on('order_revisions')->restrictOnDelete();
            $table->foreign(['tenant_id', 'requested_by_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
            $table->foreign(['tenant_id', 'approved_by_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE order_revisions ADD CONSTRAINT order_revisions_status_check CHECK (status IN ('pending', 'applied', 'rejected')), ADD CONSTRAINT order_revisions_difference_check CHECK (difference_rupiah <> 0), ADD CONSTRAINT order_revisions_version_check CHECK (expected_version > 0), ADD CONSTRAINT order_revisions_terminal_check CHECK ((status = 'pending' AND approved_by_membership_id IS NULL AND decided_at IS NULL AND applied_at IS NULL) OR (status = 'rejected' AND approved_by_membership_id IS NOT NULL AND decided_at IS NOT NULL AND applied_at IS NULL) OR (status = 'applied' AND approved_by_membership_id IS NOT NULL AND decided_at IS NOT NULL AND applied_at IS NOT NULL AND invoice_adjustment_id IS NOT NULL))");
        DB::statement('CREATE UNIQUE INDEX order_revisions_one_pending_per_order ON order_revisions (tenant_id, order_id) WHERE status = \'pending\'');
        DB::statement("ALTER TABLE invoice_adjustments ADD CONSTRAINT invoice_adjustments_kind_check CHECK (kind IN ('debit_note', 'credit_note')), ADD CONSTRAINT invoice_adjustments_sign_check CHECK ((kind = 'debit_note' AND amount_rupiah > 0) OR (kind = 'credit_note' AND amount_rupiah < 0))");

        foreach (['order_revisions', 'invoice_adjustments'] as $name) {
            DB::statement("ALTER TABLE {$name} ENABLE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY {$name}_tenant_scope ON {$name} FOR ALL USING (tenant_id = app_current_tenant_id()) WITH CHECK (tenant_id = app_current_tenant_id())");
        }

        DB::statement('REVOKE DELETE ON order_revisions FROM netclean_runtime');
        DB::statement('REVOKE UPDATE, DELETE ON invoice_adjustments FROM netclean_runtime');
        DB::statement('GRANT SELECT, INSERT, UPDATE ON order_revisions TO netclean_runtime');
        DB::statement('GRANT SELECT, INSERT ON invoice_adjustments TO netclean_runtime');
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_adjustments');
        Schema::dropIfExists('order_revisions');
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
