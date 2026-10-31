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
        Schema::table('services', function (Blueprint $table): void {
            $table->string('sla_mode', 16)->default('working_days');
            $table->integer('regular_turnaround')->default(2);
            $table->integer('express_turnaround')->default(1);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE services
                ADD CONSTRAINT services_sla_mode_check CHECK (sla_mode IN ('working_days', 'elapsed_hours')),
                ADD CONSTRAINT services_turnaround_positive_check CHECK (regular_turnaround > 0 AND express_turnaround > 0),
                ADD CONSTRAINT services_turnaround_range_check CHECK (
                    (sla_mode = 'working_days' AND regular_turnaround <= 365 AND express_turnaround <= 365)
                    OR (sla_mode = 'elapsed_hours' AND regular_turnaround <= 8760 AND express_turnaround <= 8760)
                )
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE services DROP CONSTRAINT IF EXISTS services_turnaround_range_check, DROP CONSTRAINT IF EXISTS services_turnaround_positive_check, DROP CONSTRAINT IF EXISTS services_sla_mode_check');

        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn(['sla_mode', 'regular_turnaround', 'express_turnaround']);
        });
    }
};
