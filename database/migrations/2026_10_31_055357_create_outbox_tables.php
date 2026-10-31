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
        Schema::create('outbox_events', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->uuid('event_id');
            $table->string('event_type', 100);
            $table->uuid('aggregate_public_id');
            $table->jsonb('payload')->default(DB::raw("'{}'::jsonb"));
            $table->timestampTz('created_at')->useCurrent();
            $table->unique('event_id');
            $table->unique(['tenant_id', 'event_id']);
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('event_deliveries', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->uuid('event_id');
            $table->string('consumer_name', 100);
            $table->unsignedInteger('attempts')->default(0);
            $table->timestampTz('lease_until')->nullable();
            $table->timestampTz('last_dispatched_at')->nullable();
            $table->timestampTz('next_attempt_at')->useCurrent();
            $table->string('last_error_code', 80)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->unique(['tenant_id', 'event_id', 'consumer_name']);
            $table->index(['tenant_id', 'next_attempt_at', 'lease_until']);
            $table->foreign(['tenant_id', 'event_id'])->references(['tenant_id', 'event_id'])->on('outbox_events')->restrictOnDelete();
        });

        Schema::create('consumer_receipts', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->uuid('event_id');
            $table->string('consumer_name', 100);
            $table->timestampTz('applied_at');
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['tenant_id', 'event_id', 'consumer_name']);
            $table->index(['tenant_id', 'consumer_name', 'applied_at']);
            $table->foreign(['tenant_id', 'event_id'])->references(['tenant_id', 'event_id'])->on('outbox_events')->restrictOnDelete();
        });

        foreach (['outbox_events', 'event_deliveries', 'consumer_receipts'] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY {$table}_tenant_scope ON {$table} FOR ALL USING (tenant_id = app_current_tenant_id()) WITH CHECK (tenant_id = app_current_tenant_id())");
        }

        DB::statement('REVOKE UPDATE, DELETE ON outbox_events FROM netclean_runtime');
        DB::statement('GRANT SELECT, INSERT ON outbox_events TO netclean_runtime');
        DB::statement('REVOKE DELETE ON event_deliveries FROM netclean_runtime');
        DB::statement('GRANT SELECT, INSERT, UPDATE ON event_deliveries TO netclean_runtime');
        DB::statement('REVOKE UPDATE, DELETE ON consumer_receipts FROM netclean_runtime');
        DB::statement('GRANT SELECT, INSERT ON consumer_receipts TO netclean_runtime');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consumer_receipts');
        Schema::dropIfExists('event_deliveries');
        Schema::dropIfExists('outbox_events');
    }
};
