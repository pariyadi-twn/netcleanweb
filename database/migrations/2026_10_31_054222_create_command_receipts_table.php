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
        Schema::create('command_receipts', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->uuid('client_command_id');
            $table->string('command_type', 100);
            $table->char('request_hash', 64);
            $table->bigInteger('actor_membership_id');
            $table->string('status', 16)->default('processing');
            $table->string('result_resource_type', 80)->nullable();
            $table->uuid('result_public_id')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->unique(['tenant_id', 'client_command_id']);
            $table->index(['tenant_id', 'status']);
            $table->foreign(['tenant_id', 'actor_membership_id'])
                ->references(['tenant_id', 'id'])
                ->on('tenant_memberships')
                ->restrictOnDelete();
        });

        DB::statement("ALTER TABLE command_receipts ADD CONSTRAINT command_receipts_status_check CHECK (status IN ('processing', 'completed', 'rejected'))");
        DB::statement("ALTER TABLE command_receipts ADD CONSTRAINT command_receipts_result_resource_check CHECK (result_resource_type IS NULL OR result_resource_type IN ('tenant_membership'))");
        DB::statement(<<<'SQL'
            ALTER TABLE command_receipts ADD CONSTRAINT command_receipts_terminal_state_check CHECK (
                (status <> 'completed' OR (result_resource_type IS NOT NULL AND result_public_id IS NOT NULL AND completed_at IS NOT NULL))
                AND (status <> 'rejected' OR error_code IS NOT NULL)
                AND (status <> 'processing' OR (result_resource_type IS NULL AND result_public_id IS NULL AND completed_at IS NULL AND error_code IS NULL))
            )
            SQL);

        DB::statement('ALTER TABLE command_receipts ENABLE ROW LEVEL SECURITY');
        DB::statement('CREATE POLICY command_receipts_tenant_scope ON command_receipts FOR ALL USING (tenant_id = app_current_tenant_id()) WITH CHECK (tenant_id = app_current_tenant_id())');
        DB::statement('REVOKE DELETE ON command_receipts FROM netclean_runtime');
        DB::statement('GRANT SELECT, INSERT, UPDATE ON command_receipts TO netclean_runtime');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('command_receipts');
    }
};
