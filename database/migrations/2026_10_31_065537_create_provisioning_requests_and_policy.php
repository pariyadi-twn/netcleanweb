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
        Schema::create('provisioning_requests', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->uuid('client_command_id');
            $table->char('request_hash', 64);
            $table->string('status', 16)->default('processing');
            $table->bigInteger('tenant_id')->nullable();
            $table->uuid('tenant_public_id')->nullable();
            $table->bigInteger('branch_id')->nullable();
            $table->uuid('branch_public_id')->nullable();
            $table->bigInteger('membership_id')->nullable();
            $table->uuid('membership_public_id')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->unique(['user_id', 'client_command_id']);
            $table->index(['user_id', 'status']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches')->restrictOnDelete();
            $table->foreign(['tenant_id', 'membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE provisioning_requests ADD CONSTRAINT provisioning_requests_status_check CHECK (status IN ('processing', 'completed', 'rejected'))");
        DB::statement(<<<'SQL'
            ALTER TABLE provisioning_requests ADD CONSTRAINT provisioning_requests_terminal_state_check CHECK (
                (status <> 'completed' OR (tenant_id IS NOT NULL AND tenant_public_id IS NOT NULL AND branch_id IS NOT NULL AND branch_public_id IS NOT NULL AND membership_id IS NOT NULL AND membership_public_id IS NOT NULL AND completed_at IS NOT NULL))
                AND (status <> 'rejected' OR error_code IS NOT NULL)
                AND (status <> 'processing' OR (tenant_id IS NULL AND branch_id IS NULL AND membership_id IS NULL AND completed_at IS NULL AND error_code IS NULL))
            )
            SQL);

        DB::statement('ALTER TABLE provisioning_requests ENABLE ROW LEVEL SECURITY');
        DB::statement('CREATE POLICY provisioning_requests_user_scope ON provisioning_requests FOR ALL USING (user_id = app_current_user_id()) WITH CHECK (user_id = app_current_user_id())');
        DB::statement('REVOKE DELETE ON provisioning_requests FROM netclean_runtime');
        DB::statement('GRANT SELECT, INSERT, UPDATE ON provisioning_requests TO netclean_runtime');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provisioning_requests');
    }
};
