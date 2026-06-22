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
        // 1. Create module_attachments table
        Schema::create('module_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->unsignedInteger('file_size')->default(0);
            $table->timestamps();
        });

        // 2. Add views_count to modules table
        Schema::table('modules', function (Blueprint $table) {
            $table->unsignedInteger('views_count')->default(0)->after('order_index');
        });

        // 3. Add views_count to laboratories table
        Schema::table('laboratories', function (Blueprint $table) {
            $table->unsignedInteger('views_count')->default(0)->after('time_limit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('laboratories', function (Blueprint $table) {
            $table->dropColumn('views_count');
        });

        Schema::table('modules', function (Blueprint $table) {
            $table->dropColumn('views_count');
        });

        Schema::dropIfExists('module_attachments');
    }
};
