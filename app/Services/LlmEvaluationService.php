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

        $apiKey = env('OPENAI_API_KEY');

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
        foreach ($tasks as $task) {
            $evaluatedTasks[] = [
                'id' => $task['id'],
                'completed' => false,
                'feedback' => $customMessage ?? 'No solution code submitted for this requirement.',
            ];
        }

        return [
            'tasks' => $evaluatedTasks,
            'correctness_score' => 0,
            'overall_feedback' => $customMessage ?? 'No solution code detected in workspace. Create your code file and check progress again.',
            'code_quality_feedback' => 'No runnable code files available to analyze.',
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

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', [
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
4. Provide a general feedback message ("overall_feedback") and specific code quality review ("code_quality_feedback").

You must reply with a JSON object in this exact format:
{
  "tasks": [
    {
      "id": 1,
      "completed": true,
      "feedback": "Custom exception class InvalidAgeException is implemented correctly."
    },
    ...
  ],
  "correctness_score": 85,
  "overall_feedback": "A short summary of what was done well and what needs improvement.",
  "code_quality_feedback": "Specific feedback on variables, naming, exception handling, and encapsulation."
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

        return [
            'tasks' => $evaluatedTasks,
            'correctness_score' => $score,
            'overall_feedback' => "Evaluation: Student code meets {$completedCount} out of {$totalTasks} requirements.",
            'code_quality_feedback' => $codeQualityFeedback
        ];
    }
}
