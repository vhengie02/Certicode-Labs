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
        // 1. school_classes table
        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique(); // Join Code
            $table->foreignId('instructor_id')->constrained('users')->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 2. modules table
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('content')->nullable(); // Lesson content
            $table->integer('order_index')->default(0);
            $table->timestamps();
        });

        // 3. class_student pivot table (Enrollment & Gmail invitation tracking)
        Schema::create('class_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('enrolled'); // enrolled, invited
            $table->timestamps();

            $table->unique(['class_id', 'student_id']);
        });

        // 4. Update laboratories table to associate with modules
        Schema::table('laboratories', function (Blueprint $table) {
            $table->foreignId('module_id')->nullable()->constrained('modules')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('laboratories', function (Blueprint $table) {
            $table->dropColumn('module_id');
        });
        Schema::dropIfExists('class_student');
        Schema::dropIfExists('modules');
        Schema::dropIfExists('school_classes');
    }
};
