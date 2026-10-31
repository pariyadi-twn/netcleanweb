<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qc_results', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->bigInteger('bag_content_id');
            $table->bigInteger('production_task_id');
            $table->bigInteger('inspector_membership_id');
            $table->string('outcome', 16);
            $table->jsonb('checklist')->default(DB::raw("'{}'::jsonb"));
            $table->text('notes')->nullable();
            $table->integer('attempt_no');
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'bag_content_id', 'id']);
            $table->index(['tenant_id', 'production_task_id', 'id']);
            $table->foreign(['tenant_id', 'bag_content_id', 'production_task_id'])->references(['tenant_id', 'bag_content_id', 'id'])->on('production_tasks')->restrictOnDelete();
            $table->foreign(['tenant_id', 'inspector_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE qc_results ADD CONSTRAINT qc_results_outcome_check CHECK (outcome IN ('passed', 'failed')), ADD CONSTRAINT qc_results_attempt_check CHECK (attempt_no > 0), ADD CONSTRAINT qc_results_notes_check CHECK (outcome <> 'failed' OR notes IS NOT NULL)");
        DB::statement('ALTER TABLE qc_results ENABLE ROW LEVEL SECURITY');
        DB::statement('CREATE POLICY qc_results_tenant_scope ON qc_results FOR ALL USING (tenant_id = app_current_tenant_id()) WITH CHECK (tenant_id = app_current_tenant_id())');
        DB::statement('REVOKE UPDATE, DELETE ON qc_results FROM netclean_runtime');
        DB::statement('GRANT SELECT, INSERT ON qc_results TO netclean_runtime');
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_results');
    }
};
