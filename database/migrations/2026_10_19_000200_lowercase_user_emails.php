<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Emails are now stored lowercased and looked up with lower(email). Lowercase the existing
     * rows too, except where that would collide with another account: those are the same
     * person registered twice and need merging by hand, so they are only logged.
     */
    public function up(): void
    {
        foreach (['email', 'gmail'] as $column) {
            $conflicts = DB::table('users as u')
                ->whereNotNull("u.{$column}")
                ->whereRaw("u.{$column} <> lower(u.{$column})")
                ->whereExists(fn ($q) => $q->from('users as other')
                    ->whereRaw("lower(other.{$column}) = lower(u.{$column})")
                    ->whereColumn('other.id', '<>', 'u.id'))
                ->pluck('u.id');

            if ($conflicts->isNotEmpty()) {
                Log::warning("Users sharing a {$column} apart from letter case, left as they are: ".$conflicts->implode(', '));
            }

            DB::table('users')
                ->whereNotNull($column)
                ->whereRaw("{$column} <> lower({$column})")
                ->whereNotIn('id', $conflicts)
                ->update([$column => DB::raw("lower({$column})")]);
        }

        // Lookups compare lower(email); this keeps them indexed
        DB::statement('CREATE INDEX IF NOT EXISTS users_lower_email_index ON users (lower(email))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_lower_email_index');
    }
};
