<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Roster entries for people with no Certicode account yet. A row becomes a class_student
     * enrollment, and is deleted, when someone signs in with that email.
     */
    public function up(): void
    {
        Schema::create('class_pending_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->string('email'); // lowercased
            $table->string('name')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['class_id', 'email']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_pending_students');
    }
};
