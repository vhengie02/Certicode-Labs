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
            if (!Schema::hasColumn('lab_sessions', 'ai_grade_summary')) {
                $table->json('ai_grade_summary')->nullable()->after('submitted_files');
            }
            if (!Schema::hasColumn('lab_sessions', 'instructor_grade_override')) {
                $table->float('instructor_grade_override')->nullable()->after('ai_grade_summary');
            }
            if (!Schema::hasColumn('lab_sessions', 'instructor_override_reason')) {
                $table->text('instructor_override_reason')->nullable()->after('instructor_grade_override');
            }
            if (!Schema::hasColumn('lab_sessions', 'instructor_overridden_at')) {
                $table->timestamp('instructor_overridden_at')->nullable()->after('instructor_override_reason');
            }
            if (!Schema::hasColumn('lab_sessions', 'overridden_by')) {
                $table->foreignId('overridden_by')->nullable()->after('instructor_overridden_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lab_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('lab_sessions', 'overridden_by')) {
                $table->dropForeign(['overridden_by']);
                $table->dropColumn('overridden_by');
            }
            $dropColumns = [
                'ai_grade_summary',
                'instructor_grade_override',
                'instructor_override_reason',
                'instructor_overridden_at',
            ];
            foreach ($dropColumns as $col) {
                if (Schema::hasColumn('lab_sessions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
