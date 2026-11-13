<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the team attempt a session belongs to began: the moment its first teammate opened
     * the lab. Every teammate's session in that attempt carries the same value, so it is both
     * the team's shared clock and what tells one team attempt from an earlier one. Null on
     * solo attempts and on team attempts opened before this column existed.
     */
    public function up(): void
    {
        Schema::table('lab_sessions', function (Blueprint $table) {
            $table->timestamp('team_started_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('lab_sessions', function (Blueprint $table) {
            $table->dropColumn('team_started_at');
        });
    }
};
