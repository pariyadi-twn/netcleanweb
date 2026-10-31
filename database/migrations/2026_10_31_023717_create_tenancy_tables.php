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
        Schema::create('tenants', function (Blueprint $table): void {
            $this->metadata($table);
            $table->string('name', 150);
            $table->string('slug', 80)->unique();
            $table->string('timezone', 64)->default('Asia/Jakarta');
            $table->string('status', 24)->default('provisioning');
            $table->bigInteger('data_generation')->default(1);
        });

        Schema::create('branches', function (Blueprint $table): void {
            $this->metadata($table, true);
            $table->string('code', 20);
            $table->string('name', 150);
            $table->string('timezone', 64)->default('Asia/Jakarta');
            $table->text('address');
            $table->string('phone', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('locations', function (Blueprint $table): void {
            $this->metadata($table, true);
            $table->bigInteger('branch_id')->nullable();
            $table->string('code', 40);
            $table->string('type', 24);
            $table->bigInteger('parent_location_id')->nullable();
            $table->string('name', 150);
            $table->boolean('is_active')->default(true);
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'branch_id']);
            $table->index(['tenant_id', 'parent_location_id']);
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches')->restrictOnDelete();
            $table->foreign(['tenant_id', 'parent_location_id'])->references(['tenant_id', 'id'])->on('locations')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE tenants ADD CONSTRAINT tenants_status_check CHECK (status IN ('provisioning', 'active', 'suspended', 'closed')), ADD CONSTRAINT tenants_generation_check CHECK (data_generation > 0)");
        DB::statement("ALTER TABLE locations ADD CONSTRAINT locations_type_check CHECK (type IN ('outlet', 'plant', 'warehouse', 'rack')), ADD CONSTRAINT locations_parent_check CHECK (parent_location_id IS NULL OR parent_location_id <> id)");

        foreach (['tenants', 'branches', 'locations'] as $name) {
            DB::statement("ALTER TABLE {$name} ADD CONSTRAINT {$name}_version_check CHECK (version > 0)");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('tenants');
    }

    private function metadata(Blueprint $table, bool $tenantScoped = false): void
    {
        $table->id()->generatedAs();
        $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
        $table->timestampTz('created_at')->useCurrent();
        $table->timestampTz('updated_at')->useCurrent();
        $table->bigInteger('version')->default(1);

        if ($tenantScoped) {
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->unique(['tenant_id', 'id']);
        }
    }
};
