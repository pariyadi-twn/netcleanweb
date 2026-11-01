<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table): void {
            $this->metadata($table);
            $table->bigInteger('order_id');
            $table->bigInteger('order_line_id')->nullable();
            $table->string('kind', 24);
            $table->string('status', 24)->default('open');
            $table->text('description');
            $table->text('resolution')->nullable();
            $table->bigInteger('opened_by_membership_id');
            $table->bigInteger('resolved_by_membership_id')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->bigInteger('approved_adjustment_id')->nullable();
            $table->integer('rewash_attempt_no')->nullable();
            $table->foreign(['tenant_id', 'order_id'])->references(['tenant_id', 'id'])->on('orders')->restrictOnDelete();
            $table->foreign(['tenant_id', 'order_line_id'])->references(['tenant_id', 'id'])->on('order_lines')->restrictOnDelete();
            $table->foreign(['tenant_id', 'opened_by_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
            $table->foreign(['tenant_id', 'resolved_by_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
            $table->foreign(['tenant_id', 'approved_adjustment_id'])->references(['tenant_id', 'id'])->on('invoice_adjustments')->restrictOnDelete();
        });

        Schema::create('complaint_attachments', function (Blueprint $table): void {
            $table->bigInteger('tenant_id');
            $table->bigInteger('complaint_id');
            $table->bigInteger('attachment_id');
            $table->timestampTz('created_at')->useCurrent();
            $table->primary(['tenant_id', 'complaint_id', 'attachment_id']);
            $table->foreign(['tenant_id', 'complaint_id'])->references(['tenant_id', 'id'])->on('complaints')->restrictOnDelete();
            $table->foreign(['tenant_id', 'attachment_id'])->references(['tenant_id', 'id'])->on('attachments')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE complaints ADD CONSTRAINT complaints_kind_check CHECK (kind IN ('rewash', 'missing', 'damaged', 'other')), ADD CONSTRAINT complaints_status_check CHECK (status IN ('open', 'investigating', 'resolved', 'rejected')), ADD CONSTRAINT complaints_resolution_check CHECK ((status IN ('resolved', 'rejected') AND resolution IS NOT NULL AND resolved_by_membership_id IS NOT NULL AND resolved_at IS NOT NULL) OR (status IN ('open', 'investigating') AND resolved_at IS NULL))");

        foreach (['complaints'] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY {$table}_tenant_scope ON {$table} FOR ALL USING (tenant_id = app_current_tenant_id()) WITH CHECK (tenant_id = app_current_tenant_id())");
        }
        DB::statement('ALTER TABLE complaint_attachments ENABLE ROW LEVEL SECURITY');
        DB::statement('CREATE POLICY complaint_attachments_tenant_scope ON complaint_attachments FOR ALL USING (tenant_id = app_current_tenant_id()) WITH CHECK (tenant_id = app_current_tenant_id())');

        DB::statement('REVOKE DELETE ON complaints, complaint_attachments FROM netclean_runtime');
        DB::statement('REVOKE UPDATE, DELETE ON complaint_attachments FROM netclean_runtime');
        DB::statement('GRANT SELECT, INSERT, UPDATE ON complaints TO netclean_runtime');
        DB::statement('GRANT SELECT, INSERT ON complaint_attachments TO netclean_runtime');
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_attachments');
        Schema::dropIfExists('complaints');
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
