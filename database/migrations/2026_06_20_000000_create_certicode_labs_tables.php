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
        // 1. Laboratories Table
        Schema::create('laboratories', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->string('github_repo_template')->nullable();
            $table->json('tasks_definition')->nullable(); // Checklists, validation commands/scripts
            $table->integer('time_limit')->default(60); // In minutes
            $table->boolean('is_group_lab')->default(false);
            $table->timestamps();
        });

        // 2. Groups Table (For Collaboration Labs)
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('lab_id')->constrained('laboratories')->cascadeOnDelete();
            $table->timestamps();
        });

        // 3. Group Members Table (Pivot)
        Schema::create('group_members', function (Blueprint $table) {
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->float('contribution_score')->nullable(); // Track participation percentage
            $table->timestamps();

            $table->primary(['group_id', 'user_id']);
        });

        // 4. Lab Sessions Table
        Schema::create('lab_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id')->constrained('laboratories')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->string('github_repo_url')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('status')->default('in_progress'); // in_progress, completed, flagged, abandoned
            $table->float('performance_score')->default(0.0);
            $table->timestamps();
        });

        // 5. Telemetry Logs Table
        Schema::create('telemetry_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_session_id')->constrained('lab_sessions')->cascadeOnDelete();
            $table->string('event_type'); // tab_switch, idle, code_paste, webcam_check, command, etc.
            $table->json('payload')->nullable(); // Details of event
            $table->timestamp('created_at')->useCurrent();
        });

        // 6. Anomalies Table
        Schema::create('anomalies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_session_id')->constrained('lab_sessions')->cascadeOnDelete();
            $table->string('type'); // no_face, multiple_faces, excessive_tab_switch, low_contribution, etc.
            $table->string('severity')->default('low'); // low, medium, high
            $table->text('description')->nullable();
            $table->boolean('resolved')->default(false);
            $table->timestamps();
        });

        // 7. Competencies Table
        Schema::create('competencies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique(); // e.g., COMP-LINUX-01
            $table->timestamps();
        });

        // 8. Student Competencies Table (Progress Tracker)
        Schema::create('student_competencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('competency_id')->constrained('competencies')->cascadeOnDelete();
            $table->float('score_achieved')->default(0.0);
            $table->timestamps();
        });

        // 9. Certificates Table
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('verification_code')->unique();
            $table->string('qr_code_path')->nullable();
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('student_competencies');
        Schema::dropIfExists('competencies');
        Schema::dropIfExists('anomalies');
        Schema::dropIfExists('telemetry_logs');
        Schema::dropIfExists('lab_sessions');
        Schema::dropIfExists('group_members');
        Schema::dropIfExists('groups');
        Schema::dropIfExists('laboratories');
    }
};
