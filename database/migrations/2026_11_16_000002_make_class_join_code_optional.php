<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * New classes get no join code. Old codes stay in the column but open nothing.
     *
     * Kept apart from data changes: SQLite changes a column by rebuilding the table, and
     * Laravel turns foreign keys off for that. Run inside a transaction, where that pragma
     * does nothing, dropping the old table would cascade-delete every roster and module.
     */
    public function up(): void
    {
        Schema::table('school_classes', function (Blueprint $table) {
            $table->string('code')->nullable()->change();
        });
    }

    /**
     * Classes made without a code get a fresh one so the column can be required again.
     */
    public function down(): void
    {
        DB::table('school_classes')->whereNull('code')->orderBy('id')->each(function ($class) {
            do {
                $code = 'CLASS-'.strtoupper(Str::random(6));
            } while (DB::table('school_classes')->where('code', $code)->exists());

            DB::table('school_classes')->where('id', $class->id)->update(['code' => $code]);
        });

        Schema::table('school_classes', function (Blueprint $table) {
            $table->string('code')->nullable(false)->change();
        });
    }
};
