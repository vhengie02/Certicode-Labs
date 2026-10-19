<?php

namespace Tests\Unit;

use App\Support\PostgresConnection;
use PHPUnit\Framework\TestCase;

class PostgresConnectionTest extends TestCase
{
    public function test_booleans_bind_as_postgres_literals(): void
    {
        $connection = new PostgresConnection(fn () => null, 'db', '', []);

        $this->assertSame(['true', 'false', 1, 'x'], $connection->prepareBindings([true, false, 1, 'x']));
    }
}
