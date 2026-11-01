<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('handovers', function (Blueprint $table): void {
            $this->metadata($table);
            $table->bigInteger('order_id');
            $table->bigInteger('location_id');
            $table->bigInteger('actor_membership_id');
            $table->string('recipient_name', 150);
            $table->string('verification_method', 32);
            $table->bigInteger('credit_approval_id')->nullable();
            $table->timestampTz('completed_at');
            $table->unique(['tenant_id', 'order_id', 'id']);
            $table->index(['tenant_id', 'order_id', 'completed_at']);
            $table->foreign(['tenant_id', 'order_id'])->references(['tenant_id', 'id'])->on('orders')->restrictOnDelete();
            $table->foreign(['tenant_id', 'location_id'])->references(['tenant_id', 'id'])->on('locations')->restrictOnDelete();
            $table->foreign(['tenant_id', 'actor_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
            $table->foreign(['tenant_id', 'credit_approval_id'])->references(['tenant_id', 'id'])->on('credit_approvals')->restrictOnDelete();
        });

        Schema::create('handover_lines', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->bigInteger('handover_id');
            $table->bigInteger('order_id');
            $table->bigInteger('bag_content_id');
            $table->decimal('quantity', 12, 3);
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'handover_id', 'bag_content_id']);
            $table->foreign(['tenant_id', 'order_id', 'handover_id'])->references(['tenant_id', 'order_id', 'id'])->on('handovers')->restrictOnDelete();
            $table->foreign(['tenant_id', 'bag_content_id'])->references(['tenant_id', 'id'])->on('bag_contents')->restrictOnDelete();
        });

        Schema::create('custody_events', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->bigInteger('bag_id');
            $table->string('event_type', 40);
            $table->bigInteger('from_location_id')->nullable();
            $table->bigInteger('to_location_id')->nullable();
            $table->bigInteger('handover_line_id')->nullable();
            $table->bigInteger('actor_membership_id');
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'bag_id', 'created_at']);
            $table->foreign(['tenant_id', 'bag_id'])->references(['tenant_id', 'id'])->on('bags')->restrictOnDelete();
            $table->foreign(['tenant_id', 'from_location_id'])->references(['tenant_id', 'id'])->on('locations')->restrictOnDelete();
            $table->foreign(['tenant_id', 'to_location_id'])->references(['tenant_id', 'id'])->on('locations')->restrictOnDelete();
            $table->foreign(['tenant_id', 'handover_line_id'])->references(['tenant_id', 'id'])->on('handover_lines')->restrictOnDelete();
            $table->foreign(['tenant_id', 'actor_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE handovers ADD CONSTRAINT handovers_verification_check CHECK (verification_method IN ('receipt', 'otp', 'verified_staff_exception'))");
        DB::statement('ALTER TABLE handover_lines ADD CONSTRAINT handover_lines_quantity_check CHECK (quantity > 0)');
        DB::statement("ALTER TABLE custody_events ADD CONSTRAINT custody_events_type_check CHECK (event_type IN ('received', 'dispatched', 'transfer_received', 'handed_over', 'corrected'))");

        foreach (['handovers', 'handover_lines', 'custody_events'] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY {$table}_tenant_scope ON {$table} FOR ALL USING (tenant_id = app_current_tenant_id()) WITH CHECK (tenant_id = app_current_tenant_id())");
        }

        DB::statement('REVOKE DELETE ON handovers, handover_lines, custody_events FROM netclean_runtime');
        DB::statement('REVOKE UPDATE, DELETE ON handover_lines, custody_events FROM netclean_runtime');
        DB::statement('GRANT SELECT, INSERT, UPDATE ON handovers TO netclean_runtime');
        DB::statement('GRANT SELECT, INSERT ON handover_lines, custody_events TO netclean_runtime');
    }

    public function down(): void
    {
        Schema::dropIfExists('custody_events');
        Schema::dropIfExists('handover_lines');
        Schema::dropIfExists('handovers');
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
