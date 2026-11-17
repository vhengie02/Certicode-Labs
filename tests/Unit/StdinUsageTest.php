<?php

namespace Tests\Unit;

use App\Support\StdinUsage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Whether a program reads standard input, so the "Run the submitted code" panel shows its input
 * box only for code that needs one.
 */
class StdinUsageTest extends TestCase
{
    public static function readsInput(): array
    {
        return [
            'python input()' => ["name = input('Name? ')\nprint(name)", 'python'],
            'python sys.stdin' => ["import sys\nfor line in sys.stdin:\n    print(line)", 'python'],
            'java Scanner' => ["import java.util.Scanner;\nclass Main { public static void main(String[] a) { Scanner sc = new Scanner(System.in); } }", 'java'],
            'java BufferedReader' => ['BufferedReader r = new BufferedReader(new InputStreamReader(System.in));', 'java'],
            'javascript readline' => ["const readline = require('readline');", 'javascript'],
            'javascript node:readline import' => ["import * as readline from 'node:readline/promises';", 'javascript'],
            'javascript process.stdin' => ["process.stdin.on('data', d => console.log(d));", 'javascript'],
            'javascript fs stdin' => ["const data = require('fs').readFileSync(0, 'utf8');", 'javascript'],
            'php STDIN' => ['<?php $line = trim(fgets(STDIN));', 'php'],
            'php stream' => ["<?php \$in = fopen('php://stdin', 'r');", 'php'],
            'php readline' => ["<?php \$name = readline('Name? ');", 'php'],
            'c scanf' => ["#include <stdio.h>\nint main() { int n; scanf(\"%d\", &n); }", 'c'],
            'c fgets stdin' => ['fgets(buf, sizeof buf, stdin);', 'c'],
            'c getchar' => ['int ch = getchar();', 'c'],
            'cpp cin' => ["#include <iostream>\nint main() { int n; std::cin >> n; }", 'cpp'],
            'cpp using cin' => ['using namespace std; int main() { string s; cin >> s; }', 'cpp'],
            'cpp getline' => ['std::getline(std::cin, line);', 'cpp'],
            'bash read' => ["#!/bin/bash\nread -p 'Name: ' name\necho \$name", 'bash'],
            'bash while read' => ['while IFS= read -r line; do echo $line; done', 'bash'],
            'unknown language' => ["n = int(input())\nprint(n * 2)", null],
        ];
    }

    #[DataProvider('readsInput')]
    public function test_code_that_reads_input_is_detected(string $source, ?string $language): void
    {
        $this->assertTrue(StdinUsage::readsInput($source, $language));
    }

    public static function readsNoInput(): array
    {
        return [
            'python print' => ["print('Hello')", 'python'],
            'python commented input' => ["# name = input()\nprint('Hello')", 'python'],
            'java print' => ['class Main { public static void main(String[] a) { System.out.println("Hi"); } }', 'java'],
            'java unused Scanner import' => ["import java.util.Scanner;\nclass Main { public static void main(String[] a) { System.out.println(\"Hi\"); } }", 'java'],
            'java commented Scanner' => ["class Main {\n  // Scanner sc = new Scanner(System.in);\n  /* System.in */\n  public static void main(String[] a) {}\n}", 'java'],
            'javascript log' => ["console.log('Hello');", 'javascript'],
            'javascript url string' => ["const url = 'https://example.com'; console.log(url);", 'javascript'],
            'php echo' => ["<?php echo 'Hello';", 'php'],
            'c printf' => ["#include <stdio.h>\nint main() { printf(\"Hi\\n\"); }", 'c'],
            'cpp cout' => ["#include <iostream>\nint main() { std::cout << \"Hi\"; }", 'cpp'],
            'bash echo' => ["#!/bin/bash\necho 'already read the docs'", 'bash'],
            'python reading a file' => ["with open('data.txt') as f:\n    print(f.read())", 'python'],
        ];
    }

    #[DataProvider('readsNoInput')]
    public function test_code_that_reads_no_input_is_not_flagged(string $source, ?string $language): void
    {
        $this->assertFalse(StdinUsage::readsInput($source, $language));
    }

    public function test_language_names_are_matched_loosely(): void
    {
        $this->assertTrue(StdinUsage::readsInput('x = input()', 'Python'));
        $this->assertTrue(StdinUsage::readsInput('process.stdin.resume();', 'nodejs'));
        $this->assertTrue(StdinUsage::readsInput('read name', 'sh'));
    }
}
