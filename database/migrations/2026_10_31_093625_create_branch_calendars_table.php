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
        Schema::create('branch_calendars', function (Blueprint $table): void {
            $this->metadata($table);
            $table->bigInteger('branch_id');
            $table->string('kind', 24);
            $table->date('local_date')->nullable();
            $table->smallInteger('weekday')->nullable();
            $table->jsonb('rules')->default(DB::raw("'{}'::jsonb"));
            $table->index(['tenant_id', 'branch_id', 'kind']);
            $table->foreign(['tenant_id', 'branch_id'])
                ->references(['tenant_id', 'id'])
                ->on('branches')
                ->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE branch_calendars ADD CONSTRAINT branch_calendars_shape_check CHECK (
                (kind = 'weekly_hours' AND weekday BETWEEN 0 AND 6 AND local_date IS NULL)
                OR (kind = 'holiday' AND local_date IS NOT NULL AND weekday IS NULL)
                OR (kind = 'cutoff' AND local_date IS NULL AND weekday IS NULL)
            )
        SQL);
        DB::statement('CREATE UNIQUE INDEX branch_calendars_weekly_unique ON branch_calendars (tenant_id, branch_id, weekday) WHERE kind = \'weekly_hours\'');
        DB::statement('CREATE UNIQUE INDEX branch_calendars_holiday_unique ON branch_calendars (tenant_id, branch_id, local_date) WHERE kind = \'holiday\'');
        DB::statement('CREATE UNIQUE INDEX branch_calendars_cutoff_unique ON branch_calendars (tenant_id, branch_id) WHERE kind = \'cutoff\'');

        DB::statement('ALTER TABLE branch_calendars ENABLE ROW LEVEL SECURITY');
        DB::statement('CREATE POLICY branch_calendars_tenant_scope ON branch_calendars FOR ALL USING (tenant_id = app_current_tenant_id()) WITH CHECK (tenant_id = app_current_tenant_id())');
        DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON branch_calendars TO netclean_runtime');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch_calendars');
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
