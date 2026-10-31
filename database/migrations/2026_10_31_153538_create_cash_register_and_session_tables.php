<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_registers', function (Blueprint $table): void {
            $this->metadata($table);
            $table->bigInteger('branch_id');
            $table->string('code', 40);
            $table->boolean('is_active')->default(true);
            $table->unique(['tenant_id', 'branch_id', 'code']);
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches')->restrictOnDelete();
        });

        Schema::create('cash_sessions', function (Blueprint $table): void {
            $this->metadata($table);
            $table->bigInteger('cash_register_id');
            $table->bigInteger('cashier_membership_id');
            $table->timestampTz('opened_at');
            $table->timestampTz('closed_at')->nullable();
            $table->bigInteger('opening_rupiah');
            $table->bigInteger('counted_closing_rupiah')->nullable();
            $table->string('status', 24)->default('open');
            $table->text('discrepancy_reason')->nullable();
            $table->index(['tenant_id', 'cash_register_id', 'status']);
            $table->foreign(['tenant_id', 'cash_register_id'])->references(['tenant_id', 'id'])->on('cash_registers')->restrictOnDelete();
            $table->foreign(['tenant_id', 'cashier_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
        });

        Schema::create('cash_entries', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->bigInteger('cash_session_id');
            $table->string('kind', 32);
            $table->bigInteger('amount_rupiah');
            $table->string('reference_type', 32)->nullable();
            $table->bigInteger('reference_id')->nullable();
            $table->bigInteger('reverses_entry_id')->nullable();
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'cash_session_id']);
            $table->foreign(['tenant_id', 'cash_session_id'])->references(['tenant_id', 'id'])->on('cash_sessions')->restrictOnDelete();
            $table->foreign(['tenant_id', 'reverses_entry_id'])->references(['tenant_id', 'id'])->on('cash_entries')->restrictOnDelete();
        });

        Schema::create('cash_session_events', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->bigInteger('cash_session_id');
            $table->string('event_type', 24);
            $table->bigInteger('actor_membership_id');
            $table->jsonb('payload');
            $table->text('reason')->nullable();
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'cash_session_id', 'created_at']);
            $table->foreign(['tenant_id', 'cash_session_id'])->references(['tenant_id', 'id'])->on('cash_sessions')->restrictOnDelete();
            $table->foreign(['tenant_id', 'actor_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE cash_sessions ADD CONSTRAINT cash_sessions_status_check CHECK (status IN ('open', 'closed')), ADD CONSTRAINT cash_sessions_opening_check CHECK (opening_rupiah >= 0), ADD CONSTRAINT cash_sessions_closing_check CHECK (counted_closing_rupiah IS NULL OR counted_closing_rupiah >= 0), ADD CONSTRAINT cash_sessions_terminal_check CHECK ((status = 'open' AND closed_at IS NULL AND counted_closing_rupiah IS NULL) OR (status = 'closed' AND closed_at IS NOT NULL AND counted_closing_rupiah IS NOT NULL))");
        DB::statement('CREATE UNIQUE INDEX cash_sessions_one_open_register ON cash_sessions (tenant_id, cash_register_id) WHERE status = \'open\'');
        DB::statement("ALTER TABLE cash_entries ADD CONSTRAINT cash_entries_kind_check CHECK (kind IN ('payment_in', 'refund_out', 'expense_out', 'transfer_in', 'transfer_out', 'correction'))");
        DB::statement('CREATE UNIQUE INDEX cash_entries_source_unique ON cash_entries (tenant_id, kind, reference_type, reference_id) WHERE reference_id IS NOT NULL');
        DB::statement("ALTER TABLE cash_session_events ADD CONSTRAINT cash_session_events_type_check CHECK (event_type IN ('opened', 'closed', 'reopened'))");

        foreach (['cash_registers', 'cash_sessions', 'cash_entries', 'cash_session_events'] as $name) {
            DB::statement("ALTER TABLE {$name} ENABLE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY {$name}_tenant_scope ON {$name} FOR ALL USING (tenant_id = app_current_tenant_id()) WITH CHECK (tenant_id = app_current_tenant_id())");
        }

        DB::statement('REVOKE DELETE ON cash_registers, cash_sessions, cash_entries, cash_session_events FROM netclean_runtime');
        DB::statement('REVOKE UPDATE, DELETE ON cash_entries, cash_session_events FROM netclean_runtime');
        DB::statement('GRANT SELECT, INSERT, UPDATE ON cash_registers, cash_sessions TO netclean_runtime');
        DB::statement('GRANT SELECT, INSERT ON cash_entries, cash_session_events TO netclean_runtime');
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_session_events');
        Schema::dropIfExists('cash_entries');
        Schema::dropIfExists('cash_sessions');
        Schema::dropIfExists('cash_registers');
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
