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
        Schema::table('laboratories', function (Blueprint $table) {
            $table->string('availability_mode')->default('open'); // 'open' or 'live'
            $table->integer('live_duration_minutes')->nullable(); // fixed duration window for live mode
            $table->string('live_status')->default('not_started'); // 'not_started', 'active', 'closed'
            $table->timestamp('live_started_at')->nullable(); // manual open trigger timestamp
            $table->integer('live_elapsed_seconds')->default(0); // tracks accumulated elapsed seconds for one-shot reopen calculations
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('laboratories', function (Blueprint $table) {
            $table->dropColumn([
                'availability_mode',
                'live_duration_minutes',
                'live_status',
                'live_started_at',
                'live_elapsed_seconds',
            ]);
        });
    }
};
