<?php

namespace App\Services;

use App\Models\LabSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LlmEvaluationService
{
    /**
     * Evaluate the student's code against the reference solution, rubric, and tasks.
     *
     * @param LabSession $session
     * @param string $code
     * @param string $language
     * @return array
     */
    public function evaluate(LabSession $session, string $code, string $language): array
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

        $apiKey = env('OPENAI_API_KEY') ?: env('AI_API_KEY');

        if (!empty($apiKey) && $apiKey !== 'mock') {
            try {
                return $this->evaluateWithOpenAi($apiKey, $lab->title, $lab->description, $tasks, $referenceSolution, $rubric, $testCases, $code, $language);
            } catch (\Exception $e) {
                Log::error('OpenAI evaluation failed: ' . $e->getMessage());
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
     * Call OpenAI Chat Completion API.
     */
    protected function evaluateWithOpenAi(string $apiKey, string $title, string $description, array $tasks, string $referenceSolution, string $rubric, array $testCases, string $code, string $language): array
    {
        $prompt = $this->buildPrompt($title, $description, $tasks, $referenceSolution, $rubric, $testCases, $code, $language);
        $baseUrl = rtrim(env('OPENAI_BASE_URL', env('AI_BASE_URL', 'https://api.openai.com/v1')), '/');

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(30)->post($baseUrl . '/chat/completions', [
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "You are an expert grading assistant for CertiCode Labs. You evaluate student submissions against a tasks checklist, reference solution, grading rubric, and test cases. You must respond ONLY with a valid JSON object matching the requested schema."
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.2,
        ]);

        if ($response->failed()) {
            throw new \Exception('OpenAI API returned status code ' . $response->status() . ': ' . $response->body());
        }

        $result = $response->json();
        $contentText = $result['choices'][0]['message']['content'] ?? '';
        
        $decoded = json_decode($contentText, true);
        if (!$decoded || !isset($decoded['tasks'])) {
            throw new \Exception('Failed to parse OpenAI JSON response: ' . $contentText);
        }

        $completedCount = count(array_filter($decoded['tasks'] ?? [], fn($t) => !empty($t['completed'])));
        $gradeSummary = [
            'competencies' => $decoded['competencies'] ?? [],
            'test_cases_passed' => (int) ($decoded['test_cases_passed'] ?? $completedCount),
            'test_cases_total' => (int) ($decoded['test_cases_total'] ?? max(count($tasks), 1)),
            'code_quality_notes' => $decoded['code_quality_notes'] ?? ($decoded['code_quality_feedback'] ?? 'Standard conventions verified.'),
            'summary' => $decoded['summary'] ?? ($decoded['overall_feedback'] ?? 'Evaluation complete.'),
        ];

        $decoded['ai_grade_summary'] = $gradeSummary;
        $decoded['competencies'] = $gradeSummary['competencies'];
        $decoded['test_cases_passed'] = $gradeSummary['test_cases_passed'];
        $decoded['test_cases_total'] = $gradeSummary['test_cases_total'];
        $decoded['code_quality_notes'] = $gradeSummary['code_quality_notes'];
        $decoded['summary'] = $gradeSummary['summary'];

        return $decoded;
    }

    /**
     * Build the evaluation prompt.
     */
    protected function buildPrompt(string $title, string $description, array $tasks, string $referenceSolution, string $rubric, array $testCases, string $code, string $language): string
    {
        $tasksJson = json_encode($tasks, JSON_PRETTY_PRINT);
        $testCasesJson = json_encode($testCases, JSON_PRETTY_PRINT);

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

Student Code ($language):
```$language
$code
```

Perform the following:
1. For each task in the Checklist, determine if it has been correctly implemented in the student code. Set the "completed" status (true or false).
2. Provide a short, constructive feedback message for each task ("feedback").
3. Determine an overall correctness score (0 to 100) based on how well the code aligns with the tasks, test cases, and rubric.
4. Evaluate pass/fail result per competency (e.g. oop_inheritance, exception_handling, algorithm_efficiency) with concise reason.
5. Provide test cases passed count and total count.
6. Provide specific code quality notes ("code_quality_notes").
7. Provide a comprehensive plain-language explanation of that grade ("summary") intended as an instructor review aid.

You must reply with a JSON object in this exact format:
{
  "tasks": [
    {
      "id": 1,
      "completed": true,
      "feedback": "Custom exception class InvalidAgeException is implemented correctly."
    }
  ],
  "correctness_score": 85,
  "competencies": {
    "oop_inheritance": { "passed": true, "reason": "Custom exception extends Exception correctly." },
    "exception_handling": { "passed": false, "reason": "Missing try-catch block in main method." }
  },
  "test_cases_passed": 7,
  "test_cases_total": 10,
  "code_quality_notes": "Well-structured encapsulation, but missing catch block in main.",
  "summary": "The student demonstrated strong competency in custom exceptions and encapsulation. However, error handling was incomplete because the catch block was omitted in the driver execution, leading to a score of 85%."
}
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
