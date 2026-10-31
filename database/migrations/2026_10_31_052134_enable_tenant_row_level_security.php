<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TENANT_TABLES = [
        'tenant_memberships',
        'branches',
        'locations',
        'roles',
        'membership_roles',
        'role_permissions',
        'branch_assignments',
        'location_assignments',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("CREATE OR REPLACE FUNCTION app_current_tenant_id() RETURNS bigint LANGUAGE sql STABLE PARALLEL SAFE AS $$ SELECT NULLIF(current_setting('app.tenant_id', true), '')::bigint $$");
        DB::statement("CREATE OR REPLACE FUNCTION app_current_user_id() RETURNS bigint LANGUAGE sql STABLE PARALLEL SAFE AS $$ SELECT NULLIF(current_setting('app.user_id', true), '')::bigint $$");
        DB::statement("CREATE OR REPLACE FUNCTION app_identity_bootstrap() RETURNS boolean LANGUAGE sql STABLE PARALLEL SAFE AS $$ SELECT COALESCE(current_setting('app.identity_bootstrap', true), 'off') = 'on' $$");

        foreach (self::TENANT_TABLES as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY {$table}_tenant_scope ON {$table} FOR ALL USING (tenant_id = app_current_tenant_id()) WITH CHECK (tenant_id = app_current_tenant_id())");
        }

        DB::statement('ALTER TABLE tenants ENABLE ROW LEVEL SECURITY');
        DB::statement('CREATE POLICY tenants_tenant_scope ON tenants FOR ALL USING (id = app_current_tenant_id()) WITH CHECK (id = app_current_tenant_id())');
        DB::statement("CREATE POLICY tenants_identity_scope ON tenants FOR SELECT USING (app_identity_bootstrap() AND tenants.status = 'active' AND EXISTS (SELECT 1 FROM tenant_memberships tm WHERE tm.tenant_id = tenants.id AND tm.user_id = app_current_user_id() AND tm.status = 'active'))");

        DB::statement("CREATE POLICY tenant_memberships_identity_scope ON tenant_memberships FOR SELECT USING (app_identity_bootstrap() AND user_id = app_current_user_id() AND status = 'active')");
        DB::statement("CREATE POLICY branches_identity_scope ON branches FOR SELECT USING (app_identity_bootstrap() AND EXISTS (SELECT 1 FROM tenant_memberships tm JOIN tenants t ON t.id = tm.tenant_id WHERE tm.tenant_id = branches.tenant_id AND tm.user_id = app_current_user_id() AND tm.status = 'active' AND t.status = 'active'))");
        DB::statement("CREATE POLICY locations_identity_scope ON locations FOR SELECT USING (app_identity_bootstrap() AND EXISTS (SELECT 1 FROM tenant_memberships tm JOIN tenants t ON t.id = tm.tenant_id WHERE tm.tenant_id = locations.tenant_id AND tm.user_id = app_current_user_id() AND tm.status = 'active' AND t.status = 'active'))");
        DB::statement("CREATE POLICY branch_assignments_identity_scope ON branch_assignments FOR SELECT USING (app_identity_bootstrap() AND EXISTS (SELECT 1 FROM tenant_memberships tm JOIN tenants t ON t.id = tm.tenant_id WHERE tm.id = branch_assignments.membership_id AND tm.user_id = app_current_user_id() AND tm.status = 'active' AND t.status = 'active'))");
        DB::statement("CREATE POLICY location_assignments_identity_scope ON location_assignments FOR SELECT USING (app_identity_bootstrap() AND EXISTS (SELECT 1 FROM tenant_memberships tm JOIN tenants t ON t.id = tm.tenant_id WHERE tm.id = location_assignments.membership_id AND tm.user_id = app_current_user_id() AND tm.status = 'active' AND t.status = 'active'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $policies = [
            'tenant_memberships' => ['tenant_memberships_tenant_scope', 'tenant_memberships_identity_scope'],
            'branches' => ['branches_tenant_scope', 'branches_identity_scope'],
            'locations' => ['locations_tenant_scope', 'locations_identity_scope'],
            'roles' => ['roles_tenant_scope'],
            'membership_roles' => ['membership_roles_tenant_scope'],
            'role_permissions' => ['role_permissions_tenant_scope'],
            'branch_assignments' => ['branch_assignments_tenant_scope', 'branch_assignments_identity_scope'],
            'location_assignments' => ['location_assignments_tenant_scope', 'location_assignments_identity_scope'],
            'tenants' => ['tenants_tenant_scope', 'tenants_identity_scope'],
        ];

        foreach ($policies as $table => $names) {
            foreach ($names as $name) {
                DB::statement("DROP POLICY IF EXISTS {$name} ON {$table}");
            }
        }

        foreach (array_keys($policies) as $table) {
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }

        DB::statement('DROP FUNCTION IF EXISTS app_identity_bootstrap()');
        DB::statement('DROP FUNCTION IF EXISTS app_current_user_id()');
        DB::statement('DROP FUNCTION IF EXISTS app_current_tenant_id()');
    }
};
