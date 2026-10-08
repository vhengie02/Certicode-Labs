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
        Schema::table('lab_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('lab_sessions', 'extension_token')) {
                // Encrypted per-session secret the VS Code extension sends as X-Session-Token.
                $table->text('extension_token')->nullable()->after('last_ping_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lab_sessions', function (Blueprint $table) {
            $table->dropColumn('extension_token');
        });
    }
};
