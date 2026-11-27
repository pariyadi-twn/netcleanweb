<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('production_mode', 16)->default('detailed');
            $table->boolean('require_payment_before_handover')->default(false);
        });

        DB::statement("ALTER TABLE tenants ADD CONSTRAINT tenants_production_mode_check CHECK (production_mode IN ('simple', 'detailed'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tenants DROP CONSTRAINT IF EXISTS tenants_production_mode_check');

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn(['production_mode', 'require_payment_before_handover']);
        });
    }
};
