<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Students now enter a class only through its roster, and being on the roster is being
     * enrolled. Open invitations were the instructor's own picks, so they become enrollments.
     */
    public function up(): void
    {
        DB::table('class_student')->where('status', 'invited')->update(['status' => 'enrolled', 'updated_at' => now()]);
    }

    /**
     * Which rows were invitations can't be known any more, so they stay enrolled.
     */
    public function down(): void {}
};
