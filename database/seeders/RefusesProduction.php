<?php

namespace Database\Seeders;

/**
 * The demo seeders create accounts whose password is "password". On a production database
 * those are known logins, so they stop before writing anything.
 */
trait RefusesProduction
{
    protected function refuseInProduction(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException(static::class . ' creates demo accounts with known passwords and never runs in production.');
        }
    }
}
