<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The last code-similarity comparison of each lab. It is computed when a student submits
     * (or the instructor asks to compare again), so the monitoring page only reads it.
     */
    public function up(): void
    {
        Schema::create('lab_similarity_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id')->unique()->constrained('laboratories')->cascadeOnDelete();
            $table->json('analysis');
            $table->timestamp('computed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_similarity_reports');
    }
};
