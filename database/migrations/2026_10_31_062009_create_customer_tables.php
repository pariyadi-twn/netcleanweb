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
        Schema::create('customers', function (Blueprint $table): void {
            $this->metadata($table);
            $table->string('name', 150);
            $table->string('normalized_phone', 32)->nullable();
            $table->string('email', 254)->nullable();
            $table->string('external_ref', 100)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->index(['tenant_id', 'normalized_phone']);
            $table->index(['tenant_id', 'name']);
        });

        Schema::create('customer_addresses', function (Blueprint $table): void {
            $this->metadata($table);
            $table->bigInteger('customer_id');
            $table->string('label', 60);
            $table->text('address');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_default')->default(false);
            $table->index(['tenant_id', 'customer_id']);
            $table->foreign(['tenant_id', 'customer_id'])->references(['tenant_id', 'id'])->on('customers')->restrictOnDelete();
        });

        DB::statement('CREATE UNIQUE INDEX customer_addresses_one_default ON customer_addresses (tenant_id, customer_id) WHERE is_default = true');
        DB::statement('ALTER TABLE customer_addresses ADD CONSTRAINT customer_addresses_latitude_check CHECK (latitude IS NULL OR (latitude >= -90 AND latitude <= 90)), ADD CONSTRAINT customer_addresses_longitude_check CHECK (longitude IS NULL OR (longitude >= -180 AND longitude <= 180))');

        foreach (['customers', 'customer_addresses'] as $name) {
            DB::statement("ALTER TABLE {$name} ENABLE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY {$name}_tenant_scope ON {$name} FOR ALL USING (tenant_id = app_current_tenant_id()) WITH CHECK (tenant_id = app_current_tenant_id())");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customers');
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
