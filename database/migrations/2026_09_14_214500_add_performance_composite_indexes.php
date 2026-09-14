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
        // 1. class_student performance composite indexes
        Schema::table('class_student', function (Blueprint $table) {
            $table->index(['student_id', 'status'], 'idx_class_student_student_status');
            $table->index(['class_id', 'status'], 'idx_class_student_class_status');
        });

        // 2. lab_sessions performance indexes
        Schema::table('lab_sessions', function (Blueprint $table) {
            $table->index(['user_id', 'status'], 'idx_lab_sessions_user_status');
            $table->index(['lab_id', 'status'], 'idx_lab_sessions_lab_status');
            $table->index(['user_id', 'lab_id', 'status'], 'idx_lab_sessions_user_lab_status');
        });

        // 3. modules ordering and hierarchy indexes
        Schema::table('modules', function (Blueprint $table) {
            $table->index(['class_id', 'order_index'], 'idx_modules_class_order');
            $table->index(['parent_id', 'order_index'], 'idx_modules_parent_order');
        });

        // 4. laboratories module index
        Schema::table('laboratories', function (Blueprint $table) {
            $table->index(['module_id'], 'idx_laboratories_module_id');
        });

        // 5. telemetry_logs timeline retrieval indexes
        Schema::table('telemetry_logs', function (Blueprint $table) {
            $table->index(['lab_session_id', 'created_at'], 'idx_telemetry_session_created');
            $table->index(['event_type'], 'idx_telemetry_event_type');
        });

        // 6. anomalies resolution status and severity indexes
        Schema::table('anomalies', function (Blueprint $table) {
            $table->index(['lab_session_id', 'resolved'], 'idx_anomalies_session_resolved');
            $table->index(['resolved', 'created_at'], 'idx_anomalies_resolved_created');
        });

        // 7. notifications unread query composite index
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'idx_notifications_notifiable_unread');
        });

        // 8. certificates user and class index
        Schema::table('certificates', function (Blueprint $table) {
            $table->index(['user_id', 'class_id'], 'idx_certificates_user_class');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropIndex('idx_certificates_user_class');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('idx_notifications_notifiable_unread');
        });

        Schema::table('anomalies', function (Blueprint $table) {
            $table->dropIndex('idx_anomalies_session_resolved');
            $table->dropIndex('idx_anomalies_resolved_created');
        });

        Schema::table('telemetry_logs', function (Blueprint $table) {
            $table->dropIndex('idx_telemetry_session_created');
            $table->dropIndex('idx_telemetry_event_type');
        });

        Schema::table('laboratories', function (Blueprint $table) {
            $table->dropIndex('idx_laboratories_module_id');
        });

        Schema::table('modules', function (Blueprint $table) {
            $table->dropIndex('idx_modules_class_order');
            $table->dropIndex('idx_modules_parent_order');
        });

        Schema::table('lab_sessions', function (Blueprint $table) {
            $table->dropIndex('idx_lab_sessions_user_status');
            $table->dropIndex('idx_lab_sessions_lab_status');
            $table->dropIndex('idx_lab_sessions_user_lab_status');
        });

        Schema::table('class_student', function (Blueprint $table) {
            $table->dropIndex('idx_class_student_student_status');
            $table->dropIndex('idx_class_student_class_status');
        });
    }
};
