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
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        Schema::create('price_books', function (Blueprint $table): void {
            $this->metadata($table);
            $table->string('name', 120);
            $table->bigInteger('branch_id')->nullable();
            $table->bigInteger('customer_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->index(['tenant_id', 'is_active']);
            $table->foreign(['tenant_id', 'branch_id'])
                ->references(['tenant_id', 'id'])
                ->on('branches')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'customer_id'])
                ->references(['tenant_id', 'id'])
                ->on('customers')
                ->restrictOnDelete();
        });

        Schema::create('price_entries', function (Blueprint $table): void {
            $this->metadata($table);
            $table->bigInteger('price_book_id');
            $table->bigInteger('service_id');
            $table->string('tier', 24);
            $table->bigInteger('unit_price_rupiah');
            $table->timestampTz('effective_from');
            $table->timestampTz('effective_until')->nullable();
            $table->jsonb('rules')->default(DB::raw("'{}'::jsonb"));
            $table->index(['tenant_id', 'price_book_id', 'service_id', 'tier', 'effective_from']);
            $table->foreign(['tenant_id', 'price_book_id'])
                ->references(['tenant_id', 'id'])
                ->on('price_books')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'service_id'])
                ->references(['tenant_id', 'id'])
                ->on('services')
                ->restrictOnDelete();
        });

        DB::statement('CREATE UNIQUE INDEX price_books_one_active_tenant_scope ON price_books (tenant_id) WHERE branch_id IS NULL AND customer_id IS NULL AND is_active = true');
        DB::statement('CREATE UNIQUE INDEX price_books_one_active_branch_scope ON price_books (tenant_id, branch_id) WHERE branch_id IS NOT NULL AND customer_id IS NULL AND is_active = true');

        DB::statement("ALTER TABLE price_entries ADD CONSTRAINT price_entries_tier_check CHECK (tier IN ('regular', 'express')), ADD CONSTRAINT price_entries_price_check CHECK (unit_price_rupiah >= 0), ADD CONSTRAINT price_entries_period_check CHECK (effective_until IS NULL OR effective_until > effective_from)");
        DB::statement(<<<'SQL'
            ALTER TABLE price_entries ADD CONSTRAINT price_entries_no_overlap EXCLUDE USING gist (
                tenant_id WITH =,
                price_book_id WITH =,
                service_id WITH =,
                tier WITH =,
                tstzrange(effective_from, effective_until, '[)') WITH &&
            )
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_price_entry_definition_update() RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.tenant_id IS DISTINCT FROM OLD.tenant_id
                    OR NEW.public_id IS DISTINCT FROM OLD.public_id
                    OR NEW.price_book_id IS DISTINCT FROM OLD.price_book_id
                    OR NEW.service_id IS DISTINCT FROM OLD.service_id
                    OR NEW.tier IS DISTINCT FROM OLD.tier
                    OR NEW.unit_price_rupiah IS DISTINCT FROM OLD.unit_price_rupiah
                    OR NEW.effective_from IS DISTINCT FROM OLD.effective_from
                    OR NEW.rules IS DISTINCT FROM OLD.rules THEN
                    RAISE EXCEPTION 'Price entry definitions are immutable; only effective_until may be changed.' USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $$
        SQL);
        DB::statement('CREATE TRIGGER price_entries_immutable_definition BEFORE UPDATE ON price_entries FOR EACH ROW EXECUTE FUNCTION prevent_price_entry_definition_update()');

        foreach (['price_books', 'price_entries'] as $name) {
            DB::statement("ALTER TABLE {$name} ENABLE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY {$name}_tenant_scope ON {$name} FOR ALL USING (tenant_id = app_current_tenant_id()) WITH CHECK (tenant_id = app_current_tenant_id())");
        }

        DB::statement('REVOKE DELETE ON price_books, price_entries FROM netclean_runtime');
        DB::statement('GRANT SELECT, INSERT, UPDATE ON price_books, price_entries TO netclean_runtime');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_entries');
        Schema::dropIfExists('price_books');
        DB::statement('DROP FUNCTION IF EXISTS prevent_price_entry_definition_update()');
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
