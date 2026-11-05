<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The background an instructor chose for a class card. With both empty, the card gets a
     * pattern from the class id. A picture wins over a pattern.
     */
    public function up(): void
    {
        Schema::table('school_classes', function (Blueprint $table) {
            $table->string('card_pattern')->nullable();
            $table->string('card_image_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('school_classes', function (Blueprint $table) {
            $table->dropColumn(['card_pattern', 'card_image_path']);
        });
    }
};
