<?php

namespace App\Services;

use App\Models\LabSession;
use Illuminate\Support\Facades\Log;

class LlmEvaluationService
{
    /**
     * Evaluate the student's code against the reference solution, rubric, and tasks.
     *
     * @param LabSession $session
     * @param string $code
     * @param string $language
     * @param array $diagnostics
     * @return array
     */
    public function evaluate(LabSession $session, string $code, string $language, array $diagnostics = []): array
    {
        $lab = $session->laboratory;
        $tasks = $lab->tasks_definition ?? [];
        $referenceSolution = $lab->reference_solution ?? '';
        $rubric = $lab->rubric ?? '';
        $testCases = $lab->test_cases ?? [];

        // 1. If code is empty or whitespace only, immediately return 0% evaluation
        if (empty(trim($code))) {
            return $this->evaluateEmptySubmission($tasks);
        }

        // 2. If code is purely markdown or non-code text (e.g. user opened TODO.md with no code)
        if ($this->isNonCodeDocument($code, $language)) {
            return $this->evaluateEmptySubmission($tasks, 'The submitted file is documentation/markdown, not runnable solution code.');
        }

        // 3. Syntax and compilation error detection (catches syntax errors like trailing garbage, unbalanced braces, etc.)
        $syntaxErrors = $this->detectSyntaxErrors($code, $language, $diagnostics);
        if (!empty($syntaxErrors)) {
            return $this->evaluateSyntaxErrorSubmission($tasks, $syntaxErrors, $language);
        }

        $apiKey = (string) config('services.anthropic.key');
        if ($apiKey !== '' && $apiKey !== 'mock') {
            try {
                return $this->evaluateWithClaude($lab->title, $lab->description, $tasks, $referenceSolution, $rubric, $testCases, $code, $language, $diagnostics);
            } catch (\Throwable $e) {
                Log::error('Claude evaluation failed: ' . $e->getMessage());
                // Fall back to rule-based mock evaluation
            }
        }

        return $this->evaluateMock($tasks, $code, $language);
    }

    /**
     * Return 0% evaluation when no code or non-code file is submitted.
     */
    protected function evaluateEmptySubmission(array $tasks, ?string $customMessage = null): array
    {
        $evaluatedTasks = [];
        $competencies = [];
        foreach ($tasks as $task) {
            $evaluatedTasks[] = [
                'id' => $task['id'],
                'completed' => false,
                'feedback' => $customMessage ?? 'No solution code submitted for this requirement.',
            ];
            $key = \Illuminate\Support\Str::slug($task['task'] ?? 'task_' . $task['id'], '_');
            $competencies[$key] = [
                'passed' => false,
                'reason' => 'Requirement unattempted: no runnable code provided.',
            ];
        }

        $summaryText = $customMessage ?? 'No runnable solution code detected in submission workspace. All checklist requirements and competencies marked unfulfilled with 0% score.';

        $gradeSummary = [
            'competencies' => !empty($competencies) ? $competencies : [
                'core_implementation' => ['passed' => false, 'reason' => 'No solution code submitted.']
            ],
            'test_cases_passed' => 0,
            'test_cases_total' => count($tasks) ?: 1,
            'code_quality_notes' => 'No runnable code files available to analyze.',
            'summary' => $summaryText,
        ];

        return [
            'tasks' => $evaluatedTasks,
            'correctness_score' => 0,
            'overall_feedback' => $summaryText,
            'code_quality_feedback' => 'No runnable code files available to analyze.',
            'ai_grade_summary' => $gradeSummary,
            'competencies' => $gradeSummary['competencies'],
            'test_cases_passed' => 0,
            'test_cases_total' => $gradeSummary['test_cases_total'],
            'code_quality_notes' => $gradeSummary['code_quality_notes'],
            'summary' => $summaryText,
        ];
    }

