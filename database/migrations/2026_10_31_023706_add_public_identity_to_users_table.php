<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('public_id')->nullable()->unique();
            $table->string('status', 24)->default('active');
            $table->text('mfa_secret_ref')->nullable();
            $table->bigInteger('version')->default(1);
        });

        DB::table('users')->whereNull('public_id')->orderBy('id')->chunkById(500, function (Collection $users): void {
            foreach ($users as $user) {
                DB::table('users')->where('id', $user->id)->update(['public_id' => (string) Str::uuid()]);
            }
        });

        DB::statement('ALTER TABLE users ALTER COLUMN public_id SET DEFAULT gen_random_uuid(), ALTER COLUMN public_id SET NOT NULL');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('active', 'disabled')), ADD CONSTRAINT users_version_check CHECK (version > 0)");

        foreach (['created_at', 'updated_at', 'email_verified_at'] as $column) {
            DB::statement("ALTER TABLE users ALTER COLUMN {$column} TYPE timestamptz USING {$column} AT TIME ZONE 'UTC'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_status_check, DROP CONSTRAINT users_version_check');

        foreach (['created_at', 'updated_at', 'email_verified_at'] as $column) {
            DB::statement("ALTER TABLE users ALTER COLUMN {$column} TYPE timestamp(0) USING {$column} AT TIME ZONE 'UTC'");
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['public_id']);
            $table->dropColumn(['public_id', 'status', 'mfa_secret_ref', 'version']);
        });
    }
};
