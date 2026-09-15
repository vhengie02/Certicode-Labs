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
        Schema::table('school_classes', function (Blueprint $table) {
            $table->integer('passing_threshold')->default(75);
            $table->timestamp('scheduled_end_date')->nullable();
            $table->string('status')->default('active'); // active, completed, closed
        });

        Schema::table('anomalies', function (Blueprint $table) {
            $table->json('metadata')->nullable();
            $table->string('image_path')->nullable();
        });

        Schema::table('lab_sessions', function (Blueprint $table) {
            $table->integer('wpm')->default(0);
            $table->integer('keystroke_count')->default(0);
            $table->integer('focus_lost_count')->default(0);
            $table->integer('paste_anomaly_count')->default(0);
            $table->timestamp('closed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('school_classes', function (Blueprint $table) {
            $table->dropColumn(['passing_threshold', 'scheduled_end_date', 'status']);
        });

        Schema::table('anomalies', function (Blueprint $table) {
            $table->dropColumn(['metadata', 'image_path']);
        });

        Schema::table('lab_sessions', function (Blueprint $table) {
            $table->dropColumn(['wpm', 'keystroke_count', 'focus_lost_count', 'paste_anomaly_count', 'closed_at']);
        });
    }
};
