<?php

namespace App\Services;

use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\LabSimilarityReport;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class CodeSimilarityService
{
    /**
     * Minimum token length for shingle/n-gram analysis.
     */
    protected int $ngramSize = 4;

    /**
     * A submission with fewer n-grams of its own than this, once the starter code is taken
     * out, is essentially the starter code and isn't compared.
     */
    protected int $minOwnNgrams = 5;

    /**
     * The lab's stored comparison, computed now only if there is none yet. Students' submits
     * keep it current (refreshLabReport), so loading the monitoring page doesn't compare
     * every pair again.
     */
    public function labReport(int $labId): array
    {
        try {
            $report = LabSimilarityReport::where('lab_id', $labId)->first();
        } catch (QueryException $e) {
            // The table's migration hasn't run on this database yet
            Log::warning('lab_similarity_reports is unavailable; comparing without storing: '.$e->getMessage());

            return $this->analyzeLabCohort($labId);
        }

        return $report
            ? $report->analysis + ['computed_at' => $report->computed_at?->toIso8601String()]
            : $this->refreshLabReport($labId);
    }

    /**
     * Compare the lab's submissions again and store the result.
     */
    public function refreshLabReport(int $labId): array
    {
        $analysis = $this->analyzeLabCohort($labId);
        $computedAt = now();

        try {
            LabSimilarityReport::updateOrCreate(['lab_id' => $labId], ['analysis' => $analysis, 'computed_at' => $computedAt]);
        } catch (QueryException $e) {
            Log::warning('Could not store the similarity report for lab '.$labId.': '.$e->getMessage());
        }

        return $analysis + ['computed_at' => $computedAt->toIso8601String()];
    }

    /**
     * refreshLabReport for a request that has already done its real work (a submit): a
     * failure here is logged, never thrown.
     */
    public function refreshLabReportQuietly(int $labId): void
    {
        try {
            $this->refreshLabReport($labId);
        } catch (\Throwable $e) {
            Log::warning("Similarity report for lab {$labId} not refreshed: ".$e->getMessage());
        }
    }

    /**
     * Analyze all student submissions in a laboratory session cohort. Each student counts once
     * (their latest submitted attempt, else their latest attempt with code), teammates in a
     * group lab are not compared with each other, and code that came from the lab's starter
     * files doesn't count towards a match.
     *
     * @param  float  $highRiskThreshold  Percentage above which a pair is flagged as high risk (default: 75.0)
     * @param  float  $mediumRiskThreshold  Percentage above which a pair is flagged as medium risk (default: 50.0)
     */
    public function analyzeLabCohort(int $labId, float $highRiskThreshold = 75.0, float $mediumRiskThreshold = 50.0): array
    {
        $sessions = LabSession::with(['user', 'group'])
            ->where('lab_id', $labId)
            ->whereNotNull('submitted_code')
            ->where('submitted_code', '!=', '')
            ->get();

        // A retake is not copied from the student's own earlier attempt
        $sessions = $sessions
            ->groupBy(fn ($s) => $s->user_id ?? 'session_'.$s->id)
            ->map(fn ($own) => $own->where('status', 'completed')->sortByDesc('id')->first() ?? $own->sortByDesc('id')->first())
            ->values();

        $starter = $this->starterProfile(Laboratory::find($labId));

        $students = [];
        $preprocessed = [];

        foreach ($sessions as $session) {
            $name = $session->user ? $session->user->name : 'Student #'.$session->id;
            $code = $session->submitted_code;

            $tokens = $this->tokenize($code);
            if (empty($tokens)) {
                continue;
            }

            // Everyone was given the starter code, so sharing it is not a match
            $ownNgrams = $this->withoutMultiset($this->generateNgrams($tokens, $this->ngramSize), $starter['ngrams']);
            if (count($ownNgrams) < $this->minOwnNgrams) {
                continue;
            }

            $students[$session->id] = [
                'session_id' => $session->id,
                'user_id' => $session->user_id,
                'group_id' => $session->group_id,
                'name' => $name,
                'group' => $session->group ? $session->group->name : null,
                'code_length' => strlen($code),
                'token_count' => count($tokens),
                'code_preview' => mb_substr($code, 0, 250),
            ];

            $preprocessed[$session->id] = [
                'tokens' => $this->withoutMultiset($tokens, $starter['tokens']),
                'ngrams' => $ownNgrams,
            ];
        }

        $sessionIds = array_keys($students);
        $count = count($sessionIds);
        $flaggedPairs = [];
        $matrix = [];

        // Build pairwise comparisons
        for ($i = 0; $i < $count; $i++) {
            $idA = $sessionIds[$i];
            $matrix[$idA] = [];

            for ($j = 0; $j < $count; $j++) {
                $idB = $sessionIds[$j];

                if ($i === $j) {
                    $matrix[$idA][$idB] = 100.0;

                    continue;
                }

                if ($j < $i) {
                    // Mirror symmetric value
                    $matrix[$idA][$idB] = $matrix[$idB][$idA];

                    continue;
                }

                // Teammates write the same code on purpose (null: not compared)
                $sameTeam = $students[$idA]['group_id'] !== null && $students[$idA]['group_id'] === $students[$idB]['group_id'];
                $sameStudent = $students[$idA]['user_id'] !== null && $students[$idA]['user_id'] === $students[$idB]['user_id'];
                if ($sameTeam || $sameStudent) {
                    $matrix[$idA][$idB] = null;

                    continue;
                }

                $score = $this->calculateSimilarity(
                    $preprocessed[$idA]['ngrams'],
                    $preprocessed[$idB]['ngrams'],
                    $preprocessed[$idA]['tokens'],
                    $preprocessed[$idB]['tokens']
                );

                $matrix[$idA][$idB] = $score;

                if ($score >= $mediumRiskThreshold) {
                    $risk = $score >= $highRiskThreshold ? 'high' : 'medium';

                    $flaggedPairs[] = [
                        'student_a' => $students[$idA],
                        'student_b' => $students[$idB],
                        'similarity' => round($score, 1),
                        'risk' => $risk,
                        'reason' => $risk === 'high'
                            ? 'Suspiciously identical code structure and token sequence.'
                            : 'Moderate structural and algorithmic overlap detected.',
                    ];
                }
            }
        }

        // Sort flagged pairs by similarity descending
        usort($flaggedPairs, fn ($a, $b) => $b['similarity'] <=> $a['similarity']);

        return [
            'total_students_with_code' => $count,
            'flagged_pairs_count' => count($flaggedPairs),
            'high_risk_count' => count(array_filter($flaggedPairs, fn ($p) => $p['risk'] === 'high')),
            'medium_risk_count' => count(array_filter($flaggedPairs, fn ($p) => $p['risk'] === 'medium')),
            'flagged_pairs' => $flaggedPairs,
            'students' => array_values($students),
            'matrix' => $matrix,
        ];
    }

    /**
     * Token and n-gram counts of the lab's starter files, the code every student starts from.
     */
    protected function starterProfile(?Laboratory $lab): array
    {
        $tokens = [];
        $ngrams = [];

        foreach ($lab ? $lab->getStarterFilesList() : [] as $file) {
            $fileTokens = $this->tokenize((string) ($file['content'] ?? ''));
            if (empty($fileTokens)) {
                continue;
            }
            array_push($tokens, ...$fileTokens);
            array_push($ngrams, ...$this->generateNgrams($fileTokens, $this->ngramSize));
        }

        return ['tokens' => array_count_values($tokens), 'ngrams' => array_count_values($ngrams)];
    }

    /**
     * $items with each value removed as many times as $counts has it (multiset difference).
     */
    protected function withoutMultiset(array $items, array $counts): array
    {
        $kept = [];
        foreach ($items as $item) {
            if (($counts[$item] ?? 0) > 0) {
                $counts[$item]--;

                continue;
            }
            $kept[] = $item;
        }

        return $kept;
    }

    /**
     * Calculate similarity percentage between two raw code strings.
     *
     * @return float 0.0 to 100.0
     */
    public function calculatePairSimilarity(string $codeA, string $codeB): float
    {
        $tokensA = $this->tokenize($codeA);
        $tokensB = $this->tokenize($codeB);

        if (empty($tokensA) || empty($tokensB)) {
            return 0.0;
        }

        $ngramsA = $this->generateNgrams($tokensA, $this->ngramSize);
        $ngramsB = $this->generateNgrams($tokensB, $this->ngramSize);

        return $this->calculateSimilarity($ngramsA, $ngramsB, $tokensA, $tokensB);
    }

    /**
     * Compute composite similarity using Jaccard n-gram overlap and token multiset intersection.
     */
    protected function calculateSimilarity(array $ngramsA, array $ngramsB, array $tokensA, array $tokensB): float
    {
        if (empty($ngramsA) || empty($ngramsB)) {
            // Fallback to token Jaccard if n-grams are too short
            return $this->jaccardSimilarity($tokensA, $tokensB);
        }

        // 1. N-Gram Jaccard
        $ngramScore = $this->jaccardSimilarity($ngramsA, $ngramsB);

        // 2. Token Bag-of-Words Cosine / Overlap
        $tokenScore = $this->tokenOverlapScore($tokensA, $tokensB);

        // Weighted combination (65% structural n-gram sequence, 35% token vocabulary overlap)
        $combined = ($ngramScore * 0.65) + ($tokenScore * 0.35);

        return min(100.0, max(0.0, round($combined, 2)));
    }

    /**
     * Calculate Jaccard similarity between two arrays.
     */
    protected function jaccardSimilarity(array $setA, array $setB): float
    {
        $countA = count($setA);
        $countB = count($setB);

        if ($countA === 0 && $countB === 0) {
            return 100.0;
        }

        if ($countA === 0 || $countB === 0) {
            return 0.0;
        }

        // Count frequency map
        $mapA = array_count_values($setA);
        $mapB = array_count_values($setB);

        $intersection = 0;
        $union = 0;

        $allKeys = array_unique(array_merge(array_keys($mapA), array_keys($mapB)));
        foreach ($allKeys as $key) {
            $freqA = $mapA[$key] ?? 0;
            $freqB = $mapB[$key] ?? 0;
            $intersection += min($freqA, $freqB);
            $union += max($freqA, $freqB);
        }

        return $union > 0 ? ($intersection / $union) * 100.0 : 0.0;
    }

    /**
     * Calculate token frequency overlap score.
     */
    protected function tokenOverlapScore(array $tokensA, array $tokensB): float
    {
        $freqA = array_count_values($tokensA);
        $freqB = array_count_values($tokensB);

        $commonCount = 0;
        foreach ($freqA as $token => $count) {
            if (isset($freqB[$token])) {
                $commonCount += min($count, $freqB[$token]);
            }
        }

        $avgTotal = (count($tokensA) + count($tokensB)) / 2.0;

        return $avgTotal > 0 ? ($commonCount / $avgTotal) * 100.0 : 0.0;
    }

    /**
     * Clean, normalize, and tokenize source code into a sequence of structural tokens.
     */
    public function tokenize(string $code): array
    {
        // 1. Remove comments
        // Multi-line comments
        $code = preg_replace('!/\*.*?\*/!s', '', $code);
        // Single-line comments (// and #)
        $code = preg_replace('!//.*$!m', '', $code);
        $code = preg_replace('!#.*$!m', '', $code);

        // 2. Remove string literals to focus on structural logic (replace with placeholder token)
        $code = preg_replace('/"([^"\\\\]|\\\\.)*"/', 'STR_LIT', $code);
        $code = preg_replace("/'([^'\\\\]|\\\\.)*'/", 'STR_LIT', $code);

        // 3. Normalize numbers
        $code = preg_replace('/\b\d+(\.\d+)?\b/', 'NUM_LIT', $code);

        // 4. Tokenize identifiers, keywords, and structural punctuation
        preg_match_all('/[a-zA-Z_]\w*|[{}()\[\];,\.=<>\+\-\*\/%&!|]/', $code, $matches);

        $rawTokens = $matches[0] ?? [];

        $keywords = [
            'function', 'def', 'fn', 'sub', 'class', 'interface', 'trait', 'extends', 'implements',
            'if', 'elif', 'elseif', 'else', 'endif', 'switch', 'case', 'default',
            'for', 'foreach', 'while', 'do', 'endwhile', 'endfor', 'endforeach',
            'break', 'continue', 'return', 'yield',
            'try', 'catch', 'finally', 'throw', 'throws',
            'public', 'private', 'protected', 'static', 'final', 'abstract', 'const', 'let', 'var',
            'new', 'clone', 'instanceof', 'typeof',
            'import', 'from', 'package', 'use', 'namespace', 'require', 'include',
            'echo', 'print', 'println', 'printf', 'console', 'log',
            'int', 'integer', 'float', 'double', 'bool', 'boolean', 'string', 'char', 'void', 'array', 'object',
            'true', 'false', 'null', 'nil', 'none', 'undefined', 'this', 'self', 'super',
            'str_lit', 'num_lit',
        ];
        $keywordMap = array_flip($keywords);

        $tokens = [];
        foreach ($rawTokens as $tok) {
            $lower = strtolower($tok);
            if (preg_match('/^[a-zA-Z_]/', $tok)) {
                $tokens[] = isset($keywordMap[$lower]) ? $lower : 'var_id';
            } else {
                $tokens[] = $tok;
            }
        }

        return $tokens;
    }

    /**
     * Generate contiguous n-grams from a sequence of tokens.
     */
    public function generateNgrams(array $tokens, int $n = 4): array
    {
        $count = count($tokens);
        if ($count < $n) {
            return [implode('_', $tokens)];
        }

        $ngrams = [];
        for ($i = 0; $i <= $count - $n; $i++) {
            $slice = array_slice($tokens, $i, $n);
            $ngrams[] = implode('_', $slice);
        }

        return $ngrams;
    }
}
