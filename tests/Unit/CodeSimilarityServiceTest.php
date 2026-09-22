<?php

namespace Tests\Unit;

use App\Services\CodeSimilarityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodeSimilarityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CodeSimilarityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CodeSimilarityService();
    }

    /**
     * Test identical code returns 100% similarity.
     */
    public function test_identical_code_returns_high_similarity(): void
    {
        $code = <<<'CODE'
function calculateSum($a, $b) {
    $result = $a + $b;
    return $result * 2;
}
CODE;

        $score = $this->service->calculatePairSimilarity($code, $code);
        $this->assertEquals(100.0, $score);
    }

    /**
     * Test code with renamed variables and comments still detects structural plagiarism.
     */
    public function test_renamed_variables_and_comments_detected(): void
    {
        $codeA = <<<'CODE'
// Calculate fibonacci sequence
function fibonacci($n) {
    if ($n <= 1) {
        return $n;
    }
    return fibonacci($n - 1) + fibonacci($n - 2);
}
CODE;

        $codeB = <<<'CODE'
/* Different comment header */
function fibonacci($num) {
    // Check base condition
    if ($num <= 1) {
        return $num;
    }
    return fibonacci($num - 1) + fibonacci($num - 2);
}
CODE;

        $score = $this->service->calculatePairSimilarity($codeA, $codeB);
        $this->assertGreaterThan(75.0, $score);
    }

    /**
     * Test completely different code produces low similarity score.
     */
    public function test_completely_different_code_returns_low_similarity(): void
    {
        $codeA = 'echo "Hello world";';
        $codeB = <<<'CODE'
class BinarySearchTree {
    private $root;
    public function insert($val) {
        if ($this->root === null) {
            $this->root = new Node($val);
        }
    }
}
CODE;

        $score = $this->service->calculatePairSimilarity($codeA, $codeB);
        $this->assertLessThan(25.0, $score);
    }

    /**
     * Test tokenization strips comments and normalizes literals.
     */
    public function test_tokenize_strips_comments(): void
    {
        $code = '// single line comment' . "\n" . '/* multi line */ $x = "hello" + 42;';
        $tokens = $this->service->tokenize($code);

        $this->assertNotContains('single', $tokens);
        $this->assertNotContains('multi', $tokens);
        $this->assertContains('str_lit', $tokens);
        $this->assertContains('num_lit', $tokens);
        $this->assertContains('var_id', $tokens);
        $this->assertContains('=', $tokens);
    }

    /**
     * Test cohort analysis detects plagiarism pair in database.
     */
    public function test_cohort_analysis_detects_plagiarism_pair(): void
    {
        $lab = \App\Models\Laboratory::create([
            'title' => 'Sorting Algorithms',
            'description' => 'Implement sorting',
            'language' => 'php',
        ]);
        $user1 = \App\Models\User::create(['name' => 'Alice', 'email' => 'alice_test@test.com', 'password' => bcrypt('secret')]);
        $user2 = \App\Models\User::create(['name' => 'Bob', 'email' => 'bob_test@test.com', 'password' => bcrypt('secret')]);
        $user3 = \App\Models\User::create(['name' => 'Charlie', 'email' => 'charlie_test@test.com', 'password' => bcrypt('secret')]);

        $codeOriginal = <<<'CODE'
function bubbleSort($arr) {
    $n = count($arr);
    for ($i = 0; $i < $n - 1; $i++) {
        for ($j = 0; $j < $n - $i - 1; $j++) {
            if ($arr[$j] > $arr[$j + 1]) {
                $temp = $arr[$j];
                $arr[$j] = $arr[$j + 1];
                $arr[$j + 1] = $temp;
            }
        }
    }
    return $arr;
}
CODE;

        $codeCopied = <<<'CODE'
/* Copied and renamed */
function bubbleSort($items) {
    $count = count($items);
    for ($x = 0; $x < $count - 1; $x++) {
        for ($y = 0; $y < $count - $x - 1; $y++) {
            if ($items[$y] > $items[$y + 1]) {
                $swap = $items[$y];
                $items[$y] = $items[$y + 1];
                $items[$y + 1] = $swap;
            }
        }
    }
    return $items;
}
CODE;

        $codeDifferent = <<<'CODE'
function reverseString($str) {
    $len = strlen($str);
    $out = '';
    while ($len > 0) {
        $out .= $str[--$len];
    }
    return $out;
}
CODE;

        \App\Models\LabSession::create([
            'lab_id' => $lab->id,
            'user_id' => $user1->id,
            'status' => 'completed',
            'submitted_code' => $codeOriginal,
        ]);

        \App\Models\LabSession::create([
            'lab_id' => $lab->id,
            'user_id' => $user2->id,
            'status' => 'completed',
            'submitted_code' => $codeCopied,
        ]);

        \App\Models\LabSession::create([
            'lab_id' => $lab->id,
            'user_id' => $user3->id,
            'status' => 'completed',
            'submitted_code' => $codeDifferent,
        ]);

        $analysis = $this->service->analyzeLabCohort($lab->id, 70.0, 45.0);

        $this->assertEquals(3, $analysis['total_students_with_code']);
        $this->assertGreaterThanOrEqual(1, $analysis['flagged_pairs_count']);

        $topPair = $analysis['flagged_pairs'][0];
        $this->assertGreaterThan(75.0, $topPair['similarity']);
        $this->assertEquals('high', $topPair['risk']);
        $names = [$topPair['student_a']['name'], $topPair['student_b']['name']];
        $this->assertContains('Alice', $names);
        $this->assertContains('Bob', $names);
    }
}

