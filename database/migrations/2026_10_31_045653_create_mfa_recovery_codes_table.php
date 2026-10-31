<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mfa_recovery_codes', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->char('code_hash', 64);
            $table->timestampTz('used_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->unique(['user_id', 'code_hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mfa_recovery_codes');
    }
};
