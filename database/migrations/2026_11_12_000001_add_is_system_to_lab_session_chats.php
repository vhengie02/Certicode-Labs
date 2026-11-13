<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Team chat also carries notices the app writes itself: a teammate joined or left, the
     * leader changed. is_system tells them apart from what a student typed.
     */
    public function up(): void
    {
        Schema::table('lab_session_chats', function (Blueprint $table) {
            $table->boolean('is_system')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('lab_session_chats', function (Blueprint $table) {
            $table->dropColumn('is_system');
        });
    }
};
