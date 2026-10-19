<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The hot telemetry lookups filter one session's rows by event type (latest heartbeat,
     * camera check, recent switches), and the daily prune filters by event type and age.
     * The single-column event_type index served neither well. Repeat checks on flags filter
     * one session's anomalies by type and age.
     */
    public function up(): void
    {
        // IF EXISTS: a database whose schema wasn't built by these migrations may not have it
        DB::statement('DROP INDEX IF EXISTS idx_telemetry_event_type');

        Schema::table('telemetry_logs', function (Blueprint $table) {
            $table->index(['lab_session_id', 'event_type', 'created_at'], 'idx_telemetry_session_event_created');
            $table->index(['event_type', 'created_at'], 'idx_telemetry_event_created');
        });

        Schema::table('anomalies', function (Blueprint $table) {
            $table->index(['lab_session_id', 'type', 'created_at'], 'idx_anomalies_session_type_created');
        });
    }

    public function down(): void
    {
        Schema::table('anomalies', function (Blueprint $table) {
            $table->dropIndex('idx_anomalies_session_type_created');
        });

        Schema::table('telemetry_logs', function (Blueprint $table) {
            $table->dropIndex('idx_telemetry_event_created');
            $table->dropIndex('idx_telemetry_session_event_created');
            $table->index(['event_type'], 'idx_telemetry_event_type');
        });
    }
};
