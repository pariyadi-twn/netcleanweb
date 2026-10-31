<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_requests', function (Blueprint $table): void {
            $this->metadata($table);
            $table->bigInteger('requested_by_membership_id');
            $table->string('type', 24)->default('deletion');
            $table->string('status', 24)->default('requested');
            $table->text('reason');
            $table->bigInteger('target_customer_id')->nullable();
            $table->string('verification_method', 80)->nullable();
            $table->bigInteger('verified_by_user_id')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->bigInteger('processed_by_user_id')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->jsonb('result_summary')->nullable();
            $table->foreign(['tenant_id', 'requested_by_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
            $table->foreign(['tenant_id', 'target_customer_id'])->references(['tenant_id', 'id'])->on('customers')->restrictOnDelete();
            $table->foreign('verified_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('processed_by_user_id')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('data_deletion_tombstones', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->bigInteger('data_request_id')->nullable();
            $table->string('subject_type', 24);
            $table->uuid('subject_public_id');
            $table->text('reason');
            $table->timestampTz('applied_at');
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'subject_type', 'subject_public_id']);
            $table->foreign(['tenant_id', 'data_request_id'])->references(['tenant_id', 'id'])->on('data_requests')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE data_requests ADD CONSTRAINT data_requests_type_check CHECK (type = 'deletion'), ADD CONSTRAINT data_requests_status_check CHECK (status IN ('requested', 'verified', 'completed', 'rejected')), ADD CONSTRAINT data_requests_verified_check CHECK (status NOT IN ('verified', 'completed') OR (verified_by_user_id IS NOT NULL AND verified_at IS NOT NULL))");
        DB::statement("ALTER TABLE data_deletion_tombstones ADD CONSTRAINT data_deletion_tombstones_subject_check CHECK (subject_type = 'customer')");

        foreach (['data_requests', 'data_deletion_tombstones'] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY {$table}_tenant_scope ON {$table} FOR ALL USING (tenant_id = app_current_tenant_id()) WITH CHECK (tenant_id = app_current_tenant_id())");
            DB::statement("CREATE POLICY {$table}_platform_scope ON {$table} FOR ALL USING (app_identity_bootstrap() AND EXISTS (SELECT 1 FROM users u WHERE u.id = app_current_user_id() AND u.is_platform_admin)) WITH CHECK (app_identity_bootstrap() AND EXISTS (SELECT 1 FROM users u WHERE u.id = app_current_user_id() AND u.is_platform_admin))");
        }

        DB::statement('REVOKE DELETE ON data_requests, data_deletion_tombstones FROM netclean_runtime');
        DB::statement('REVOKE UPDATE, DELETE ON data_deletion_tombstones FROM netclean_runtime');
        DB::statement('GRANT SELECT, INSERT, UPDATE ON data_requests TO netclean_runtime');
        DB::statement('GRANT SELECT, INSERT ON data_deletion_tombstones TO netclean_runtime');
        DB::statement('GRANT INSERT, UPDATE ON saas_plans TO netclean_runtime');
        DB::statement('REVOKE DELETE ON customers, customer_addresses FROM netclean_runtime');
        DB::statement('GRANT SELECT, INSERT, UPDATE ON customers, customer_addresses TO netclean_runtime');
    }

    public function down(): void
    {
        DB::statement('REVOKE INSERT, UPDATE ON saas_plans FROM netclean_runtime');
        Schema::dropIfExists('data_deletion_tombstones');
        Schema::dropIfExists('data_requests');
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