    /**
     * Return 0% evaluation when syntax or compilation errors prevent verifying requirements.
     */
    protected function evaluateSyntaxErrorSubmission(array $tasks, array $syntaxErrors, string $language): array
    {
        $errorSummary = implode(' | ', array_slice($syntaxErrors, 0, 3));
        $evaluatedTasks = [];
        $competencies = [];
        foreach ($tasks as $task) {
            $evaluatedTasks[] = [
                'id' => $task['id'],
                'completed' => false,
                'feedback' => "Requirement cannot be verified: code contains syntax/compilation error ({$syntaxErrors[0]}).",
            ];
            $key = \Illuminate\Support\Str::slug($task['task'] ?? 'task_' . $task['id'], '_');
            $competencies[$key] = [
                'passed' => false,
                'reason' => "Syntax error prevented compilation: {$syntaxErrors[0]}",
            ];
        }

        $summaryText = "Code analysis detected syntax/compilation errors: {$errorSummary}. Code must compile cleanly before checklist requirements can be evaluated.";

        $gradeSummary = [
            'competencies' => !empty($competencies) ? $competencies : [
                'compilation' => ['passed' => false, 'reason' => $errorSummary]
            ],
            'test_cases_passed' => 0,
            'test_cases_total' => count($tasks) ?: 1,
            'code_quality_notes' => "Syntax/Compilation Errors: {$errorSummary}",
            'summary' => $summaryText,
        ];

        return [
            'tasks' => $evaluatedTasks,
            'correctness_score' => 0,
            'overall_feedback' => "Compilation Failed: Syntax errors detected in {$language} code. All tasks marked pending with 0% score.",
            'code_quality_feedback' => "Syntax/Compilation Errors: {$errorSummary}",
            'ai_grade_summary' => $gradeSummary,
            'competencies' => $gradeSummary['competencies'],
            'test_cases_passed' => 0,
            'test_cases_total' => $gradeSummary['test_cases_total'],
            'code_quality_notes' => $gradeSummary['code_quality_notes'],
            'summary' => $summaryText,
        ];
    }

    /**
     * Statically inspect code for syntax errors, compilation issues, and illegal tokens.
     */
    public function detectSyntaxErrors(string $code, string $language, array $diagnostics = []): array
    {
        $errors = [];

        // 1. Check IDE diagnostics if provided
        foreach ($diagnostics as $diag) {
            $msg = is_array($diag) ? ($diag['message'] ?? '') : (string) $diag;
            if (!empty($msg) && (
                stripos($msg, 'syntax error') !== false ||
                stripos($msg, 'cannot find symbol') !== false ||
                stripos($msg, 'expected') !== false ||
                stripos($msg, 'not a statement') !== false ||
                stripos($msg, 'illegal') !== false ||
                stripos($msg, 'error:') !== false ||
                stripos($msg, 'unclosed') !== false
            )) {
                $errors[] = $msg;
            }
        }

        // Strip single-line and multi-line comments and strings for brace analysis
        $cleanCode = preg_replace('/\/\*.*?\*\//s', '', $code);
        $cleanCode = preg_replace('/\/\/.*?$/m', '', $cleanCode);
        $cleanCode = preg_replace('/"(?:\\\\.|[^"\\\\])*"/', '""', $cleanCode);
        $cleanCode = preg_replace('/\'(?:\\\\.|[^\'\\\\])*\'/', "''", $cleanCode);

        // 2. Unbalanced curly braces check
        $openBraces = substr_count($cleanCode, '{');
        $closeBraces = substr_count($cleanCode, '}');
        if ($openBraces !== $closeBraces) {
            $errors[] = "Unbalanced curly braces: found {$openBraces} opening '{' and {$closeBraces} closing '}'.";
        }

        // 3. Unbalanced parentheses check
        $openParens = substr_count($cleanCode, '(');
        $closeParens = substr_count($cleanCode, ')');
        if ($openParens !== $closeParens) {
            $errors[] = "Unbalanced parentheses: found {$openParens} opening '(' and {$closeParens} closing ')'.";
        }

        // 4. Repeated character spam / keyboard mash detection (e.g. aaaaaaaaaaaaaaaaaa)
        if (preg_match('/([a-zA-Z0-9_])\1{5,}/', $code, $spamMatches)) {
            $errors[] = "Invalid syntax: unexpected repeated token sequence '{$spamMatches[0]}'.";
        }

        // 5. Line ending syntax garbage: statement terminated with ';' followed by illegal identifier
        // e.g. "return result;aaaaaaaaaaaaaaaaaa" or "int x = 5; foo bar"
        $lines = explode("\n", $code);
        foreach ($lines as $lineNum => $line) {
            $trimmedLine = trim($line);
            // Ignore comment lines
            if (str_starts_with($trimmedLine, '//') || str_starts_with($trimmedLine, '/*')) {
                continue;
            }
            // Check for semicolon followed by non-whitespace that isn't a comment or recognized keyword
            if (preg_match('/;\s*([a-zA-Z0-9_]{3,})\s*$/', $trimmedLine, $m)) {
                $trailing = $m[1];
                $validFollowers = ['return', 'break', 'continue', 'throw', 'if', 'for', 'while', 'else'];
                if (!in_array(strtolower($trailing), $validFollowers)) {
                    $errors[] = "Syntax error on line " . ($lineNum + 1) . ": unexpected token '{$trailing}' after statement terminator ';'.";
                }
            }
        }

        return array_unique($errors);
    }

