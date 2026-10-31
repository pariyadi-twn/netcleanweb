<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bag_repack_events', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->bigInteger('order_id');
            $table->bigInteger('source_bag_id');
            $table->bigInteger('target_bag_id');
            $table->bigInteger('actor_membership_id');
            $table->jsonb('allocation_snapshot');
            $table->text('reason');
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'order_id', 'created_at']);
            $table->foreign(['tenant_id', 'order_id'])->references(['tenant_id', 'id'])->on('orders')->restrictOnDelete();
            $table->foreign(['tenant_id', 'source_bag_id'])->references(['tenant_id', 'id'])->on('bags')->restrictOnDelete();
            $table->foreign(['tenant_id', 'target_bag_id'])->references(['tenant_id', 'id'])->on('bags')->restrictOnDelete();
            $table->foreign(['tenant_id', 'actor_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE bag_contents DROP CONSTRAINT bag_contents_quantity_check');
        DB::statement('ALTER TABLE bag_contents ADD CONSTRAINT bag_contents_quantity_check CHECK ((is_active = true AND physical_quantity > 0 AND handed_over_quantity >= 0 AND handed_over_quantity <= physical_quantity) OR (is_active = false AND physical_quantity = 0 AND handed_over_quantity = 0))');
        DB::statement('ALTER TABLE bag_repack_events ENABLE ROW LEVEL SECURITY');
        DB::statement('CREATE POLICY bag_repack_events_tenant_scope ON bag_repack_events FOR ALL USING (tenant_id = app_current_tenant_id()) WITH CHECK (tenant_id = app_current_tenant_id())');
        DB::statement('REVOKE UPDATE, DELETE ON bag_repack_events FROM netclean_runtime');
        DB::statement('GRANT SELECT, INSERT ON bag_repack_events TO netclean_runtime');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE bag_contents DROP CONSTRAINT IF EXISTS bag_contents_quantity_check');
        DB::statement('ALTER TABLE bag_contents ADD CONSTRAINT bag_contents_quantity_check CHECK (physical_quantity > 0 AND handed_over_quantity >= 0 AND handed_over_quantity <= physical_quantity)');
        Schema::dropIfExists('bag_repack_events');
    }
};
