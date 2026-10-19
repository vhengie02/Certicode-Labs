<?php

namespace App\Support;

use Illuminate\Database\PostgresConnection as BasePostgresConnection;

/**
 * Binds booleans as 'true'/'false' instead of 1/0.
 *
 * With PDO::ATTR_EMULATE_PREPARES on (needed for the Supabase pooler), PDO inlines
 * bindings into the SQL. Laravel turns booleans into integers, so a write like
 * `resolved = 1` fails on a boolean column. A quoted 'true' is accepted.
 */
class PostgresConnection extends BasePostgresConnection
{
    public function prepareBindings(array $bindings)
    {
        foreach ($bindings as $key => $value) {
            if (is_bool($value)) {
                $bindings[$key] = $value ? 'true' : 'false';
            }
        }

        return parent::prepareBindings($bindings);
    }
}
