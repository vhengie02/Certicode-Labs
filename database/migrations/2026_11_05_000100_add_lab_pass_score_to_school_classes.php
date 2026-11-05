<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The score a lab needs to count toward a class's certificate. passing_threshold stays
     * the share of labs a student must reach.
     */
    public function up(): void
    {
        Schema::table('school_classes', function (Blueprint $table) {
            $table->integer('lab_pass_score')->default(75);
        });
    }

    public function down(): void
    {
        Schema::table('school_classes', function (Blueprint $table) {
            $table->dropColumn('lab_pass_score');
        });
    }
};
