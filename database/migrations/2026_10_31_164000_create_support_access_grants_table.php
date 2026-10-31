<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_access_grants', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->bigInteger('version')->default(1);
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->bigInteger('platform_user_id');
            $table->bigInteger('approved_by_membership_id');
            $table->string('case_reference', 120);
            $table->text('reason');
            $table->jsonb('allowed_actions');
            $table->timestampTz('expires_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->bigInteger('revoked_by_membership_id')->nullable();
            $table->timestampTz('last_used_at')->nullable();
            $table->integer('use_count')->default(0);
            $table->unique(['tenant_id', 'id']);
            $table->index(['platform_user_id', 'expires_at']);
            $table->index(['tenant_id', 'expires_at']);
            $table->foreign('platform_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign(['tenant_id', 'approved_by_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
            $table->foreign(['tenant_id', 'revoked_by_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE support_access_grants ADD CONSTRAINT support_access_grants_actions_check CHECK (jsonb_typeof(allowed_actions) = 'array'), ADD CONSTRAINT support_access_grants_use_count_check CHECK (use_count >= 0), ADD CONSTRAINT support_access_grants_revoked_check CHECK ((revoked_at IS NULL AND revoked_by_membership_id IS NULL) OR (revoked_at IS NOT NULL AND revoked_by_membership_id IS NOT NULL))");

        DB::statement('ALTER TABLE support_access_grants ENABLE ROW LEVEL SECURITY');
        DB::statement('CREATE POLICY support_access_grants_tenant_scope ON support_access_grants FOR ALL USING (tenant_id = app_current_tenant_id()) WITH CHECK (tenant_id = app_current_tenant_id())');
        DB::statement('CREATE POLICY support_access_grants_platform_scope ON support_access_grants FOR SELECT USING (app_identity_bootstrap() AND platform_user_id = app_current_user_id())');
        DB::statement('CREATE POLICY tenants_support_scope ON tenants FOR SELECT USING (EXISTS (SELECT 1 FROM support_access_grants sag WHERE sag.tenant_id = tenants.id AND sag.platform_user_id = app_current_user_id() AND sag.revoked_at IS NULL AND sag.expires_at > now()))');
        DB::statement('REVOKE DELETE ON support_access_grants FROM netclean_runtime');
        DB::statement('GRANT SELECT, INSERT, UPDATE ON support_access_grants TO netclean_runtime');
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenants_support_scope ON tenants');
        Schema::dropIfExists('support_access_grants');
    }
};
