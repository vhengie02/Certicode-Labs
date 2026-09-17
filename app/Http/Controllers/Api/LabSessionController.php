<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\LabSessionChat;
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
    public function startSession(Request $request, int $labId)
    {
        $lab = Laboratory::findOrFail($labId);
        $user = $request->user();

        // Feature 9: Live Lab availability gating
        if ($lab->isLiveLab()) {
            $lab->checkAndAutoCloseLive();

            if ($lab->isLiveNotStarted()) {
                return response()->json([
                    'error' => 'live_not_started',
                    'message' => 'This Live Lab has not been opened by the instructor yet. Please wait for the instructor to manually start the session.',
                ], 403);
            }

            if ($lab->isLiveClosed() || $lab->getRemainingLiveSeconds() <= 0) {
                return response()->json([
                    'error' => 'live_expired',
                    'message' => 'The countdown for this Live Lab has expired. Late joiners are not permitted to start.',
                ], 403);
            }
        }

        // Find or create session
        $session = LabSession::where('lab_id', $lab->id)
            ->where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->latest()
            ->first();

        // If session exists but has exceeded its time limit, mark it abandoned and start fresh
        if ($session && $lab->time_limit > 0) {
            $elapsed = $session->started_at ? (int) $session->started_at->diffInSeconds(now(), true) : 0;
            if ($elapsed >= ($lab->time_limit * 60)) {
                $session->update([
                    'status' => 'abandoned',
                    'ended_at' => now(),
                ]);
                $session = null;
            }
        }

        if (!$session) {
            $session = LabSession::create([
                'lab_id' => $lab->id,
                'user_id' => $user->id,
                'status' => 'in_progress',
                'started_at' => now(),
                'performance_score' => 0.0,
            ]);
        }

        return response()->json([
            'message' => 'Lab session started.',
            'session' => $session->load('laboratory'),
        ]);
    }

    /**
     * Submit real-time telemetry logs (tab switches, webcam face detection status).
     */
    public function submitTelemetry(Request $request, int $sessionId)
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
        if ($request->event_type === 'tab_switch' || $request->event_type === 'focus_lost') {
            if ($request->event_type === 'focus_lost') {
                $session->increment('focus_lost_count');
            }

            // Count recent switches / focus losses in last 2 minutes
            $recentSwitches = TelemetryLog::where('lab_session_id', $session->id)
                ->whereIn('event_type', ['tab_switch', 'focus_lost'])
                ->where('created_at', '>=', now()->subMinutes(2))
                ->count();

            if ($recentSwitches >= 3) {
                Anomaly::create([
                    'lab_session_id' => $session->id,
                    'type' => $request->event_type === 'focus_lost' ? 'excessive_focus_loss' : 'excessive_tab_switch',
                    'severity' => $recentSwitches >= 6 ? 'high' : 'medium',
                    'description' => "Student switched window/focus {$recentSwitches} times within the last 2 minutes.",
                    'metadata' => $request->payload,
                ]);
            }
        }

        if ($request->event_type === 'wpm_update') {
            $wpm = (int) ($request->payload['wpm'] ?? 0);
            $keystrokes = (int) ($request->payload['keystroke_count'] ?? 0);
            $session->update([
                'wpm' => $wpm,
                'keystroke_count' => $keystrokes > 0 ? $keystrokes : $session->keystroke_count,
            ]);
        }

        if ($request->event_type === 'paste_anomaly') {
            $session->increment('paste_anomaly_count');
            $pastedLen = (int) ($request->payload['pasted_length'] ?? 0);
            $snippet = (string) ($request->payload['snippet'] ?? '');

            Anomaly::create([
                'lab_session_id' => $session->id,
                'type' => 'paste_anomaly',
                'severity' => $pastedLen > 100 ? 'high' : 'medium',
                'description' => 'Unusual external code paste detected (' . $pastedLen . ' chars): ' . \Illuminate\Support\Str::limit($snippet, 70),
                'metadata' => $request->payload,
            ]);
        }

        if ($request->event_type === 'camera_absence') {
            $imagePath = $this->storeSnapshotImage(
                $session->id,
                $request->payload['image_base64'] ?? null,
                $request->payload['image_path'] ?? null
            );

            Anomaly::create([
                'lab_session_id' => $session->id,
                'type' => 'no_face',
                'severity' => 'high',
                'description' => 'Camera presence check failed during active lab session.',
                'image_path' => $imagePath,
                'metadata' => $request->payload,
            ]);
        }

        if ($request->event_type === 'webcam_check') {
            $faceCount = $request->payload['face_count'] ?? 1;
            $imagePath = $this->storeSnapshotImage(
                $session->id,
                $request->payload['image_base64'] ?? null,
                $request->payload['image_path'] ?? null
            );

            if ($faceCount === 0) {
                Anomaly::create([
                    'lab_session_id' => $session->id,
                    'type' => 'no_face',
                    'severity' => 'high',
                    'description' => 'No face detected in front of the camera during webcam check.',
                    'image_path' => $imagePath,
                    'metadata' => $request->payload,
                ]);
            } elseif ($faceCount > 1) {
                Anomaly::create([
                    'lab_session_id' => $session->id,
                    'type' => 'multiple_faces',
                    'severity' => 'medium',
                    'description' => 'Multiple faces detected in front of the camera.',
                    'image_path' => $imagePath,
                    'metadata' => $request->payload,
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
    public function executeCode(Request $request, int $sessionId)
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
    public function syncGithubContributions(Request $request, int $sessionId)
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

    /**
     * Get active laboratory session information.
     */
    public function getSession(Request $request, int $sessionId)
    {
        $session = LabSession::with(['laboratory', 'user', 'group.members'])->findOrFail($sessionId);
        $lab = $session->laboratory;

        $isLiveExpired = false;
        if ($lab && $lab->isLiveLab()) {
            $lab->checkAndAutoCloseLive();
            $remaining = $lab->getRemainingLiveSeconds();
            $isLiveExpired = ($lab->live_status === 'closed' || $remaining <= 0);

            if ($isLiveExpired && $session->status === 'in_progress') {
                $session->update([
                    'status' => 'completed',
                    'ended_at' => $session->ended_at ?: now(),
                    'closed_at' => now(),
                ]);
            }

            $timeRemainingSeconds = $remaining;
            $elapsedSeconds = $lab->getLiveElapsedSeconds();
            $timeLimitSeconds = $lab->getLiveTotalDurationSeconds();
        } else {
            $timeLimitSeconds = ($lab->time_limit ?? 0) * 60;
            $endTime = $session->ended_at ?? now();
            $elapsedSeconds = ($session->started_at && $endTime->gte($session->started_at))
                ? (int) $session->started_at->diffInSeconds($endTime, true)
                : 0;
            $timeRemainingSeconds = $timeLimitSeconds > 0 ? max(0, $timeLimitSeconds - $elapsedSeconds) : 0;
        }

        $colors = ['#3ecf8e', '#38bdf8', '#f59e0b', '#a855f7', '#ec4899', '#10b981', '#6366f1'];
        $currentUser = $request->user() ?? $session->user;
        $userId = $currentUser ? $currentUser->id : 1;
        $userName = $currentUser ? $currentUser->name : 'Student';
        $userColor = $colors[$userId % count($colors)];

        $teammates = [];
        if ($session->group && $session->group->members) {
            foreach ($session->group->members as $member) {
                $mColor = $colors[$member->id % count($colors)];
                $nameParts = explode(' ', $member->name);
                $initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
                $teammates[] = [
                    'id' => $member->id,
                    'name' => $member->name,
                    'initials' => $initials ?: 'ST',
                    'avatar_color' => $mColor,
                    'contribution_score' => (float) ($member->pivot->contribution_score ?? 0.0),
                ];
            }
        }

        return response()->json([
            'session_id' => $session->id,
            'status' => $session->status,
            'started_at' => $session->started_at,
            'elapsed_seconds' => $elapsedSeconds,
            'time_remaining_seconds' => $timeRemainingSeconds,
            'time_limit_minutes' => $lab->isLiveLab() ? (int) ($lab->getLiveTotalDurationSeconds() / 60) : ($lab->time_limit ?? 0),
            'availability_mode' => $lab->availability_mode ?? 'open',
            'live_status' => $lab->live_status ?? 'not_started',
            'is_live_expired' => $isLiveExpired,
            'shared_countdown' => (bool) $lab->isLiveLab(),
            'live_started_at' => $lab->live_started_at,
            'live_duration_minutes' => $lab->live_duration_minutes ?? $lab->time_limit ?? 60,
            'performance_score' => $session->performance_score,
            'completed_tasks' => $session->completed_tasks ?? [],
            'diff_stats' => $session->diff_stats ?? ['lines_added' => 0, 'lines_deleted' => 0, 'lines_modified' => 0],
            'code_contributions' => $session->code_contributions ?? [],
            'current_user' => [
                'id' => $userId,
                'name' => $userName,
                'initials' => strtoupper(substr($userName, 0, 2)),
                'avatar_color' => $userColor,
            ],
            'is_group_lab' => (bool) $lab->is_group_lab,
            'teammates' => $teammates,
            'laboratory' => [
                'id' => $lab->id,
                'title' => $lab->title,
                'description' => $lab->description,
                'is_group_lab' => (bool) $lab->is_group_lab,
                'availability_mode' => $lab->availability_mode ?? 'open',
                'live_status' => $lab->live_status ?? 'not_started',
                'live_duration_minutes' => $lab->live_duration_minutes ?? $lab->time_limit ?? 60,
                'tasks_definition' => $lab->tasks_definition ?? [],
                'starter_files' => $lab->getStarterFilesList(),
            ]
        ]);
    }

    /**
     * Run an AI-based check progress check-in-progress assessment.
     */
    public function checkProgress(Request $request, int $sessionId)
    {
        $session = LabSession::with('laboratory')->findOrFail($sessionId);

        $request->validate([
            'code' => 'nullable|string',
            'files' => 'nullable|array',
            'language' => 'required|string',
        ]);

        $code = $request->code;
        if (empty($code) && !empty($request->input('files')) && is_array($request->input('files'))) {
            foreach ($request->input('files') as $f) {
                if (!empty($f['is_primary']) && !empty($f['content'])) {
                    $code = $f['content'];
                    break;
                }
            }
            if (empty($code) && count($request->input('files')) > 0) {
                $code = $request->input('files')[0]['content'] ?? '';
            }
        }
        $code = $code ?? '';

        $evaluationService = app(\App\Services\LlmEvaluationService::class);
        $evaluation = $evaluationService->evaluate($session, $code, $request->language);

        $completedTasks = [];
        if (isset($evaluation['tasks']) && is_array($evaluation['tasks'])) {
            foreach ($evaluation['tasks'] as $taskEval) {
                if (!empty($taskEval['completed'])) {
                    $completedTasks[] = (int) $taskEval['id'];
                }
            }
        }

        $session->update([
            'completed_tasks' => $completedTasks,
            'performance_score' => (float) ($evaluation['correctness_score'] ?? $session->performance_score),
        ]);

        TelemetryLog::create([
            'lab_session_id' => $session->id,
            'event_type' => 'check_progress',
            'payload' => [
                'language' => $request->language,
                'correctness_score' => $evaluation['correctness_score'] ?? null,
                'completed_tasks_count' => count($completedTasks),
            ],
        ]);

        return response()->json([
            'status' => 'success',
            'completed_tasks' => $completedTasks,
            'performance_score' => $session->performance_score,
            'evaluation' => $evaluation,
        ]);
    }

    /**
     * Submit session, execute code, grade, map competencies, and mark completed.
     */
    public function submitSession(Request $request, int $sessionId)
    {
        $session = LabSession::with('laboratory')->findOrFail($sessionId);

        $request->validate([
            'code' => 'nullable|string',
            'files' => 'nullable|array',
            'language' => 'required|string',
        ]);

        $code = $request->code;
        if (empty($code) && !empty($request->input('files')) && is_array($request->input('files'))) {
            foreach ($request->input('files') as $f) {
                if (!empty($f['is_primary']) && !empty($f['content'])) {
                    $code = $f['content'];
                    break;
                }
            }
            if (empty($code) && count($request->input('files')) > 0) {
                $code = $request->input('files')[0]['content'] ?? '';
            }
        }
        $code = $code ?? '';

        // Execute code
        $executionResult = $this->sandboxService->execute($code, $request->language);

        // AI task-completion evaluation
        $evaluationService = app(\App\Services\LlmEvaluationService::class);
        $evaluation = $evaluationService->evaluate($session, $code, $request->language);

        $completedTasks = [];
        if (isset($evaluation['tasks']) && is_array($evaluation['tasks'])) {
            foreach ($evaluation['tasks'] as $taskEval) {
                if (!empty($taskEval['completed'])) {
                    $completedTasks[] = (int) $taskEval['id'];
                }
            }
        }

        $finalScore = (float) ($evaluation['correctness_score'] ?? 0.0);
        $session->update([
            'status' => 'completed',
            'ended_at' => now(),
            'completed_tasks' => $completedTasks,
            'performance_score' => $finalScore,
        ]);

        // Map competencies
        $this->mapCompetencies($session, $finalScore);

        TelemetryLog::create([
            'lab_session_id' => $session->id,
            'event_type' => 'session_completed',
            'payload' => [
                'language' => $request->language,
                'final_score' => $finalScore,
                'completed_tasks_count' => count($completedTasks),
                'execution_status' => $executionResult['status'] ?? 'unknown',
            ],
        ]);

        return response()->json([
            'status' => 'success',
            'completed_tasks' => $completedTasks,
            'performance_score' => $session->performance_score,
            'execution' => $executionResult,
            'evaluation' => $evaluation,
        ]);
    }

    /**
     * Record real-time line diffs and contribution metrics.
     */
    public function recordDiff(Request $request, int $sessionId)
    {
        $session = LabSession::with(['laboratory', 'user'])->findOrFail($sessionId);

        $request->validate([
            'lines_added' => 'required|integer|min:0',
            'lines_deleted' => 'required|integer|min:0',
            'lines_modified' => 'nullable|integer|min:0',
            'files' => 'nullable|array',
            'blame_blocks' => 'nullable|array',
        ]);

        $currentUser = $request->user() ?? $session->user;
        $userId = $currentUser ? $currentUser->id : 1;
        $userName = $currentUser ? $currentUser->name : 'Student';
        $colors = ['#3ecf8e', '#38bdf8', '#f59e0b', '#a855f7', '#ec4899', '#10b981', '#6366f1'];
        $userColor = $colors[$userId % count($colors)];

        $diffStats = [
            'lines_added' => (int) $request->lines_added,
            'lines_deleted' => (int) $request->lines_deleted,
            'lines_modified' => (int) ($request->lines_modified ?? 0),
            'files' => $request->files ?? [],
            'updated_at' => now()->toIso8601String(),
        ];

        // Maintain contribution aggregation per student
        $contributions = $session->code_contributions ?? [];
        $existingIndex = -1;
        foreach ($contributions as $idx => $c) {
            if (($c['user_id'] ?? null) == $userId) {
                $existingIndex = $idx;
                break;
            }
        }

        $userContrib = [
            'user_id' => $userId,
            'name' => $userName,
            'avatar_color' => $userColor,
            'lines_added' => (int) $request->lines_added,
            'lines_deleted' => (int) $request->lines_deleted,
            'lines_modified' => (int) ($request->lines_modified ?? 0),
            'last_active_at' => now()->toIso8601String(),
            'edit_count' => ($existingIndex >= 0 ? ($contributions[$existingIndex]['edit_count'] ?? 1) + 1 : 1),
        ];

        if ($existingIndex >= 0) {
            $contributions[$existingIndex] = $userContrib;
        } else {
            $contributions[] = $userContrib;
        }

        // Calculate total lines across all contributors for percentage
        $totalLines = 0;
        foreach ($contributions as $c) {
            $totalLines += ($c['lines_added'] + $c['lines_modified']);
        }

        foreach ($contributions as &$c) {
            $myLines = $c['lines_added'] + $c['lines_modified'];
            $c['contribution_percent'] = $totalLines > 0 ? round(($myLines / $totalLines) * 100, 1) : 100.0;
        }

        $session->update([
            'diff_stats' => $diffStats,
            'code_contributions' => $contributions,
        ]);

        // If in a group, update pivot contribution_score
        if ($session->group_id && $currentUser) {
            $userPercent = 100.0;
            foreach ($contributions as $c) {
                if ($c['user_id'] == $userId) {
                    $userPercent = $c['contribution_percent'];
                    break;
                }
            }
            \DB::table('group_members')
                ->where('group_id', $session->group_id)
                ->where('user_id', $userId)
                ->update(['contribution_score' => $userPercent]);
        }

        return response()->json([
            'status' => 'success',
            'diff_stats' => $diffStats,
            'code_contributions' => $contributions,
        ]);
    }

    /**
     * Get ephemeral team chats for active session.
     */
    public function getChats(Request $request, int $sessionId)
    {
        $session = LabSession::findOrFail($sessionId);

        $chats = LabSessionChat::where('lab_session_id', $session->id)
            ->orderBy('id', 'asc')
            ->limit(100)
            ->get()
            ->map(function ($chat) {
                $nameParts = explode(' ', $chat->user_name);
                $initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
                return [
                    'id' => $chat->id,
                    'user_id' => $chat->user_id,
                    'user_name' => $chat->user_name,
                    'initials' => $initials ?: 'ST',
                    'avatar_color' => $chat->avatar_color,
                    'message' => $chat->message,
                    'code_snippet' => $chat->code_snippet,
                    'time' => $chat->created_at ? $chat->created_at->format('H:i') : '',
                    'created_at' => $chat->created_at,
                ];
            });

        return response()->json([
            'status' => 'success',
            'chats' => $chats,
        ]);
    }

    /**
     * Send an ephemeral team chat message.
     */
    public function sendChat(Request $request, int $sessionId)
    {
        $session = LabSession::findOrFail($sessionId);

        $request->validate([
            'message' => 'required|string|max:2000',
            'code_snippet' => 'nullable|string|max:5000',
        ]);

        $currentUser = $request->user() ?? $session->user;
        $userId = $currentUser ? $currentUser->id : 1;
        $userName = $currentUser ? $currentUser->name : 'Student';
        $colors = ['#3ecf8e', '#38bdf8', '#f59e0b', '#a855f7', '#ec4899', '#10b981', '#6366f1'];
        $userColor = $colors[$userId % count($colors)];

        $chat = LabSessionChat::create([
            'lab_session_id' => $session->id,
            'user_id' => $userId,
            'user_name' => $userName,
            'avatar_color' => $userColor,
            'message' => $request->message,
            'code_snippet' => $request->code_snippet,
        ]);

        $nameParts = explode(' ', $chat->user_name);
        $initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));

        return response()->json([
            'status' => 'success',
            'chat' => [
                'id' => $chat->id,
                'user_id' => $chat->user_id,
                'user_name' => $chat->user_name,
                'initials' => $initials ?: 'ST',
                'avatar_color' => $chat->avatar_color,
                'message' => $chat->message,
                'code_snippet' => $chat->code_snippet,
                'time' => $chat->created_at ? $chat->created_at->format('H:i') : now()->format('H:i'),
                'created_at' => $chat->created_at,
            ],
        ]);
    }

    /**
     * Map completed lab tasks to student competencies.
     */
    protected function mapCompetencies(LabSession $session, float $score)
    {
        $user = $session->user;
        if (!$user) {
            return;
        }

        // Create/Update Java competency record
        $competency = \App\Models\Competency::firstOrCreate([
            'code' => 'COMP-JAVA-01',
        ], [
            'name' => 'Java OOP and Custom Exceptions Mastery',
        ]);

        \App\Models\StudentCompetency::updateOrCreate([
            'user_id' => $user->id,
            'competency_id' => $competency->id,
        ], [
            'score_achieved' => max($score, 0.0),
        ]);
    }

    /**
     * Retrieve live leaderboard rankings for the laboratory session.
     */
    public function getLeaderboard(int $sessionId)
    {
        $session = LabSession::with('laboratory')->findOrFail($sessionId);

        $sessions = LabSession::with(['user', 'group'])
            ->where('lab_id', $session->lab_id)
            ->get()
            ->map(function ($s) {
                $tasksCount = is_array($s->completed_tasks) ? count($s->completed_tasks) : 0;
                $duration = 0;
                if ($s->started_at) {
                    $end = $s->ended_at ?: now();
                    $duration = $end->diffInSeconds($s->started_at);
                }

                $name = $s->user->name ?? 'Student';
                $nameParts = explode(' ', $name);
                $initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));

                return [
                    'id' => $s->id,
                    'user_id' => $s->user_id,
                    'name' => $name,
                    'initials' => $initials ?: 'ST',
                    'is_team' => (bool) $s->group_id,
                    'group_name' => $s->group->name ?? null,
                    'tasks_completed' => $tasksCount,
                    'status' => $s->status,
                    'elapsed_seconds' => $duration,
                    'elapsed_time' => sprintf('%02d:%02d', floor($duration / 60), $duration % 60),
                    'wpm' => $s->wpm ?? 0,
                    'focus_lost_count' => $s->focus_lost_count ?? 0,
                    'paste_anomaly_count' => $s->paste_anomaly_count ?? 0,
                    'is_current' => (auth()->id() && auth()->id() === $s->user_id),
                ];
            })
            ->sort(function ($a, $b) {
                // Priority 1: Tasks completed desc
                if ($a['tasks_completed'] !== $b['tasks_completed']) {
                    return $b['tasks_completed'] <=> $a['tasks_completed'];
                }
                // Priority 2: Elapsed time asc
                return $a['elapsed_seconds'] <=> $b['elapsed_seconds'];
            })
            ->values()
            ->map(function ($item, $index) {
                $item['rank'] = $index + 1;
                return $item;
            });

        $lab = $session->laboratory;
        $sharedRemaining = null;
        $sharedRemainingFormatted = null;
        if ($lab && $lab->isLiveLab()) {
            $lab->checkAndAutoCloseLive();
            $sharedRemaining = $lab->getRemainingLiveSeconds();
            $sharedRemainingFormatted = sprintf('%02d:%02d', floor($sharedRemaining / 60), $sharedRemaining % 60);
        }

        return response()->json([
            'status' => 'success',
            'availability_mode' => $lab ? ($lab->availability_mode ?? 'open') : 'open',
            'live_status' => $lab ? $lab->live_status : null,
            'shared_time_remaining' => $sharedRemaining,
            'shared_time_remaining_formatted' => $sharedRemainingFormatted,
            'leaderboard' => $sessions,
        ]);
    }

    /**
     * End session and purge ephemeral records.
     */
    public function endSession(int $sessionId)
    {
        $session = LabSession::findOrFail($sessionId);
        $session->update([
            'status' => 'completed',
            'ended_at' => $session->ended_at ?: now(),
            'closed_at' => now(),
        ]);

        // Purge ephemeral chat
        LabSessionChat::where('lab_session_id', $session->id)->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Lab session completed and ephemeral records purged.',
            'session' => $session,
        ]);
    }

    /**
     * Reopen an individual lab session.
     */
    public function reopenSession(int $sessionId)
    {
        $session = LabSession::findOrFail($sessionId);
        $session->update([
            'status' => 'in_progress',
            'ended_at' => null,
            'closed_at' => null,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Lab session reopened.',
            'session' => $session,
        ]);
    }

    /**
     * Manually trigger open Live Lab via API (Feature 9).
     */
    public function openLive(Request $request, int $labId)
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, ['admin', 'instructor'])) {
            return response()->json([
                'error' => 'Unauthorized action. Only instructors or admins can manage Live Labs.',
            ], 403);
        }

        $lab = Laboratory::findOrFail($labId);
        $duration = $request->input('duration_minutes') ? (int) $request->input('duration_minutes') : null;
        $lab->openLive($duration);

        return response()->json([
            'status' => 'success',
            'message' => 'Live Lab opened and shared countdown started.',
            'laboratory' => $lab,
            'remaining_seconds' => $lab->getRemainingLiveSeconds(),
        ]);
    }

    /**
     * Manually end Live Lab via API, auto-submitting student work (Feature 9 & 7A).
     */
    public function endLive(Request $request, int $labId)
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, ['admin', 'instructor'])) {
            return response()->json([
                'error' => 'Unauthorized action. Only instructors or admins can manage Live Labs.',
            ], 403);
        }

        $lab = Laboratory::findOrFail($labId);
        $lab->closeLive();

        return response()->json([
            'status' => 'success',
            'message' => 'Live Lab ended and all active student sessions auto-submitted.',
            'laboratory' => $lab,
        ]);
    }

    /**
     * Reopen Live Lab via API with leftover remaining duration (Feature 9).
     */
    public function reopenLive(Request $request, int $labId)
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, ['admin', 'instructor'])) {
            return response()->json([
                'error' => 'Unauthorized action. Only instructors or admins can manage Live Labs.',
            ], 403);
        }

        $lab = Laboratory::findOrFail($labId);
        $extendMinutes = $request->input('extend_minutes') ?? $request->input('add_minutes');
        $extendMinutes = $extendMinutes ? (int) $extendMinutes : null;
        $success = $lab->reopenLive($extendMinutes);

        if (!$success) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot reopen Live Lab: countdown duration has already fully expired. Live Labs do not grant a fresh duration.',
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Live Lab reopened with remaining leftover countdown.',
            'laboratory' => $lab,
            'remaining_seconds' => $lab->getRemainingLiveSeconds(),
        ]);
    }

    /**
     * Feature 8: Pre-Lab Camera Permission & AI Presence Verification Gate (Session Level)
     */
    public function verifyCamera(Request $request, int $sessionId)
    {
        $session = LabSession::findOrFail($sessionId);

        $status = $request->input('status', 'granted'); // granted, denied, blocked
        $faceCount = (int) $request->input('face_count', 1);
        $imageBase64 = $request->input('image_base64');

        if ($status === 'denied' || $status === 'blocked') {
            TelemetryLog::create([
                'lab_session_id' => $session->id,
                'event_type' => 'webcam_permission_denied',
                'payload' => [
                    'reason' => $request->input('reason', 'Camera access was denied by the user.'),
                    'timestamp' => now()->toISOString(),
                ],
            ]);

            return response()->json([
                'status' => 'error',
                'verified' => false,
                'error' => 'permission_denied',
                'message' => 'Camera permission is required to access and unlock this laboratory workspace.',
            ], 403);
        }

        if ($faceCount === 0) {
            $imagePath = $this->storeSnapshotImage($session->id, $imageBase64);

            Anomaly::create([
                'lab_session_id' => $session->id,
                'type' => 'no_face',
                'severity' => 'high',
                'description' => 'Pre-lab facial verification failed: No face detected in frame.',
                'image_path' => $imagePath,
                'metadata' => ['pre_lab' => true],
            ]);

            return response()->json([
                'status' => 'error',
                'verified' => false,
                'error' => 'no_face_detected',
                'message' => 'No face detected in camera reference frame. Please look directly at the webcam.',
            ], 422);
        }

        if ($faceCount > 1) {
            $imagePath = $this->storeSnapshotImage($session->id, $imageBase64);

            Anomaly::create([
                'lab_session_id' => $session->id,
                'type' => 'multiple_faces',
                'severity' => 'medium',
                'description' => 'Pre-lab facial verification failed: Multiple faces detected.',
                'image_path' => $imagePath,
                'metadata' => ['pre_lab' => true, 'face_count' => $faceCount],
            ]);

            return response()->json([
                'status' => 'error',
                'verified' => false,
                'error' => 'multiple_faces',
                'message' => 'Multiple faces detected in frame. Only the enrolled student may be present.',
            ], 422);
        }

        // Successfully verified
        $imagePath = $this->storeSnapshotImage($session->id, $imageBase64);

        TelemetryLog::create([
            'lab_session_id' => $session->id,
            'event_type' => 'webcam_prelab_verification',
            'payload' => [
                'verified' => true,
                'face_count' => $faceCount,
                'reference_image_path' => $imagePath,
                'verified_at' => now()->toISOString(),
            ],
        ]);

        return response()->json([
            'status' => 'success',
            'verified' => true,
            'reference_image' => $imagePath,
            'message' => 'Camera permission and AI presence verified successfully. Workspace unlocked.',
        ]);
    }

    /**
     * Feature 8: Pre-Lab Camera Permission & AI Presence Verification Gate (Lab Level)
     */
    public function verifyCameraLab(Request $request, int $labId)
    {
        $lab = Laboratory::findOrFail($labId);

        $status = $request->input('status', 'granted'); // granted, denied, blocked
        $faceCount = (int) $request->input('face_count', 1);

        if ($status === 'denied' || $status === 'blocked') {
            return response()->json([
                'status' => 'error',
                'verified' => false,
                'error' => 'permission_denied',
                'message' => 'Camera permission is required to start this laboratory exercise.',
            ], 403);
        }

        if ($faceCount === 0) {
            return response()->json([
                'status' => 'error',
                'verified' => false,
                'error' => 'no_face_detected',
                'message' => 'No face detected in camera reference frame. Please face the webcam directly.',
            ], 422);
        }

        if ($faceCount > 1) {
            return response()->json([
                'status' => 'error',
                'verified' => false,
                'error' => 'multiple_faces',
                'message' => 'Multiple faces detected in frame. Only one student is permitted.',
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'verified' => true,
            'message' => 'Camera permission and AI presence verified. Workspace unlocked.',
        ]);
    }

    /**
     * Store anomaly snapshot image if base64 data is provided.
     */
    protected function storeSnapshotImage(int $sessionId, ?string $imageBase64, ?string $defaultPath = null): ?string
    {
        if (empty($imageBase64)) {
            return $defaultPath;
        }

        try {
            $type = 'jpg';
            if (preg_match('/^data:image\/(\w+);base64,/', $imageBase64, $matches)) {
                $imageBase64 = substr($imageBase64, strpos($imageBase64, ',') + 1);
                $type = strtolower($matches[1]);
            }

            $decoded = base64_decode($imageBase64);
            if ($decoded === false) {
                return $defaultPath;
            }

            $extension = in_array($type, ['png', 'webp', 'jpeg']) ? ($type === 'jpeg' ? 'jpg' : $type) : 'jpg';
            $filename = 'snapshot_' . time() . '_' . \Illuminate\Support\Str::random(6) . '.' . $extension;

            $relativeDir = "anomalies/{$sessionId}";
            $storageDir = storage_path("app/public/{$relativeDir}");

            if (!file_exists($storageDir)) {
                mkdir($storageDir, 0755, true);
            }

            file_put_contents("{$storageDir}/{$filename}", $decoded);

            return "storage/{$relativeDir}/{$filename}";
        } catch (\Throwable $e) {
            return $defaultPath;
        }
    }
}
