<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\TelemetryLog;
use App\Models\Anomaly;
use App\Services\SandboxExecutionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class LabSessionController extends Controller
{
    protected SandboxExecutionService $sandboxService;

    public function __construct(SandboxExecutionService $sandboxService)
    {
        $this->sandboxService = $sandboxService;
    }

    /**
     * Start a new laboratory session for a student.
     */
    public function startSession(Request $request, $labId)
    {
        $lab = Laboratory::findOrFail($labId);
        $user = $request->user();

        // Find or create session
        $session = LabSession::firstOrCreate([
            'lab_id' => $lab->id,
            'user_id' => $user->id,
            'status' => 'in_progress',
        ], [
            'started_at' => now(),
            'performance_score' => 0.0,
        ]);

        return response()->json([
            'message' => 'Lab session started.',
            'session' => $session->load('laboratory'),
        ]);
    }

    /**
     * Submit real-time telemetry logs (tab switches, webcam face detection status).
     */
    public function submitTelemetry(Request $request, $sessionId)
    {
        $session = LabSession::findOrFail($sessionId);

        $request->validate([
            'event_type' => 'required|string',
            'payload' => 'nullable|array',
        ]);

        // Create log record
        $log = TelemetryLog::create([
            'lab_session_id' => $session->id,
            'event_type' => $request->event_type,
            'payload' => $request->payload,
        ]);

        // Smart anomaly checks
        if ($request->event_type === 'tab_switch') {
            // Count recent tab switches in last 2 minutes
            $recentSwitches = TelemetryLog::where('lab_session_id', $session->id)
                ->where('event_type', 'tab_switch')
                ->where('created_at', '>=', now()->subMinutes(2))
                ->count();

            if ($recentSwitches > 5) {
                Anomaly::create([
                    'lab_session_id' => $session->id,
                    'type' => 'excessive_tab_switch',
                    'severity' => 'medium',
                    'description' => "Student switched browser tabs {$recentSwitches} times within the last 2 minutes.",
                ]);
            }
        }

        if ($request->event_type === 'webcam_check') {
            $faceCount = $request->payload['face_count'] ?? 1;

            if ($faceCount === 0) {
                Anomaly::create([
                    'lab_session_id' => $session->id,
                    'type' => 'no_face',
                    'severity' => 'high',
                    'description' => 'No face detected in front of the camera during webcam check.',
                ]);
            } elseif ($faceCount > 1) {
                Anomaly::create([
                    'lab_session_id' => $session->id,
                    'type' => 'multiple_faces',
                    'severity' => 'medium',
                    'description' => 'Multiple faces detected in front of the camera.',
                ]);
            }
        }

        return response()->json([
            'status' => 'success',
            'log' => $log,
        ]);
    }

    /**
     * Compile/Run student code submissions.
     */
    public function executeCode(Request $request, $sessionId)
    {
        $session = LabSession::findOrFail($sessionId);

        $request->validate([
            'code' => 'required|string',
            'language' => 'required|string',
        ]);

        // Run code via Mock Sandbox
        $result = $this->sandboxService->executeCodeMock($request->code, $request->language);

        // Verify tasks based on the executed code and its output
        $completedTasks = $this->verifyTasks($session, $request->code, $request->language, $result['output'] ?? '');

        // Record execution event in logs
        TelemetryLog::create([
            'lab_session_id' => $session->id,
            'event_type' => 'code_execution',
            'payload' => [
                'language' => $request->language,
                'status' => $result['status'],
                'execution_time_ms' => $result['execution_time_ms'],
            ],
        ]);

        // Merge verification state in response
        $result['completed_tasks'] = $completedTasks;
        $result['performance_score'] = $session->fresh()->performance_score;

        return response()->json($result);
    }

    /**
     * Compare student execution against tasks_definition and update progress.
     */
    protected function verifyTasks(LabSession $session, string $code, string $language, string $output): array
    {
        $lab = $session->laboratory;
        $tasks = $lab->tasks_definition ?? [];
        $completed = $session->completed_tasks ?? [];

        if (empty($tasks)) {
            return $completed;
        }

        $newlyCompleted = [];

        foreach ($tasks as $task) {
            $taskId = $task['id'];
            
            // Skip if already completed
            if (in_array($taskId, $completed)) {
                continue;
            }

            $expectedCommand = trim($task['command'] ?? '');
            
            if (empty($expectedCommand)) {
                continue;
            }

            $cleanedStudentCode = preg_replace('/\s+/', ' ', strtolower(trim($code)));
            $cleanedExpected = preg_replace('/\s+/', ' ', strtolower($expectedCommand));

            $isMatch = false;

            // Simple prefix support for regex
            if (str_starts_with($expectedCommand, 'regex:')) {
                $pattern = substr($expectedCommand, 6);
                if (@preg_match($pattern, $code) || @preg_match('/' . str_replace('/', '\/', $pattern) . '/i', $code)) {
                    $isMatch = true;
                }
            } else {
                if ($cleanedStudentCode === $cleanedExpected || str_contains($cleanedStudentCode, $cleanedExpected)) {
                    $isMatch = true;
                }
            }

            // Fallback: Check if execution output contains the keyword / expected string
            if (!$isMatch && !empty($output)) {
                $cleanedOutput = strtolower(trim($output));
                if (str_contains($cleanedOutput, strtolower($expectedCommand))) {
                    $isMatch = true;
                }
            }

            if ($isMatch) {
                $completed[] = $taskId;
                $newlyCompleted[] = $taskId;
            }
        }

        if (!empty($newlyCompleted)) {
            $completed = array_values(array_unique($completed));
            sort($completed);
            
            $totalTasks = count($tasks);
            $score = ($totalTasks > 0) ? (count($completed) / $totalTasks) * 100 : 0.0;

            $session->update([
                'completed_tasks' => $completed,
                'performance_score' => $score,
            ]);

            TelemetryLog::create([
                'lab_session_id' => $session->id,
                'event_type' => 'task_verified',
                'payload' => [
                    'verified_task_ids' => $newlyCompleted,
                    'current_progress' => count($completed) . '/' . $totalTasks,
                    'performance_score' => $score,
                ],
            ]);
        }

        return $completed;
    }

    /**
     * Read the GitHub API to parse collaboration/contribution stats.
     * (Phase 1 Integration)
     */
    public function syncGithubContributions(Request $request, $sessionId)
    {
        $session = LabSession::findOrFail($sessionId);
        $user = $session->user;

        if (!$session->github_repo_url) {
            return response()->json([
                'error' => 'No GitHub repository linked to this session.'
            ], 400);
        }

        if (!$user->github_username) {
            return response()->json([
                'error' => 'Student does not have a linked GitHub account.'
            ], 400);
        }

        // Parse owner and repo from URL (e.g. https://github.com/owner/repo)
        preg_match('/github\.com\/([^\/]+)\/([^\/]+)/', $session->github_repo_url, $matches);
        if (count($matches) < 3) {
            return response()->json(['error' => 'Invalid GitHub URL format.'], 400);
        }

        $owner = $matches[1];
        $repo = rtrim($matches[2], '.git');

        // Call GitHub API to list contributors / commits
        // Note: For production, a personal access token or OAuth token would be injected in headers
        $response = Http::withHeaders([
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'Certicode-Labs-App',
        ])->get("https://api.github.com/repos/{$owner}/{$repo}/stats/contributors");

        if ($response->failed()) {
            return response()->json([
                'error' => 'Failed to retrieve stats from GitHub. Code: ' . $response->status(),
                'details' => $response->json(),
            ], 500);
        }

        $stats = $response->json();
        $userStats = null;

        // Search for user statistics
        foreach ($stats as $contributor) {
            if (strcasecmp($contributor['author']['login'], $user->github_username) === 0) {
                $userStats = $contributor;
                break;
            }
        }

        if (!$userStats) {
            return response()->json([
                'message' => 'No commits found for GitHub user: ' . $user->github_username,
                'stats' => []
            ]);
        }

        // Calculate metrics
        $commitsCount = $userStats['total'] ?? 0;
        $additions = 0;
        $deletions = 0;

        foreach ($userStats['weeks'] as $week) {
            $additions += $week['a'];
            $deletions += $week['d'];
        }

        // Log sync event in telemetry
        TelemetryLog::create([
            'lab_session_id' => $session->id,
            'event_type' => 'github_sync',
            'payload' => [
                'commits' => $commitsCount,
                'additions' => $additions,
                'deletions' => $deletions,
            ],
        ]);

        return response()->json([
            'message' => 'GitHub contribution stats synced.',
            'stats' => [
                'username' => $user->github_username,
                'commits' => $commitsCount,
                'lines_added' => $additions,
                'lines_deleted' => $deletions,
            ]
        ]);
    }
}