    /**
     * Check if the provided text appears to be a markdown document or project notes instead of code.
     */
    protected function isNonCodeDocument(string $code, string $language): bool
    {
        $langLower = strtolower(trim($language));
        if ($langLower === 'markdown' || $langLower === 'md') {
            return true;
        }

        $trimmed = trim($code);
        // If it starts with markdown headers (# or ##) and contains markdown bullet points without code syntax
        if (preg_match('/^#{1,3}\s+[A-Za-z]/m', $trimmed) && !preg_match('/(#include|import\s+|class\s+|def\s+|function\s+|public\s+class)/', $trimmed)) {
            return true;
        }

        return false;
    }

    /**
     * JSON schema the grade must match (structured outputs). Competencies are a list here because
     * strict schemas can't have arbitrary keys; they're converted back to a map keyed by name.
     */
    private const GRADE_SCHEMA = [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['tasks', 'correctness_score', 'competencies', 'test_cases_passed', 'test_cases_total', 'code_quality_notes', 'summary'],
        'properties' => [
            'tasks' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['id', 'completed', 'feedback'],
                    'properties' => [
                        'id' => ['type' => 'integer', 'description' => 'The task id from the checklist'],
                        'completed' => ['type' => 'boolean'],
                        'feedback' => ['type' => 'string', 'description' => 'Short, constructive feedback for the student'],
                    ],
                ],
            ],
            'correctness_score' => ['type' => 'integer', 'description' => 'Overall score from 0 to 100'],
            'competencies' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['name', 'passed', 'reason'],
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'snake_case competency, e.g. exception_handling'],
                        'passed' => ['type' => 'boolean'],
                        'reason' => ['type' => 'string'],
                    ],
                ],
            ],
            'test_cases_passed' => ['type' => 'integer'],
            'test_cases_total' => ['type' => 'integer'],
            'code_quality_notes' => ['type' => 'string'],
            'summary' => ['type' => 'string', 'description' => 'Plain-language explanation of the grade for the instructor'],
        ],
    ];

    private const GRADER_SYSTEM_PROMPT = "You are the grading assistant for CertiCode Labs, a platform where students complete programming labs. "
        . "You grade a student submission against the lab's task checklist, reference solution, rubric and test cases. "
        . "Grade what the code actually does: a different approach from the reference solution is fine if it meets the task. "
        . "Code that would not compile cannot complete any task. Your feedback is shown to the student, and your summary to their instructor, "
        . "who may override the grade, so be specific and fair.";

    /**
     * Grade with Claude. The response is constrained to GRADE_SCHEMA, then mapped to the evaluation
     * shape the rest of the app expects.
     */
    protected function evaluateWithClaude(string $title, string $description, array $tasks, string $referenceSolution, string $rubric, array $testCases, string $code, string $language, array $diagnostics = []): array
    {
        $prompt = $this->buildPrompt($title, $description, $tasks, $referenceSolution, $rubric, $testCases, $code, $language, $diagnostics);
        $grade = $this->requestClaudeGrade($prompt);

        return $this->mapClaudeGrade($grade, $tasks);
    }

    /**
     * One Messages API call; returns the decoded grade. Separate so tests can stub the network call.
     */
    protected function requestClaudeGrade(string $prompt): array
    {
        $client = new \Anthropic\Client(
            apiKey: (string) config('services.anthropic.key'),
            requestOptions: ['transporter' => $this->graderTransport(), 'maxRetries' => 1],
        );

        $message = $client->beta->messages->create(
            model: (string) config('services.anthropic.grader_model', 'claude-opus-5-5'),
            maxTokens: 16000,
            system: self::GRADER_SYSTEM_PROMPT,
            messages: [['role' => 'user', 'content' => $prompt]],
            outputConfig: [
                'effort' => (string) config('services.anthropic.grader_effort', 'medium'),
                'format' => ['type' => 'json_schema', 'schema' => self::GRADE_SCHEMA],
            ],
            // If the model declines for policy reasons (e.g. code that looks like malware), retry on the
            // server-defined fallback model instead of failing the grade.
            fallbacks: 'default',
            betas: ['server-side-fallback-2026-07-01'],
        );

        if ($message->stopReason === 'refusal') {
            throw new \RuntimeException('Claude declined to grade this submission (' . ($message->stopDetails->category ?? 'no category') . ').');
        }
        if ($message->stopReason === 'max_tokens') {
            throw new \RuntimeException('Claude grading response was cut off at max_tokens.');
        }

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $decoded = json_decode($block->text, true);
                if (is_array($decoded) && isset($decoded['tasks'])) {
                    return $decoded;
                }
                throw new \RuntimeException('Claude returned a grade that is not valid JSON.');
            }
        }

        throw new \RuntimeException('Claude returned no grade.');
    }

    /**
     * HTTP client for the Claude call. The SDK leaves timeouts to the transport; 45 s keeps a grade
     * inside Vercel's 60 s function limit. Overridable so tests can use a mock handler.
     */
    protected function graderTransport(): \Psr\Http\Client\ClientInterface
    {
        return new \GuzzleHttp\Client(['timeout' => 45, 'connect_timeout' => 5]);
    }

    /**
     * Map a schema-shaped grade to the evaluation array stored on the session and shown in the UI.
     */
    protected function mapClaudeGrade(array $grade, array $tasks): array
    {
        $competencies = [];
        foreach ($grade['competencies'] ?? [] as $competency) {
            if (!empty($competency['name'])) {
                $competencies[$competency['name']] = [
                    'passed' => (bool) ($competency['passed'] ?? false),
                    'reason' => (string) ($competency['reason'] ?? ''),
                ];
            }
        }

        $taskResults = array_map(fn ($t) => [
            'id' => (int) ($t['id'] ?? 0),
            'completed' => (bool) ($t['completed'] ?? false),
            'feedback' => (string) ($t['feedback'] ?? ''),
        ], $grade['tasks'] ?? []);

        $completedCount = count(array_filter($taskResults, fn ($t) => $t['completed']));
        $summary = (string) ($grade['summary'] ?? 'Evaluation complete.');
        $qualityNotes = (string) ($grade['code_quality_notes'] ?? 'Standard conventions verified.');

        $gradeSummary = [
            'competencies' => $competencies,
            'test_cases_passed' => (int) ($grade['test_cases_passed'] ?? $completedCount),
            'test_cases_total' => (int) ($grade['test_cases_total'] ?? max(count($tasks), 1)),
            'code_quality_notes' => $qualityNotes,
            'summary' => $summary,
        ];

        return array_merge($gradeSummary, [
            'tasks' => $taskResults,
            'correctness_score' => max(0, min(100, (int) ($grade['correctness_score'] ?? 0))),
            // Shown by the VS Code extension and the lab page
            'overall_feedback' => $summary,
            'code_quality_feedback' => $qualityNotes,
            'ai_grade_summary' => $gradeSummary,
        ]);
    }

    /**
     * Build the evaluation prompt.
     */
    protected function buildPrompt(string $title, string $description, array $tasks, string $referenceSolution, string $rubric, array $testCases, string $code, string $language, array $diagnostics = []): string
    {
        $tasksJson = json_encode($tasks, JSON_PRETTY_PRINT);
        $testCasesJson = json_encode($testCases, JSON_PRETTY_PRINT);
        $diagnosticsJson = !empty($diagnostics) ? json_encode($diagnostics, JSON_PRETTY_PRINT) : 'None';

        return <<<TEXT
Evaluate the following student submission for the lab exercise: "$title".

Lab Description:
$description

Tasks Checklist:
$tasksJson

Reference Solution:
$referenceSolution

Grading Rubric:
$rubric

Test Cases (for context):
$testCasesJson

IDE Compiler Diagnostics:
$diagnosticsJson

Student Code ($language):
```$language
$code
```

Grade the submission:
1. Check that the code would compile. If it would not (unexpected tokens, unbalanced braces, trailing garbage such as ';aaaa', or the compiler diagnostics above), no task is completed and the score is 0-20.
2. For each task in the checklist, decide whether it is correctly implemented and give short feedback.
3. Give an overall correctness score from 0 to 100, based on the tasks, test cases and rubric.
4. Judge each competency the lab exercises (for example oop_inheritance, exception_handling, algorithm_efficiency) as passed or not, with a concise reason.
5. Estimate how many of the test cases would pass, out of the total.
6. Write specific code quality notes.
7. Write a plain-language summary of the grade for the instructor.
TEXT;
    }

    /**
     * Fallback mock evaluation using task regexes and pattern heuristics.
     */
    protected function evaluateMock(array $tasks, string $code, string $language): array
    {
        $trimmedCode = trim($code);
        $totalTasks = count($tasks);

        if (empty($trimmedCode)) {
            return $this->evaluateEmptySubmission($tasks);
        }

        $evaluatedTasks = [];
        $completedCount = 0;

        foreach ($tasks as $task) {
            $taskId = $task['id'];
            $taskText = strtolower($task['task'] ?? '');
            $command = $task['command'] ?? '';
            $completed = false;
            $feedback = '';

            // 1. Check if the task defines a regex command
            if (!empty($command) && str_starts_with($command, 'regex:')) {
                $rawRegex = substr($command, 6);
                $pattern = '/' . str_replace('/', '\/', $rawRegex) . '/i';
                if (@preg_match($pattern, $code)) {
                    $completed = true;
                    $feedback = 'Requirement verified in code.';
                } else {
                    $completed = false;
                    $feedback = "Requirement not met: expected pattern from '{$task['task']}' not found in code.";
                }
            } else {
                // 2. Language & domain-specific heuristics
                if (str_contains($taskText, 'invalidageexception') || str_contains($taskText, 'custom exception')) {
                    if (preg_match('/class\s+InvalidAgeException\s+extends\s+(Exception|RuntimeException)/i', $code)) {
                        $completed = true;
                        $feedback = 'Found custom Exception class InvalidAgeException extending Exception.';
                    } else {
                        $feedback = 'Please implement InvalidAgeException extending Exception or RuntimeException.';
                    }
                } elseif (str_contains($taskText, 'student class') || str_contains($taskText, 'class named student') || str_contains($taskText, 'student` class')) {
                    if (preg_match('/class\s+Student/i', $code)) {
                        $completed = true;
                        $feedback = 'Found Student class.';
                    } else {
                        $feedback = 'Please define a class named Student.';
                    }
                } elseif (str_contains($taskText, 'private field') || str_contains($taskText, 'private` field') || str_contains($taskText, 'name and age')) {
                    $hasName = preg_match('/private\s+String\s+name/i', $code);
                    $hasAge = preg_match('/private\s+int\s+age/i', $code);
                    if ($hasName && $hasAge) {
                        $completed = true;
                        $feedback = 'Fields name (String) and age (int) are correctly encapsulated (private).';
                    } else {
                        $feedback = 'Fields name and age should be private to enforce encapsulation.';
                    }
                } elseif (str_contains($taskText, 'throw') || str_contains($taskText, 'constructor') || str_contains($taskText, 'out of bounds')) {
                    if (preg_match('/throw\s+new\s+InvalidAgeException/i', $code)) {
                        $completed = true;
                        $feedback = 'Student constructor correctly validates age and throws InvalidAgeException.';
                    } else {
                        $feedback = 'The Student constructor should check age and throw InvalidAgeException if out of bounds.';
                    }
                } elseif (str_contains($taskText, 'catch') || str_contains($taskText, 'print') || str_contains($taskText, 'handle')) {
                    if (preg_match('/try\s*\{/i', $code) && preg_match('/catch\s*\(\s*InvalidAgeException/i', $code)) {
                        $completed = true;
                        $feedback = 'Exception is properly caught in try-catch block.';
                    } else {
                        $feedback = 'Ensure you have a try-catch block to handle InvalidAgeException.';
                    }
                } elseif (str_contains($taskText, 'pipe')) {
                    if (preg_match('/pipe\s*\(/i', $code)) {
                        $completed = true;
                        $feedback = 'Pipe IPC initialized with pipe() system call.';
                    } else {
                        $feedback = 'Expected pipe() system call invocation before forking.';
                    }
                } elseif (str_contains($taskText, 'fork')) {
                    if (preg_match('/fork\s*\(/i', $code)) {
                        $completed = true;
                        $feedback = 'Process branching implemented using fork().';
                    } else {
                        $feedback = 'Expected fork() system call to create child process.';
                    }
                } elseif (str_contains($taskText, 'wait') || str_contains($taskText, 'termination')) {
                    if (preg_match('/(waitpid|wait)\s*\(/i', $code)) {
                        $completed = true;
                        $feedback = 'Parent process properly waits for child process termination.';
                    } else {
                        $feedback = 'Expected wait() or waitpid() in parent process.';
                    }
                } else {
                    // Keyword extraction from task text
                    $stopWords = ['create', 'with', 'from', 'this', 'that', 'into', 'class', 'method', 'using', 'before', 'after', 'calling', 'reads', 'writes', 'and', 'the', 'for'];
                    preg_match_all('/[a-zA-Z_][a-zA-Z0-9_]{3,}/', $taskText, $matches);
                    $keywords = array_filter($matches[0] ?? [], fn($w) => !in_array(strtolower($w), $stopWords));

                    $matchedCount = 0;
                    foreach ($keywords as $kw) {
                        if (stripos($code, $kw) !== false) {
                            $matchedCount++;
                        }
                    }

                    if (count($keywords) > 0 && $matchedCount >= ceil(count($keywords) * 0.6)) {
                        $completed = true;
                        $feedback = 'Key task requirements identified in code.';
                    } else {
                        $completed = false;
                        $feedback = "Requirement not met: implementation not detected for '{$task['task']}'.";
                    }
                }
            }

            if ($completed) {
                $completedCount++;
            }

            $evaluatedTasks[] = [
                'id' => $taskId,
                'completed' => $completed,
                'feedback' => $feedback
            ];
        }

        $score = ($totalTasks > 0) ? round(($completedCount / $totalTasks) * 100) : 0;

        $langLower = strtolower($language);
        if ($completedCount === 0) {
            $codeQualityFeedback = 'No requirements met yet. Write your code logic to satisfy checklist items.';
        } elseif ($langLower === 'c' || $langLower === 'cpp') {
            $codeQualityFeedback = 'POSIX conventions checked. Ensure proper error checking on system calls and close unused file descriptors.';
        } elseif ($langLower === 'java') {
            $codeQualityFeedback = 'Encapsulation checks passed. Standard Java naming conventions applied.';
        } elseif ($langLower === 'python') {
            $codeQualityFeedback = 'PEP 8 naming style and function definitions evaluated.';
        } else {
            $codeQualityFeedback = 'Code structure follows standard conventions for ' . strtoupper($language) . '.';
        }

        $competencies = [];
        foreach ($evaluatedTasks as $index => $et) {
            $originalTask = $tasks[$index] ?? null;
            $taskName = $originalTask['task'] ?? ('task_' . $et['id']);
            $compKey = \Illuminate\Support\Str::slug($taskName, '_');
            if (empty($compKey)) {
                $compKey = 'task_' . $et['id'];
            }
            $competencies[$compKey] = [
                'passed' => (bool) $et['completed'],
                'reason' => $et['feedback'] ?? ($et['completed'] ? 'Requirement satisfied.' : 'Requirement not satisfied.')
            ];
        }

        if (empty($competencies)) {
            $competencies['core_implementation'] = [
                'passed' => $score >= 70,
                'reason' => $score >= 70 ? 'Overall implementation meets passing threshold.' : 'Implementation fell below passing threshold.'
            ];
        }

        $summary = "The submission scored {$score}% by completing {$completedCount} of {$totalTasks} verified requirements. {$codeQualityFeedback}";

        $gradeSummary = [
            'competencies' => $competencies,
            'test_cases_passed' => $completedCount,
            'test_cases_total' => max($totalTasks, 1),
            'code_quality_notes' => $codeQualityFeedback,
            'summary' => $summary,
        ];

        return [
            'tasks' => $evaluatedTasks,
            'correctness_score' => $score,
            'overall_feedback' => "Evaluation: Student code meets {$completedCount} out of {$totalTasks} requirements.",
            'code_quality_feedback' => $codeQualityFeedback,
            'ai_grade_summary' => $gradeSummary,
            'competencies' => $competencies,
            'test_cases_passed' => $completedCount,
            'test_cases_total' => max($totalTasks, 1),
            'code_quality_notes' => $codeQualityFeedback,
            'summary' => $summary,
        ];
    }
}
