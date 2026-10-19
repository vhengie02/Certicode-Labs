<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression test for the Supabase Auth signup trigger, which took the new user's role from
 * signup metadata the client controls. The tests run on SQLite, so this checks the SQL the
 * migration sends to Postgres, and that the migration is the last word on the function.
 */
class SupabaseSignupRoleTest extends TestCase
{
    private const MIGRATIONS = __DIR__.'/../../database/migrations';

    /**
     * The SQL a migration sends when it runs against Postgres.
     */
    private function postgresStatements(string $file): array
    {
        $statements = [];
        DB::shouldReceive('getDriverName')->andReturn('pgsql');
        DB::shouldReceive('statement')->andReturnUsing(function ($sql) use (&$statements) {
            $statements[] = $sql;

            return true;
        });

        (require $file)->up();

        return $statements;
    }

    public function test_new_supabase_users_are_always_students(): void
    {
        // The newest migration defining the function is the one Postgres ends up with
        $definitions = array_filter(glob(self::MIGRATIONS.'/*.php'), fn ($f) => str_contains(file_get_contents($f), 'FUNCTION public.handle_new_auth_user'));
        sort($definitions);
        $latest = end($definitions);

        $sql = implode("\n", array_filter($this->postgresStatements($latest), fn ($s) => str_contains($s, 'handle_new_auth_user')));

        $this->assertNotSame('', $sql);
        $this->assertStringNotContainsString("raw_user_meta_data->>'role'", $sql);
        $this->assertMatchesRegularExpression("/'student',\s*NEW\.id/", $sql);
    }
}
