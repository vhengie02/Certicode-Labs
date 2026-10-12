<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Platform-wide settings an admin can change without a deploy
        Schema::create('system_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::table('competencies', function (Blueprint $table) {
            $table->text('description')->nullable()->after('code');
        });

        // Which competencies a lab assesses; grading credits exactly these
        Schema::create('laboratory_competency', function (Blueprint $table) {
            $table->foreignId('laboratory_id')->constrained('laboratories')->cascadeOnDelete();
            $table->foreignId('competency_id')->constrained('competencies')->cascadeOnDelete();
            $table->primary(['laboratory_id', 'competency_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laboratory_competency');
        Schema::table('competencies', function (Blueprint $table) {
            $table->dropColumn('description');
        });
        Schema::dropIfExists('system_settings');
    }
};
