<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('reference', 120)->nullable()->after('method');
        });

        DB::statement('ALTER TABLE payments DROP CONSTRAINT payments_method_check');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_method_check CHECK (method IN ('cash', 'bank_manual', 'qris_manual'))");
    }

    public function down(): void
    {
        DB::table('payments')->where('method', 'qris_manual')->update(['method' => 'bank_manual']);

        DB::statement('ALTER TABLE payments DROP CONSTRAINT payments_method_check');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_method_check CHECK (method IN ('cash', 'bank_manual'))");

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('reference');
        });
    }
};
