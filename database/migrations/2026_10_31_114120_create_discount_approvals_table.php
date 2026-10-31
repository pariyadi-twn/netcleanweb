<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_approvals', function (Blueprint $table): void {
            $this->metadata($table);
            $table->bigInteger('quote_id');
            $table->integer('percent');
            $table->bigInteger('requested_by_membership_id');
            $table->bigInteger('approved_by_membership_id')->nullable();
            $table->string('status', 16);
            $table->text('reason')->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->unique(['tenant_id', 'quote_id']);
            $table->index(['tenant_id', 'status']);
            $table->foreign(['tenant_id', 'quote_id'])->references(['tenant_id', 'id'])->on('quotes')->restrictOnDelete();
            $table->foreign(['tenant_id', 'requested_by_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
            $table->foreign(['tenant_id', 'approved_by_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE discount_approvals ADD CONSTRAINT discount_approvals_percent_check CHECK (percent > 0 AND percent <= 100), ADD CONSTRAINT discount_approvals_status_check CHECK (status IN ('pending', 'approved', 'rejected')), ADD CONSTRAINT discount_approvals_decision_check CHECK ((status = 'pending' AND approved_by_membership_id IS NULL AND decided_at IS NULL) OR (status IN ('approved', 'rejected') AND approved_by_membership_id IS NOT NULL AND decided_at IS NOT NULL))");
        DB::statement('ALTER TABLE discount_approvals ENABLE ROW LEVEL SECURITY');
        DB::statement('CREATE POLICY discount_approvals_tenant_scope ON discount_approvals FOR ALL USING (tenant_id = app_current_tenant_id()) WITH CHECK (tenant_id = app_current_tenant_id())');
        DB::statement('REVOKE DELETE ON discount_approvals FROM netclean_runtime');
        DB::statement('GRANT SELECT, INSERT, UPDATE ON discount_approvals TO netclean_runtime');
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_approvals');
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
