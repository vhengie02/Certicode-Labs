<?php

namespace App\Support;

/**
 * The name shown as the sender of every email.
 *
 * A .env file expands `${APP_NAME}`, but a host's environment variables do not. MAIL_FROM_NAME
 * copied from .env.example into Vercel reached inboxes as the literal text "${APP_NAME}".
 */
class MailFromName
{
    public static function resolve(?string $configured, string $appName): string
    {
        $configured = trim((string) $configured);

        if ($configured === '' || str_contains($configured, '${')) {
            return $appName;
        }

        return $configured;
    }
}
