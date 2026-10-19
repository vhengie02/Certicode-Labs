<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Regression test for credentials committed as config defaults. The repo is public, so every
 * secret must come from the environment: env('DB_PASSWORD', '<real password>') leaks it.
 */
class ConfigSecretsTest extends TestCase
{
    private const SECRET_KEY = '/(PASSWORD|SECRET|_KEY|TOKEN)$/';

    /** Hosts and users that are safe to ship as defaults */
    private const SAFE_DEFAULTS = ['', '127.0.0.1', 'localhost', 'root', 'forge', 'laravel', 'postgres', 'sa'];

    private function configFiles(): array
    {
        return glob(dirname(__DIR__, 2).'/config/*.php');
    }

    public function test_no_config_file_ships_a_secret_as_an_env_default(): void
    {
        $leaks = [];
        foreach ($this->configFiles() as $file) {
            preg_match_all("/env\\(\\s*'([A-Z0-9_]+)'\\s*,\\s*'([^']+)'\\s*\\)/", file_get_contents($file), $matches, PREG_SET_ORDER);
            foreach ($matches as [, $key, $default]) {
                // Table names like AUTH_PASSWORD_RESET_TOKEN_TABLE are not secrets
                if (preg_match(self::SECRET_KEY, $key) && ! str_ends_with($key, '_TABLE')) {
                    $leaks[] = basename($file).": {$key} has a non-empty default";
                }
            }
        }

        $this->assertSame([], $leaks);
    }

    public function test_database_connections_default_to_local_development_values(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/config/database.php');

        preg_match_all("/env\\(\\s*'DB_(HOST|USERNAME)'\\s*,\\s*'([^']*)'\\s*\\)/", $source, $matches, PREG_SET_ORDER);
        $this->assertNotEmpty($matches);

        foreach ($matches as [, $key, $default]) {
            $this->assertContains($default, self::SAFE_DEFAULTS, "DB_{$key} default must be a local development value");
        }
        $this->assertStringNotContainsString('supabase', strtolower($source));
        $this->assertStringNotContainsString('pooler', strtolower($source));
    }
}
