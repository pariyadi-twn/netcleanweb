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
        Schema::create('workflow_templates', function (Blueprint $table): void {
            $this->metadata($table);
            $table->string('code', 40);
            $table->integer('revision');
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->unique(['tenant_id', 'code', 'revision']);
            $table->index(['tenant_id', 'is_active', 'code']);
        });

        Schema::create('workflow_steps', function (Blueprint $table): void {
            $this->metadata($table);
            $table->bigInteger('workflow_template_id');
            $table->string('code', 40);
            $table->integer('sequence');
            $table->boolean('is_required')->default(true);
            $table->boolean('requires_qc')->default(false);
            $table->unique(['tenant_id', 'workflow_template_id', 'code']);
            $table->unique(['tenant_id', 'workflow_template_id', 'sequence']);
            $table->foreign(['tenant_id', 'workflow_template_id'])
                ->references(['tenant_id', 'id'])
                ->on('workflow_templates')
                ->restrictOnDelete();
        });

        Schema::create('services', function (Blueprint $table): void {
            $this->metadata($table);
            $table->string('code', 40);
            $table->string('name', 150);
            $table->string('unit', 16);
            $table->decimal('minimum_quantity', 12, 3);
            $table->decimal('quantity_step', 12, 3);
            $table->bigInteger('workflow_template_id');
            $table->boolean('is_active')->default(false);
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active', 'name']);
            $table->foreign(['tenant_id', 'workflow_template_id'])
                ->references(['tenant_id', 'id'])
                ->on('workflow_templates')
                ->restrictOnDelete();
        });

        DB::statement('CREATE UNIQUE INDEX workflow_templates_one_active_revision ON workflow_templates (tenant_id, code) WHERE is_active = true');
        DB::statement('ALTER TABLE workflow_templates ADD CONSTRAINT workflow_templates_revision_check CHECK (revision > 0)');
        DB::statement('ALTER TABLE workflow_steps ADD CONSTRAINT workflow_steps_sequence_check CHECK (sequence > 0)');
        DB::statement("ALTER TABLE services ADD CONSTRAINT services_unit_check CHECK (unit IN ('kg', 'pcs', 'pair', 'meter', 'package')), ADD CONSTRAINT services_minimum_check CHECK (minimum_quantity >= 0), ADD CONSTRAINT services_step_check CHECK (quantity_step > 0), ADD CONSTRAINT services_discrete_quantity_check CHECK (unit NOT IN ('pcs', 'pair', 'package') OR (minimum_quantity = trunc(minimum_quantity) AND quantity_step = trunc(quantity_step)))");

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_workflow_template_definition_update() RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.tenant_id IS DISTINCT FROM OLD.tenant_id
                    OR NEW.public_id IS DISTINCT FROM OLD.public_id
                    OR NEW.code IS DISTINCT FROM OLD.code
                    OR NEW.revision IS DISTINCT FROM OLD.revision
                    OR NEW.name IS DISTINCT FROM OLD.name THEN
                    RAISE EXCEPTION 'Workflow definitions are immutable; create a new revision.' USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $$
        SQL);
        DB::statement('CREATE TRIGGER workflow_templates_immutable_definition BEFORE UPDATE ON workflow_templates FOR EACH ROW EXECUTE FUNCTION prevent_workflow_template_definition_update()');

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_workflow_step_mutation() RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Workflow steps are immutable; create a new workflow revision.' USING ERRCODE = '23514';
            END;
            $$
        SQL);
        DB::statement('CREATE TRIGGER workflow_steps_immutable BEFORE UPDATE OR DELETE ON workflow_steps FOR EACH ROW EXECUTE FUNCTION prevent_workflow_step_mutation()');

        foreach (['workflow_templates', 'workflow_steps', 'services'] as $name) {
            DB::statement("ALTER TABLE {$name} ENABLE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY {$name}_tenant_scope ON {$name} FOR ALL USING (tenant_id = app_current_tenant_id()) WITH CHECK (tenant_id = app_current_tenant_id())");
        }

        DB::statement('REVOKE DELETE ON workflow_templates, services FROM netclean_runtime');
        DB::statement('REVOKE UPDATE, DELETE ON workflow_steps FROM netclean_runtime');
        DB::statement('GRANT SELECT, INSERT, UPDATE ON workflow_templates, services TO netclean_runtime');
        DB::statement('GRANT SELECT, INSERT ON workflow_steps TO netclean_runtime');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
        Schema::dropIfExists('workflow_steps');
        Schema::dropIfExists('workflow_templates');
        DB::statement('DROP FUNCTION IF EXISTS prevent_workflow_step_mutation()');
        DB::statement('DROP FUNCTION IF EXISTS prevent_workflow_template_definition_update()');
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
