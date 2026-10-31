<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE order_events DROP CONSTRAINT order_events_type_check');
        DB::statement("ALTER TABLE order_events ADD CONSTRAINT order_events_type_check CHECK (event_type IN ('accepted', 'revised', 'rescheduled', 'cancelled', 'readiness_changed'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE order_events DROP CONSTRAINT order_events_type_check');
        DB::statement("ALTER TABLE order_events ADD CONSTRAINT order_events_type_check CHECK (event_type IN ('accepted', 'revised', 'rescheduled', 'cancelled'))");
    }
};
