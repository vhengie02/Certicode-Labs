<?php

namespace App\Support;

/**
 * Whether a program reads standard input. Judge0 runs code in one go, with all of its input
 * given up front, so the "Run the submitted code" panel asks for input only when the code
 * reads some. A guess from the source text: it leans towards yes, since a needless input box
 * costs nothing and a missing one makes the run crash.
 */
class StdinUsage
{
    /** Patterns that read standard input, per language (SandboxExecutionService's names). */
    private const PATTERNS = [
        'python' => [
            '/\b(?:raw_)?input\s*\(/',
            '/\bsys\s*\.\s*stdin\b/',
            '/\bfileinput\b/',
        ],
        'java' => [
            '/\bSystem\s*\.\s*in\b/',
        ],
        'javascript' => [
            '/\bprocess\s*\.\s*stdin\b/',
            '/[\'"](?:node:)?readline(?:\/promises)?[\'"]/',
            '/\breadFileSync\s*\(\s*(?:0\b|[\'"]\/dev\/stdin[\'"])/',
            '/\bprompt\s*\(/',
        ],
        'php' => [
            '/\bSTDIN\b/',
            '/php:\/\/stdin/i',
            '/\breadline\s*\(/',
        ],
        'c' => [
            '/\b(?:scanf|getchar|gets|getline)\s*\(/',
            '/\bstdin\b/',
        ],
        'cpp' => [
            '/\b(?:scanf|getchar|gets)\s*\(/',
            '/\bstdin\b/',
            '/\bcin\b/',
        ],
        'bash' => [
            // `read` as a command (at the start of a line or after ; & | do then while until if !),
            // perhaps after variable settings such as `IFS=`
            '/(?:^|[;&|!]|\b(?:do|then|while|until|if|else)\b)[ \t]*(?:\w+=\S*[ \t]+)*read\b/m',
            '/\/dev\/stdin\b/',
        ],
    ];

    /** Other names the code runner accepts for the same languages. */
    private const ALIASES = [
        'nodejs' => 'javascript',
        'js' => 'javascript',
        'shell' => 'bash',
        'sh' => 'bash',
        'c++' => 'cpp',
        'py' => 'python',
    ];

    /**
     * Whether $source reads standard input. With an unknown language, any language's patterns count.
     */
    public static function readsInput(string $source, ?string $language): bool
    {
        $language = strtolower(trim((string) $language));
        $language = self::ALIASES[$language] ?? $language;

        $languages = isset(self::PATTERNS[$language]) ? [$language] : array_keys(self::PATTERNS);

        foreach ($languages as $name) {
            $code = self::withoutComments($source, $name);
            foreach (self::PATTERNS[$name] as $pattern) {
                if (preg_match($pattern, $code) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The source without comments, so commented-out input code doesn't count. Rough: a comment
     * marker inside a string cuts the rest of that line too, which only makes a match less likely
     * on that line. PHP is left as it is, because `php://stdin` looks like a `//` comment.
     */
    private static function withoutComments(string $source, string $language): string
    {
        return match ($language) {
            'python' => preg_replace('/#.*$/m', '', $source),
            'bash' => preg_replace('/(?:^|(?<=\s))#.*$/m', '', $source),
            'java', 'javascript', 'c', 'cpp' => preg_replace(['/\/\*.*?\*\//s', '/(?<![:\'"])\/\/.*$/m'], '', $source),
            default => $source,
        } ?? $source;
    }
}
