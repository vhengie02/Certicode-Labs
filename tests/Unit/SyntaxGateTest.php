<?php

namespace Tests\Unit;

use App\Services\LlmEvaluationService;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for the pre-grade syntax gate, which gave valid code a final 0% without
 * asking Claude: six repeated characters (1000000, comment dividers) and Python # comments
 * with a bracket or apostrophe counted as syntax errors.
 */
class SyntaxGateTest extends TestCase
{
    private function errors(string $code, string $language): array
    {
        return (new LlmEvaluationService())->detectSyntaxErrors($code, $language);
    }

    public function test_valid_java_with_long_literals_and_dividers_passes(): void
    {
        $code = <<<'JAVA'
public class Main {
    // ________ helpers ________
    static final int MAX = 1000000;
    static final long BIG = 999999L;
    static final int MASK = 0xFFFFFFFF;

    public static void main(String[] args) {
        String face = ":-( {";   // a ( and { in a string
        char open = '(';
        /* unbalanced ( in a block comment { */
        System.out.println(MAX + BIG + MASK + face + open);
    }
}
JAVA;

        $this->assertSame([], $this->errors($code, 'java'));
    }

    public function test_python_comments_with_brackets_and_apostrophes_pass(): void
    {
        $code = <<<'PY'
def average(values):
    # don't divide (yet
    total = sum(values)  # sum of values, [unbalanced
    """Docstring with a ( and a {"""
    return total / len(values)
PY;

        $this->assertSame([], $this->errors($code, 'python'));
    }

    public function test_real_syntax_errors_are_still_caught(): void
    {
        $this->assertNotEmpty($this->errors("public class Main {\n    void f() {\n}\n", 'java'));
        $this->assertNotEmpty($this->errors("class Main {\n    int f() {\n        return 1;aaaaaaaa\n    }\n}\n", 'java'));
        $this->assertNotEmpty($this->errors("def f(:\n    return 1\n", 'python'));
    }
}
