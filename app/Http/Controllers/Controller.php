<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * Whether verification codes and password-reset links may be shown on screen or written to
     * the log when email delivery fails. Only for local development and tests: in production that
     * would let anyone verify an address or reset a password without access to the inbox.
     */
    protected function mayRevealAuthSecrets(): bool
    {
        return app()->environment('local', 'testing');
    }
}
