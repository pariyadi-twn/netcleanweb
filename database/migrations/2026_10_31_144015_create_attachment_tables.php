<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table): void {
            $this->metadata($table);
            $table->bigInteger('uploaded_by_membership_id');
            $table->text('object_key')->unique();
            $table->text('thumbnail_key')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->bigInteger('size_bytes')->default(0);
            $table->char('expected_sha256', 64)->nullable();
            $table->char('sha256', 64)->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->string('original_name', 255)->nullable();
            $table->string('state', 24)->default('pending');
            $table->index(['tenant_id', 'state', 'created_at']);
            $table->foreign(['tenant_id', 'uploaded_by_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
        });

        Schema::create('order_attachments', function (Blueprint $table): void {
            $table->bigInteger('tenant_id');
            $table->bigInteger('order_id');
            $table->bigInteger('attachment_id');
            $table->string('purpose', 32);
            $table->timestampTz('created_at')->useCurrent();
            $table->primary(['tenant_id', 'order_id', 'attachment_id']);
            $table->index(['tenant_id', 'attachment_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'order_id'])->references(['tenant_id', 'id'])->on('orders')->restrictOnDelete();
            $table->foreign(['tenant_id', 'attachment_id'])->references(['tenant_id', 'id'])->on('attachments')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE attachments ADD CONSTRAINT attachments_state_check CHECK (state IN ('pending', 'quarantined', 'ready', 'rejected')), ADD CONSTRAINT attachments_size_check CHECK (size_bytes >= 0 AND size_bytes <= 5242880), ADD CONSTRAINT attachments_dimension_check CHECK ((width IS NULL OR width > 0) AND (height IS NULL OR height > 0))");
        DB::statement("ALTER TABLE order_attachments ADD CONSTRAINT order_attachments_purpose_check CHECK (purpose IN ('initial_condition', 'receipt', 'handover', 'complaint', 'delivery'))");

        foreach (['attachments', 'order_attachments'] as $name) {
            DB::statement("ALTER TABLE {$name} ENABLE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY {$name}_tenant_scope ON {$name} FOR ALL USING (tenant_id = app_current_tenant_id()) WITH CHECK (tenant_id = app_current_tenant_id())");
        }

        DB::statement('REVOKE DELETE ON attachments FROM netclean_runtime');
        DB::statement('REVOKE UPDATE, DELETE ON order_attachments FROM netclean_runtime');
        DB::statement('GRANT SELECT, INSERT, UPDATE ON attachments TO netclean_runtime');
        DB::statement('GRANT SELECT, INSERT ON order_attachments TO netclean_runtime');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_attachments');
        Schema::dropIfExists('attachments');
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
