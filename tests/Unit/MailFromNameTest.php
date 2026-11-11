<?php

namespace Tests\Unit;

use App\Support\MailFromName;
use PHPUnit\Framework\TestCase;

/**
 * Emails once arrived from "${APP_NAME}": the host's environment held that text as it was typed,
 * because only a .env file expands it.
 */
class MailFromNameTest extends TestCase
{
    public function test_an_unexpanded_placeholder_falls_back_to_the_app_name(): void
    {
        $this->assertSame('Certicode Labs', MailFromName::resolve('${APP_NAME}', 'Certicode Labs'));
        $this->assertSame('Certicode Labs', MailFromName::resolve('"${APP_NAME}"', 'Certicode Labs'));
        $this->assertSame('Certicode Labs', MailFromName::resolve('${APP_NAME} Team', 'Certicode Labs'));
    }

    public function test_a_missing_or_blank_name_falls_back_to_the_app_name(): void
    {
        $this->assertSame('Certicode Labs', MailFromName::resolve(null, 'Certicode Labs'));
        $this->assertSame('Certicode Labs', MailFromName::resolve('  ', 'Certicode Labs'));
    }

    public function test_a_real_name_is_kept(): void
    {
        $this->assertSame('Certicode Support', MailFromName::resolve(' Certicode Support ', 'Certicode Labs'));
    }

    public function test_the_mail_config_uses_it(): void
    {
        $config = file_get_contents(dirname(__DIR__, 2).'/config/mail.php');

        $this->assertStringContainsString("MailFromName::resolve(env('MAIL_FROM_NAME')", $config);
    }
}
