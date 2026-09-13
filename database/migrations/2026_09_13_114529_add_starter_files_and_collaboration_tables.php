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
        if (Schema::hasTable('laboratories') && !Schema::hasColumn('laboratories', 'starter_files')) {
            Schema::table('laboratories', function (Blueprint $table) {
                $table->json('starter_files')->nullable()->after('tasks_definition');
            });
        }

        if (Schema::hasTable('lab_sessions')) {
            Schema::table('lab_sessions', function (Blueprint $table) {
                if (!Schema::hasColumn('lab_sessions', 'diff_stats')) {
                    $table->json('diff_stats')->nullable()->after('completed_tasks');
                }
                if (!Schema::hasColumn('lab_sessions', 'code_contributions')) {
                    $table->json('code_contributions')->nullable()->after('diff_stats');
                }
            });
        }

        if (!Schema::hasTable('lab_session_chats')) {
            Schema::create('lab_session_chats', function (Blueprint $table) {
                $table->id();
                $table->foreignId('lab_session_id')->constrained('lab_sessions')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('user_name')->default('Student');
                $table->string('avatar_color')->default('#3ecf8e');
                $table->text('message');
                $table->text('code_snippet')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_session_chats');

        if (Schema::hasTable('lab_sessions')) {
            Schema::table('lab_sessions', function (Blueprint $table) {
                if (Schema::hasColumn('lab_sessions', 'code_contributions')) {
                    $table->dropColumn('code_contributions');
                }
                if (Schema::hasColumn('lab_sessions', 'diff_stats')) {
                    $table->dropColumn('diff_stats');
                }
            });
        }

        if (Schema::hasTable('laboratories') && Schema::hasColumn('laboratories', 'starter_files')) {
            Schema::table('laboratories', function (Blueprint $table) {
                $table->dropColumn('starter_files');
            });
        }
    }
};
