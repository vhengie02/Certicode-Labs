<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\ShowcaseAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Regression tests for the demo seeders: run against production they created instructor and
 * student accounts with the password "password", and reset a real user's password too.
 */
class SeederProductionGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeders_refuse_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        foreach ([DatabaseSeeder::class, DemoSeeder::class, ShowcaseAccountSeeder::class] as $seeder) {
            try {
                // Run directly: db:seed itself would stop to ask for confirmation in production
                (new $seeder())->setContainer($this->app)->__invoke();
                $this->fail("{$seeder} ran in production");
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('never runs in production', $e->getMessage());
            }
        }

        $this->assertSame(0, User::count());
    }

    public function test_the_showcase_seeder_leaves_existing_users_passwords_alone(): void
    {
        $ben = User::create(['name' => 'Ben', 'email' => 'ben@gmail.com', 'password' => Hash::make('his-own-password'), 'role' => 'student']);

        $this->seed(ShowcaseAccountSeeder::class);

        $this->assertTrue(Hash::check('his-own-password', $ben->fresh()->password));
    }
}
