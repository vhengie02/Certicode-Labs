<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Lesson content is HTML from the module editor, shown raw to every student and admin who
 * opens the module. Only an allow-list of formatting markup survives: no scripts, event
 * handlers, styles, frames or form controls, and links and images use http(s) or relative
 * URLs only.
 */
class ModuleHtml
{
    private static ?HtmlSanitizer $sanitizer = null;

    public static function sanitize(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        return self::sanitizer()->sanitize($html);
    }

    private static function sanitizer(): HtmlSanitizer
    {
        return self::$sanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->blockElement('form')
                ->dropElement('input')
                ->dropElement('button')
                ->dropElement('select')
                ->dropElement('textarea')
                ->allowLinkSchemes(['http', 'https', 'mailto'])
                ->allowRelativeLinks()
                ->allowMediaSchemes(['http', 'https'])
                ->allowRelativeMedias()
                ->forceAttribute('a', 'rel', 'noopener noreferrer')
                // Long lessons are fine; the default cut-off is 20 KB
                ->withMaxInputLength(1_000_000)
        );
    }
}
