<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_condition_evidence', function (Blueprint $table): void {
            $this->metadata($table);
            $table->bigInteger('order_id');
            $table->text('notes')->nullable();
            $table->bigInteger('recorded_by_membership_id');
            $table->timestampTz('recorded_at');
            $table->unique(['tenant_id', 'order_id']);
            $table->foreign(['tenant_id', 'order_id'])->references(['tenant_id', 'id'])->on('orders')->restrictOnDelete();
            $table->foreign(['tenant_id', 'recorded_by_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE order_condition_evidence ENABLE ROW LEVEL SECURITY');
        DB::statement('CREATE POLICY order_condition_evidence_tenant_scope ON order_condition_evidence FOR ALL USING (tenant_id = app_current_tenant_id()) WITH CHECK (tenant_id = app_current_tenant_id())');
        DB::statement('REVOKE DELETE ON order_condition_evidence FROM netclean_runtime');
        DB::statement('GRANT SELECT, INSERT, UPDATE ON order_condition_evidence TO netclean_runtime');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_condition_evidence');
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
