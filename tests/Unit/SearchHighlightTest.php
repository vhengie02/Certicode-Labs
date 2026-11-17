<?php

namespace Tests\Unit;

use App\Support\SearchHighlight;
use PHPUnit\Framework\TestCase;

class SearchHighlightTest extends TestCase
{
    public function test_marks_each_match_and_keeps_its_casing(): void
    {
        $this->assertSame('<mark>Java</mark> OOP, <mark>JAVA</mark>', SearchHighlight::html('Java OOP, JAVA', 'java'));
    }

    public function test_matches_non_ascii_letters_without_regard_to_case(): void
    {
        $this->assertSame('<mark>École</mark> and <mark>école</mark>', SearchHighlight::html('École and école', 'ÉCOLE'));
    }

    public function test_surrounding_spaces_in_the_query_are_ignored(): void
    {
        $this->assertSame('Intro to <mark>PHP</mark>', SearchHighlight::html('Intro to PHP', '  php '));
    }

    public function test_empty_query_returns_the_escaped_text(): void
    {
        $this->assertSame('a &lt;b&gt;', SearchHighlight::html('a <b>', '   '));
    }

    public function test_invalid_utf8_query_returns_the_escaped_text(): void
    {
        $this->assertSame('a &lt;b&gt;', SearchHighlight::html('a <b>', "\xC3\x28"));
    }
}
