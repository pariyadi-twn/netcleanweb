<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->bigInteger('version')->default(1);
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->bigInteger('requested_by_membership_id');
            $table->string('type', 24);
            $table->string('source', 24)->default('csv');
            $table->string('file_name', 255);
            $table->char('checksum', 64);
            $table->string('raw_object_key', 255)->nullable();
            $table->timestampTz('raw_expires_at')->nullable();
            $table->timestampTz('raw_purged_at')->nullable();
            $table->boolean('dry_run')->default(false);
            $table->string('status', 24)->default('processing');
            $table->integer('total_rows')->default(0);
            $table->integer('valid_rows')->default(0);
            $table->integer('invalid_rows')->default(0);
            $table->integer('duplicate_rows')->default(0);
            $table->integer('imported_rows')->default(0);
            $table->timestampTz('completed_at')->nullable();
            $table->text('error_code')->nullable();
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'type', 'checksum']);
            $table->index(['tenant_id', 'raw_expires_at']);
            $table->foreign(['tenant_id', 'requested_by_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
        });

        Schema::create('import_rows', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->bigInteger('import_batch_id');
            $table->integer('row_number');
            $table->string('status', 24);
            $table->jsonb('raw_payload')->nullable();
            $table->jsonb('normalized_payload')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->text('error_message')->nullable();
            $table->uuid('target_public_id')->nullable();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'import_batch_id', 'row_number']);
            $table->index(['tenant_id', 'import_batch_id', 'status']);
            $table->foreign(['tenant_id', 'import_batch_id'])->references(['tenant_id', 'id'])->on('import_batches')->restrictOnDelete();
        });

        Schema::create('report_exports', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->bigInteger('version')->default(1);
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->bigInteger('requested_by_membership_id');
            $table->bigInteger('requested_by_user_id');
            $table->string('report_type', 80);
            $table->jsonb('filters');
            $table->string('status', 24)->default('queued');
            $table->bigInteger('attachment_id')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampTz('completed_at')->nullable();
            $table->integer('row_count')->default(0);
            $table->bigInteger('file_size_bytes')->nullable();
            $table->text('error_code')->nullable();
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'requested_by_membership_id', 'status']);
            $table->index(['tenant_id', 'expires_at']);
            $table->foreign(['tenant_id', 'requested_by_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
            $table->foreign(['tenant_id', 'attachment_id'])->references(['tenant_id', 'id'])->on('attachments')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE import_batches ADD CONSTRAINT import_batches_type_check CHECK (type IN ('customers', 'catalog')), ADD CONSTRAINT import_batches_source_check CHECK (source = 'csv'), ADD CONSTRAINT import_batches_status_check CHECK (status IN ('processing', 'completed', 'failed')), ADD CONSTRAINT import_batches_counts_check CHECK (total_rows >= 0 AND valid_rows >= 0 AND invalid_rows >= 0 AND duplicate_rows >= 0 AND imported_rows >= 0)");
        DB::statement("ALTER TABLE import_rows ADD CONSTRAINT import_rows_status_check CHECK (status IN ('validated', 'imported', 'duplicate', 'invalid'))");
        DB::statement("ALTER TABLE report_exports ADD CONSTRAINT report_exports_status_check CHECK (status IN ('queued', 'running', 'ready', 'failed', 'expired')), ADD CONSTRAINT report_exports_type_check CHECK (report_type IN ('outlet_sales')), ADD CONSTRAINT report_exports_rows_check CHECK (row_count >= 0)");
        DB::statement("ALTER TABLE report_exports ADD CONSTRAINT report_exports_ready_check CHECK (status <> 'ready' OR (attachment_id IS NOT NULL AND completed_at IS NOT NULL))");

        DB::statement('ALTER TABLE command_receipts DROP CONSTRAINT command_receipts_result_resource_check');
        DB::statement("ALTER TABLE command_receipts ADD CONSTRAINT command_receipts_result_resource_check CHECK (result_resource_type IS NULL OR result_resource_type IN ('tenant_membership', 'order', 'payment', 'refund', 'allocation_reversal', 'expense', 'cash_transfer', 'import_batch', 'report_export'))");

        foreach (['import_batches', 'import_rows', 'report_exports'] as $name) {
            DB::statement("ALTER TABLE {$name} ENABLE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY {$name}_tenant_scope ON {$name} FOR ALL USING (tenant_id = app_current_tenant_id()) WITH CHECK (tenant_id = app_current_tenant_id())");
        }

        DB::statement('REVOKE DELETE ON import_batches, import_rows, report_exports FROM netclean_runtime');
        DB::statement('REVOKE UPDATE, DELETE ON import_rows FROM netclean_runtime');
        DB::statement('GRANT SELECT, INSERT, UPDATE ON import_batches, report_exports TO netclean_runtime');
        DB::statement('GRANT SELECT, INSERT ON import_rows TO netclean_runtime');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE command_receipts DROP CONSTRAINT command_receipts_result_resource_check');
        DB::statement("ALTER TABLE command_receipts ADD CONSTRAINT command_receipts_result_resource_check CHECK (result_resource_type IS NULL OR result_resource_type IN ('tenant_membership', 'order', 'payment', 'refund', 'allocation_reversal', 'expense', 'cash_transfer'))");
        Schema::dropIfExists('report_exports');
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('import_batches');
    }
};
