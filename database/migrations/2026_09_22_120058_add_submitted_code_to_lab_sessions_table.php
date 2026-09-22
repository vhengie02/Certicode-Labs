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
        Schema::table('lab_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('lab_sessions', 'submitted_code')) {
                $table->longText('submitted_code')->nullable()->after('completed_tasks');
            }
            if (!Schema::hasColumn('lab_sessions', 'submitted_files')) {
                $table->json('submitted_files')->nullable()->after('submitted_code');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lab_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('lab_sessions', 'submitted_files')) {
                $table->dropColumn('submitted_files');
            }
            if (Schema::hasColumn('lab_sessions', 'submitted_code')) {
                $table->dropColumn('submitted_code');
            }
        });
    }
};
