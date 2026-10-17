<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How many sessions a student may start on a lab. 1 means no retakes; 0 means unlimited.
     */
    public function up(): void
    {
        Schema::table('laboratories', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_attempts')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('laboratories', function (Blueprint $table) {
            $table->dropColumn('max_attempts');
        });
    }
};
