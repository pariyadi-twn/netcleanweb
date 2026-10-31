<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_tasks', function (Blueprint $table): void {
            $this->metadata($table);
            $table->bigInteger('bag_content_id');
            $table->bigInteger('production_location_id');
            $table->string('step_code', 40);
            $table->integer('sequence');
            $table->boolean('is_required')->default(true);
            $table->boolean('requires_qc')->default(false);
            $table->integer('attempt_no')->default(1);
            $table->bigInteger('original_task_id')->nullable();
            $table->string('status', 24)->default('queued');
            $table->timestampTz('due_at');
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->unique(['tenant_id', 'bag_content_id', 'step_code', 'attempt_no']);
            $table->unique(['tenant_id', 'bag_content_id', 'id']);
            $table->index(['tenant_id', 'production_location_id', 'status', 'due_at', 'id'], 'production_tasks_queue');
            $table->foreign(['tenant_id', 'bag_content_id'])->references(['tenant_id', 'id'])->on('bag_contents')->restrictOnDelete();
            $table->foreign(['tenant_id', 'production_location_id'])->references(['tenant_id', 'id'])->on('locations')->restrictOnDelete();
            $table->foreign(['tenant_id', 'original_task_id'])->references(['tenant_id', 'id'])->on('production_tasks')->restrictOnDelete();
        });

        Schema::create('production_task_events', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->bigInteger('production_task_id');
            $table->bigInteger('actor_membership_id');
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->text('reason')->nullable();
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'production_task_id', 'created_at']);
            $table->foreign(['tenant_id', 'production_task_id'])->references(['tenant_id', 'id'])->on('production_tasks')->restrictOnDelete();
            $table->foreign(['tenant_id', 'actor_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE production_tasks ADD CONSTRAINT production_tasks_status_check CHECK (status IN ('queued', 'in_progress', 'done', 'blocked', 'skipped')), ADD CONSTRAINT production_tasks_sequence_check CHECK (sequence > 0 AND attempt_no > 0)");
        DB::statement("ALTER TABLE production_task_events ADD CONSTRAINT production_task_events_status_check CHECK (to_status IN ('queued', 'in_progress', 'done', 'blocked', 'skipped')), ADD CONSTRAINT production_task_events_reason_check CHECK ((to_status NOT IN ('blocked', 'skipped') AND from_status <> 'blocked') OR reason IS NOT NULL)");

        foreach (['production_tasks', 'production_task_events'] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY {$table}_tenant_scope ON {$table} FOR ALL USING (tenant_id = app_current_tenant_id()) WITH CHECK (tenant_id = app_current_tenant_id())");
        }

        DB::statement('REVOKE DELETE ON production_tasks, production_task_events FROM netclean_runtime');
        DB::statement('REVOKE UPDATE, DELETE ON production_task_events FROM netclean_runtime');
        DB::statement('GRANT SELECT, INSERT, UPDATE ON production_tasks TO netclean_runtime');
        DB::statement('GRANT SELECT, INSERT ON production_task_events TO netclean_runtime');
    }

    public function down(): void
    {
        Schema::dropIfExists('production_task_events');
        Schema::dropIfExists('production_tasks');
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
