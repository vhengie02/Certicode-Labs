<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When an open lab is due. Students see it as "Due in" or "Late by"; it never blocks work.
     */
    public function up(): void
    {
        Schema::table('laboratories', function (Blueprint $table) {
            $table->timestamp('due_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('laboratories', function (Blueprint $table) {
            $table->dropColumn('due_at');
        });
    }
};
