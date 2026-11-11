<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How a group lab forms its teams. team_size is the most people per team, and null on
     * group labs made before this setting: they have no limit. team_formation is who forms
     * the teams: the instructor by hand, the students, or a random draw.
     */
    public function up(): void
    {
        Schema::table('laboratories', function (Blueprint $table) {
            $table->unsignedSmallInteger('team_size')->nullable();
            $table->string('team_formation')->default('instructor');
        });
    }

    public function down(): void
    {
        Schema::table('laboratories', function (Blueprint $table) {
            $table->dropColumn(['team_size', 'team_formation']);
        });
    }
};
