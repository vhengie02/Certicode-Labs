<?php

namespace App\Support;

/**
 * A search result's text as HTML with every match of the query wrapped in <mark>.
 *
 * The text is split around the matches first and each piece is escaped on its own, so every
 * character of the result and of the query reaches the page escaped, and a query such as
 * "amp" can never land inside an escaped "&amp;". The query is matched literally (regex
 * characters included) and without regard to case, and each match keeps the result's casing.
 */
class SearchHighlight
{
    public static function html(string $text, string $query): string
    {
        $query = trim($query);
        // Invalid UTF-8 in the query would not compile as a /u pattern
        if ($query === '' || ! mb_check_encoding($query, 'UTF-8')) {
            return e($text);
        }

        // With PREG_SPLIT_DELIM_CAPTURE the matches land at the odd indexes
        $parts = preg_split('/('.preg_quote($query, '/').')/iu', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            // Invalid UTF-8 in the text: show it without marks
            return e($text);
        }

        $html = '';
        foreach ($parts as $i => $part) {
            $html .= $i % 2 === 1 ? '<mark>'.e($part).'</mark>' : e($part);
        }

        return $html;
    }
}
