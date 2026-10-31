<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_consents', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->bigInteger('customer_id');
            $table->string('purpose', 40);
            $table->string('channel', 24);
            $table->string('decision', 16);
            $table->string('source', 80);
            $table->timestampTz('occurred_at');
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'customer_id', 'purpose', 'channel', 'occurred_at']);
            $table->foreign(['tenant_id', 'customer_id'])->references(['tenant_id', 'id'])->on('customers')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE customer_consents ADD CONSTRAINT customer_consents_purpose_check CHECK (purpose IN ('service_contact', 'marketing')), ADD CONSTRAINT customer_consents_channel_check CHECK (channel IN ('email', 'whatsapp', 'push')), ADD CONSTRAINT customer_consents_decision_check CHECK (decision IN ('granted', 'withdrawn'))");
        DB::statement('ALTER TABLE customer_consents ENABLE ROW LEVEL SECURITY');
        DB::statement('CREATE POLICY customer_consents_tenant_scope ON customer_consents FOR ALL USING (tenant_id = app_current_tenant_id()) WITH CHECK (tenant_id = app_current_tenant_id())');
        DB::statement('REVOKE UPDATE, DELETE ON customer_consents FROM netclean_runtime');
        DB::statement('GRANT SELECT, INSERT ON customer_consents TO netclean_runtime');
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_consents');
    }
};
