<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_tracking_tokens', function (Blueprint $table): void {
            $this->metadata($table);
            $table->bigInteger('order_id');
            $table->char('token_hash', 64)->unique();
            $table->timestampTz('expires_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampTz('last_used_at')->nullable();
            $table->bigInteger('created_by_membership_id')->nullable();
            $table->index(['tenant_id', 'order_id']);
            $table->foreign(['tenant_id', 'order_id'])->references(['tenant_id', 'id'])->on('orders')->restrictOnDelete();
            $table->foreign(['tenant_id', 'created_by_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
        });

        DB::statement('CREATE UNIQUE INDEX order_tracking_tokens_one_active ON order_tracking_tokens (tenant_id, order_id) WHERE revoked_at IS NULL');
        DB::statement("CREATE OR REPLACE FUNCTION app_tracking_token_hash() RETURNS text LANGUAGE sql STABLE PARALLEL SAFE AS $$ SELECT NULLIF(current_setting('app.tracking_token_hash', true), '') $$");

        DB::statement('ALTER TABLE order_tracking_tokens ENABLE ROW LEVEL SECURITY');
        DB::statement('CREATE POLICY order_tracking_tokens_tenant_scope ON order_tracking_tokens FOR ALL USING (tenant_id = app_current_tenant_id()) WITH CHECK (tenant_id = app_current_tenant_id())');
        DB::statement('CREATE POLICY order_tracking_tokens_public_scope ON order_tracking_tokens FOR SELECT USING (app_tracking_token_hash() IS NOT NULL AND token_hash = app_tracking_token_hash() AND revoked_at IS NULL AND expires_at > now())');
        DB::statement('REVOKE DELETE ON order_tracking_tokens FROM netclean_runtime');
        DB::statement('GRANT SELECT, INSERT, UPDATE ON order_tracking_tokens TO netclean_runtime');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_tracking_tokens');
        DB::statement('DROP FUNCTION IF EXISTS app_tracking_token_hash()');
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
