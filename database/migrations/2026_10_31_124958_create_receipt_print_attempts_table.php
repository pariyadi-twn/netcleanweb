<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_print_attempts', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->bigInteger('order_id');
            $table->bigInteger('actor_membership_id');
            $table->string('status', 16);
            $table->char('receipt_hash', 64);
            $table->string('error_code', 80)->nullable();
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'order_id', 'created_at']);
            $table->foreign(['tenant_id', 'order_id'])->references(['tenant_id', 'id'])->on('orders')->restrictOnDelete();
            $table->foreign(['tenant_id', 'actor_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE receipt_print_attempts ADD CONSTRAINT receipt_print_attempts_status_check CHECK (status IN ('printed', 'failed')), ADD CONSTRAINT receipt_print_attempts_error_check CHECK ((status = 'printed' AND error_code IS NULL) OR (status = 'failed' AND error_code IS NOT NULL))");
        DB::statement('ALTER TABLE receipt_print_attempts ENABLE ROW LEVEL SECURITY');
        DB::statement('CREATE POLICY receipt_print_attempts_tenant_scope ON receipt_print_attempts FOR ALL USING (tenant_id = app_current_tenant_id()) WITH CHECK (tenant_id = app_current_tenant_id())');
        DB::statement('REVOKE UPDATE, DELETE ON receipt_print_attempts FROM netclean_runtime');
        DB::statement('GRANT SELECT, INSERT ON receipt_print_attempts TO netclean_runtime');
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_print_attempts');
    }
};
