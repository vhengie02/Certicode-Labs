<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Null means follow the viewer's device
            $table->string('timezone', 64)->nullable();
            $table->string('date_style', 8)->nullable();
            $table->boolean('notify_grade')->default(true);
            // Off by default: a live lab can raise many flags
            $table->boolean('notify_integrity')->default(false);
        });

        // Grade emails used to count as lab emails, so anyone who turned those off stays off
        DB::table('users')->update(['notify_grade' => DB::raw('notify_lab')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['timezone', 'date_style', 'notify_grade', 'notify_integrity']);
        });
    }
};
