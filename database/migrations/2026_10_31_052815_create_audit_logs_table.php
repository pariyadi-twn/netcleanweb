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
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->bigInteger('actor_membership_id')->nullable();
            $table->string('action', 120);
            $table->string('subject_type', 80);
            $table->uuid('subject_public_id')->nullable();
            $table->string('correlation_id', 100);
            $table->jsonb('redacted_changes')->default(DB::raw("'{}'::jsonb"));
            $table->text('reason')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'subject_type', 'subject_public_id']);
            $table->index(['tenant_id', 'actor_user_id']);
            $table->index('correlation_id');
            $table->foreign(['tenant_id', 'actor_membership_id'])
                ->references(['tenant_id', 'id'])
                ->on('tenant_memberships')
                ->restrictOnDelete();
        });

        DB::statement('ALTER TABLE audit_logs ENABLE ROW LEVEL SECURITY');
        DB::statement('CREATE POLICY audit_logs_tenant_scope ON audit_logs FOR ALL USING (tenant_id = app_current_tenant_id()) WITH CHECK (tenant_id = app_current_tenant_id())');
        DB::statement('REVOKE UPDATE, DELETE ON audit_logs FROM netclean_runtime');
        DB::statement('GRANT SELECT, INSERT ON audit_logs TO netclean_runtime');
        DB::statement('GRANT USAGE, SELECT ON SEQUENCE audit_logs_id_seq TO netclean_runtime');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
