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
        Schema::create('tenant_memberships', function (Blueprint $table): void {
            $this->metadata($table, true);
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 24)->default('invited');
            $table->bigInteger('permission_version')->default(1);
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unique(['tenant_id', 'user_id']);
        });

        Schema::create('roles', function (Blueprint $table): void {
            $this->metadata($table, true);
            $table->string('code', 64);
            $table->string('name', 100);
            $table->boolean('is_system')->default(false);
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $this->metadata($table);
            $table->string('code', 100)->unique();
            $table->text('description');
        });

        $parents = ['branch_assignments' => ['branch', 'branches'], 'location_assignments' => ['location', 'locations'], 'membership_roles' => ['role', 'roles']];

        foreach ($parents as $name => [$column, $parent]) {
            Schema::create($name, function (Blueprint $table) use ($column, $parent): void {
                $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
                $table->bigInteger('membership_id');
                $table->bigInteger($column.'_id');
                $table->primary(['tenant_id', 'membership_id', $column.'_id']);
                $table->index(['tenant_id', $column.'_id']);
                $table->foreign(['tenant_id', 'membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
                $table->foreign(['tenant_id', $column.'_id'])->references(['tenant_id', 'id'])->on($parent)->restrictOnDelete();
            });
        }

        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->bigInteger('role_id');
            $table->foreignId('permission_id')->constrained('permissions')->restrictOnDelete();
            $table->primary(['tenant_id', 'role_id', 'permission_id']);
            $table->index('permission_id');
            $table->foreign(['tenant_id', 'role_id'])->references(['tenant_id', 'id'])->on('roles')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE tenant_memberships ADD CONSTRAINT memberships_status_check CHECK (status IN ('invited', 'active', 'disabled')), ADD CONSTRAINT memberships_permission_version_check CHECK (permission_version > 0)");

        foreach (['tenant_memberships', 'roles', 'permissions'] as $name) {
            DB::statement("ALTER TABLE {$name} ADD CONSTRAINT {$name}_version_check CHECK (version > 0)");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['role_permissions', 'membership_roles', 'location_assignments', 'branch_assignments', 'permissions', 'roles', 'tenant_memberships'] as $name) {
            Schema::dropIfExists($name);
        }
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
