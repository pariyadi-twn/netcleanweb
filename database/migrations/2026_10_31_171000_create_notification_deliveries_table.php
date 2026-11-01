<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $this->metadata($table);
            $table->uuid('event_id');
            $table->bigInteger('order_id')->nullable();
            $table->string('channel', 24);
            $table->string('business_key', 200);
            $table->text('recipient_ref');
            $table->string('subject', 200);
            $table->text('message');
            $table->jsonb('payload')->default(DB::raw("'{}'::jsonb"));
            $table->string('status', 24)->default('queued');
            $table->string('provider_reference', 150)->nullable();
            $table->integer('attempts')->default(0);
            $table->string('last_error_code', 80)->nullable();
            $table->timestampTz('next_attempt_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->unique(['tenant_id', 'business_key', 'channel']);
            $table->index(['tenant_id', 'status', 'next_attempt_at']);
            $table->foreign(['tenant_id', 'order_id'])->references(['tenant_id', 'id'])->on('orders')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE notification_deliveries ADD CONSTRAINT notification_deliveries_channel_check CHECK (channel IN ('email', 'whatsapp', 'push')), ADD CONSTRAINT notification_deliveries_status_check CHECK (status IN ('queued', 'sending', 'sent', 'failed', 'unknown')), ADD CONSTRAINT notification_deliveries_attempts_check CHECK (attempts >= 0)");
        DB::statement('ALTER TABLE notification_deliveries ENABLE ROW LEVEL SECURITY');
        DB::statement('CREATE POLICY notification_deliveries_tenant_scope ON notification_deliveries FOR ALL USING (tenant_id = app_current_tenant_id()) WITH CHECK (tenant_id = app_current_tenant_id())');
        DB::statement('CREATE POLICY notification_deliveries_platform_scope ON notification_deliveries FOR SELECT USING (app_identity_bootstrap() AND EXISTS (SELECT 1 FROM users u WHERE u.id = app_current_user_id() AND u.is_platform_admin))');
        DB::statement('REVOKE DELETE ON notification_deliveries FROM netclean_runtime');
        DB::statement('GRANT SELECT, INSERT, UPDATE ON notification_deliveries TO netclean_runtime');
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
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
