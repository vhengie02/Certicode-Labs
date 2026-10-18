<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\LabSessionChat;
use App\Models\TelemetryLog;
use App\Models\Anomaly;
use App\Services\SandboxExecutionService;
use App\Support\WebcamSnapshot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class LabSessionController extends Controller
{
    use \App\Http\Controllers\Concerns\AuthorizesClassAccess;

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
        $this->authorizeLabViewer($lab);
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

        if (!$session && !$lab->canStartNewAttempt($user)) {
            return response()->json([
                'error' => 'attempts_exhausted',
                'message' => 'You have used all your attempts for this lab. Ask your instructor if you need another one.',
            ], 403);
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
            'session' => array_merge($session->toArray(), ['laboratory' => $lab->studentPayload()]),
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

        // The lab page going out of view is the expected workflow (students code in VS Code with
        // the page open behind it), so it is logged as its own event and never counted as a
        // switch. Pages loaded before this change still send it as tab_switch.
        $eventType = $request->event_type;
        $pageEvent = $request->input('payload.event');
        if ($eventType === 'tab_switch' && in_array($pageEvent, ['tab_hidden', 'tab_visible'], true)) {
            $eventType = $pageEvent === 'tab_hidden' ? 'lab_page_hidden' : 'lab_page_visible';
        }

        // Create log record. A webcam snapshot is kept once, on the anomaly it proves.
        $logPayload = $request->payload;
        if (is_array($logPayload)) {
            unset($logPayload['image_base64'], $logPayload['image_path']);
        }
        $log = TelemetryLog::create([
            'lab_session_id' => $session->id,
            'event_type' => $eventType,
            'payload' => $logPayload,
        ]);

        // Smart anomaly checks
        if ($eventType === 'tab_switch' || $eventType === 'focus_lost') {
            if ($eventType === 'focus_lost') {
                $session->increment('focus_lost_count');
            }

            // Count recent switches / focus losses in last 2 minutes
            $recentSwitches = TelemetryLog::where('lab_session_id', $session->id)
                ->whereIn('event_type', ['tab_switch', 'focus_lost'])
                ->where('created_at', '>=', now()->subMinutes(2))
                ->count();

            if ($recentSwitches >= 3) {
                $this->recordSwitchAnomaly($session, $eventType === 'focus_lost' ? 'excessive_focus_loss' : 'excessive_tab_switch', $recentSwitches, $request->payload);
            }
        }

        if ($request->event_type === 'wpm_update') {
            $wpm = (int) ($request->payload['wpm'] ?? 0);
            $keystrokes = (int) ($request->payload['keystroke_count'] ?? 0);
            if ($keystrokes > 0 && (!isset($request->payload['wpm']) || $request->payload['wpm'] === null)) {
                $session->keystroke_count = $keystrokes;
                $wpm = $session->calculateOverallWpm();
            }
            $session->update([
                'wpm' => $wpm,
                'keystroke_count' => $keystrokes > 0 ? $keystrokes : $session->keystroke_count,
            ]);
        }

        // Feature 11: Idle Detection Anomaly Interceptor
        if ($request->event_type === 'idle_timeout') {
            $idleMinutes = (int) ($request->payload['idle_minutes'] ?? 10);
            if ($idleMinutes <= 0) {
                $idleMinutes = 10;
            }
            $session->recordIdleAnomaly($idleMinutes, $request->payload);
        }

        if ($request->event_type === 'paste_anomaly') {
            $session->increment('paste_anomaly_count');
            $pastedLen = (int) ($request->payload['pasted_length'] ?? 0);
            $snippet = mb_substr((string) ($request->payload['snippet'] ?? ''), 0, 2000);
            $metadata = $request->payload;
            $metadata['snippet'] = $snippet;

            $this->recordAndBroadcastAnomaly($session, [
                'type' => 'paste_anomaly',
                'severity' => $pastedLen > 100 ? 'high' : 'medium',
                'description' => 'Unusual external code paste detected (' . $pastedLen . ' chars): ' . \Illuminate\Support\Str::limit($snippet, 70),
                'metadata' => $metadata,
            ]);
        }

        if ($request->event_type === 'camera_absence') {
            // In low-light mode the check is a brightness comparison, not face detection,
            // so a miss is less certain and the instructor should see why.
            $lowLight = (bool) ($request->payload['low_light'] ?? false);
            $this->recordCameraAnomaly($session, $request->payload, [
                'type' => 'no_face',
                'severity' => $lowLight ? 'medium' : 'high',
                'description' => $lowLight
                    ? 'Camera presence check failed during active lab session (low-light mode).'
                    : 'Camera presence check failed during active lab session.',
            ]);
        }

        if ($request->event_type === 'prelab_verification_failed') {
            $this->recordCameraAnomaly($session, $request->payload, [
                'type' => 'no_face',
                'severity' => 'high',
                'description' => 'Pre-lab camera presence verification failed after 3 attempts: No face detected.',
            ], ['pre_lab' => true]);
        }

        if ($request->event_type === 'webcam_check') {
            $faceCount = (int) ($request->payload['face_count'] ?? 1);

            if ($faceCount === 0) {
                $this->recordCameraAnomaly($session, $request->payload, [
                    'type' => 'no_face',
                    'severity' => 'high',
                    'description' => 'No face detected in front of the camera during webcam check.',
                ]);
            } elseif ($faceCount > 1) {
                $this->recordCameraAnomaly($session, $request->payload, [
                    'type' => 'multiple_faces',
                    'severity' => 'medium',
                    'description' => 'Multiple faces detected in front of the camera.',
                ]);
            }
        }

        $session->update(['last_ping_at' => now()]);

        return response()->json([
            'status' => 'success',
            'log' => $log,
        ]);
    }

    /**
     * Flag frequent window or focus switching once per 2-minute window: a further switch in
     * the window raises the existing flag's count (and severity) instead of adding a new row.
     */
    protected function recordSwitchAnomaly(LabSession $session, string $type, int $recentSwitches, ?array $payload): void
    {
        $attributes = [
            'severity' => $recentSwitches >= 6 ? 'high' : 'medium',
            'description' => "Student switched window/focus {$recentSwitches} times within the last 2 minutes.",
        ];

        $open = Anomaly::where('lab_session_id', $session->id)
            ->where('type', $type)
            ->where('created_at', '>=', now()->subMinutes(2))
            ->latest('id')
            ->first();

        if ($open) {
            if ($open->severity === 'high') {
                unset($attributes['severity']);
            }
            $open->update($attributes);
            return;
        }

        $this->recordAndBroadcastAnomaly($session, ['type' => $type, 'metadata' => $payload] + $attributes);
    }

    /**
     * Record an anomaly and broadcast it in real time to instructor monitoring.
     */
    protected function recordAndBroadcastAnomaly(LabSession $session, array $attributes): Anomaly
    {
        $anomaly = Anomaly::create(array_merge(['lab_session_id' => $session->id], $attributes));
        try {
            event(new \App\Events\AnomalyDetected($session->id, [
                'id' => $anomaly->id,
                'type' => $anomaly->type,
                'severity' => $anomaly->severity,
                'description' => $anomaly->description,
                'snapshot_url' => $anomaly->snapshot_url,
                'created_at' => $anomaly->created_at ? $anomaly->created_at->toIso8601String() : now()->toIso8601String(),
                'user_id' => $session->user_id,
                'student_name' => $session->user->name ?? 'Student',
            ]));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Failed to broadcast AnomalyDetected: " . $e->getMessage());
        }
        return $anomaly;
    }

    /**
     * Record a camera anomaly with its validated webcam snapshot. Repeats are dropped:
     * a flag without a picture (e.g. the camera was unplugged) is raised once per
     * 5 minutes, and one with a picture at most every 20 seconds.
     */
    protected function recordCameraAnomaly(LabSession $session, ?array $payload, array $attributes, array $extraMetadata = []): ?Anomaly
    {
        $attributes += $this->cameraSnapshotAttributes($payload, $extraMetadata);
        $hasSnapshot = $attributes['image_path'] !== null;

        $isRepeat = Anomaly::where('lab_session_id', $session->id)
            ->where('type', $attributes['type'])
            ->where('created_at', '>=', $hasSnapshot ? now()->subSeconds(20) : now()->subMinutes(5))
            ->exists();

        return $isRepeat ? null : $this->recordAndBroadcastAnomaly($session, $attributes);
    }

    /**
     * Anomaly `image_path` and `metadata` for a camera payload. Only a real JPEG/PNG sent
     * by the student is kept; a client-supplied path is never trusted.
     */
    protected function cameraSnapshotAttributes(?array $payload, array $extraMetadata = []): array
    {
        $payload = $payload ?? [];
        $snapshot = WebcamSnapshot::normalize($payload['image_base64'] ?? null);
        unset($payload['image_base64'], $payload['image_path']);

        if ($snapshot) {
            $payload['image_base64'] = $snapshot;
        }

        return [
            'image_path' => $snapshot ? Anomaly::DB_SNAPSHOT : null,
            'metadata' => array_merge($payload, $extraMetadata),
        ];
    }

    /**
     * Run student code and return its output. Running code never changes the grade: tasks and
     * scores come only from the AI grader in check-progress and submit. (Matching the code or its
     * output against each task's check string used to award tasks here, and since a program can
     * print any string, that let a student forge a 100% score.)
     */
    public function executeCode(Request $request, int $sessionId)
    {
        $session = LabSession::findOrFail($sessionId);

        if ($session->status !== 'in_progress') {
            return response()->json(['error' => 'This lab session is no longer in progress.'], 409);
        }

        $request->validate([
            'code' => 'required|string',
            'language' => 'required|string',
        ]);

        $result = $this->sandboxService->execute($request->code, $request->language);

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

        // Current progress, unchanged by this run
        $result['completed_tasks'] = $session->completed_tasks ?? [];
        $result['performance_score'] = $session->performance_score;

        return response()->json($result);
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
    /**
     * Sign a Pusher private-channel subscription for the VS Code extension, which has no web login.
     * Access to the session is already checked by the lab.session middleware (session token or user);
     * only that session's own channels can be signed.
     */
    public function authorizeBroadcast(Request $request, int $sessionId)
    {
        if (!\App\Support\Realtime::enabled()) {
            return response()->json(['error' => 'Live updates are not enabled.'], 404);
        }

        $validated = $request->validate([
            'socket_id' => ['required', 'string', 'regex:/^\d+\.\d+$/'],
            'channel_name' => ['required', 'string'],
        ]);

        $allowed = ["private-lab-session.{$sessionId}", "private-lab-session.{$sessionId}.chat"];
        if (!in_array($validated['channel_name'], $allowed, true)) {
            return response()->json(['error' => 'This channel does not belong to the session.'], 403);
        }

        return response()->json(\App\Support\Realtime::authorize($validated['channel_name'], $validated['socket_id']));
    }

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

        if ($session->status === 'in_progress') {
            $session->update(['last_ping_at' => now()]);
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

        $isVscodeSource = $request->input('source') === 'vscode' 
            || $request->header('X-Client') === 'vscode'
            || $request->input('client') === 'vscode_extension';

        if ($isVscodeSource) {
            $session->update(['last_ping_at' => now()]);
            TelemetryLog::create([
                'lab_session_id' => $session->id,
                'event_type' => 'vscode_ping',
                'payload' => [
                    'source' => 'vscode',
                    'action' => 'get_session',
                    'extension_version' => $this->extensionVersionFrom($request),
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);
        }

        // Only extension pings count. The browser page pings last_ping_at too, so it can't
        // tell us whether VS Code is still open. A clean shutdown reports itself right away.
        // Read from the telemetry rows, not the cache: on Vercel the cache is per request.
        $vscode = $this->vscodeConnection($session->id);
        $vscodePing = $vscode['last_ping'];
        $vscodeDisconnected = $vscode['disconnected'];
        $isVscodeConnected = !$vscodeDisconnected && $vscodePing && $vscodePing->diffInSeconds(now(), true) < 45;

        $isCameraVerified = TelemetryLog::where('lab_session_id', $session->id)
            ->where('event_type', 'webcam_prelab_verification')
            ->exists();

        return response()->json([
            'session_id' => $session->id,
            'status' => $session->status,
            'camera_verified' => $isCameraVerified,
            'last_ping_at' => $session->last_ping_at ? $session->last_ping_at->toIso8601String() : null,
            'vscode_connected' => $isVscodeConnected,
            'vscode_closed' => $vscodeDisconnected,
            // Server-side age so the browser needn't trust its own clock. The extension pings every 15s.
            'vscode_ping_age_seconds' => $vscodePing ? (int) $vscodePing->diffInSeconds(now(), true) : null,
            ...$this->extensionVersionStatus($vscode['extension_version']),
            'vscode_last_ping_at' => $vscodePing?->toIso8601String(),
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
            'realtime' => \App\Support\Realtime::clientConfig(),
            'current_user' => [
                'id' => $userId,
                'name' => $userName,
                'initials' => strtoupper(substr($userName, 0, 2)),
                'avatar_color' => $userColor,
            ],
            'is_group_lab' => (bool) $lab->is_group_lab,
            'teammates' => $teammates,
            'laboratory' => $lab->studentPayload(),
        ]);
    }

    /**
     * Run an AI-based check progress check-in-progress assessment.
     */
    /**
     * Keep only IDE diagnostics that belong to the submitted files. The extension reports
     * diagnostics for every document VS Code knows about, and an error in an unrelated file
     * (settings.json, another project) must not fail the submission's syntax gate.
     */
    private function submittedFileDiagnostics(Request $request): array
    {
        $diagnostics = array_filter((array) $request->input('diagnostics', []), 'is_array');
        $fileNames = collect((array) $request->input('files', []))
            ->pluck('name')
            ->filter()
            ->map(fn ($name) => ltrim(str_replace('\\', '/', (string) $name), '/'));

        if ($fileNames->isEmpty()) {
            return array_values($diagnostics);
        }

        return array_values(array_filter($diagnostics, function ($diag) use ($fileNames) {
            $file = ltrim(str_replace('\\', '/', (string) ($diag['file'] ?? '')), '/');
            return $file !== '' && $fileNames->contains(fn ($name) => $file === $name || str_ends_with($file, '/' . $name));
        }));
    }

    public function checkProgress(Request $request, int $sessionId)
    {
        $session = LabSession::with('laboratory')->findOrFail($sessionId);

        if (!$session->acceptsWork()) {
            return $this->attemptClosedResponse($session);
        }

        $request->validate([
            'code' => 'nullable|string',
            'files' => 'nullable|array',
            'language' => 'required|string',
            'diagnostics' => 'nullable|array',
        ]);

        return $this->whileGrading($session, fn () => $this->gradeProgress($request, $session));
    }

    private function gradeProgress(Request $request, LabSession $session)
    {
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

        $diagnostics = $this->submittedFileDiagnostics($request);
        $evaluationService = app(\App\Services\LlmEvaluationService::class);
        $evaluation = $evaluationService->evaluate($session, $code, $request->language, $diagnostics);

        $completedTasks = [];
        if (isset($evaluation['tasks']) && is_array($evaluation['tasks'])) {
            foreach ($evaluation['tasks'] as $taskEval) {
                if (!empty($taskEval['completed'])) {
                    $completedTasks[] = (int) $taskEval['id'];
                }
            }
        }

        $session = $this->updateIfInProgress($session, null, [
            'completed_tasks' => $completedTasks,
            'performance_score' => (float) ($evaluation['correctness_score'] ?? $session->performance_score),
            'submitted_code' => $code,
            'submitted_files' => $request->input('files'),
        ]) ?? $session->refresh();

        if ($session->status !== 'in_progress') {
            return $this->attemptClosedResponse($session);
        }

        TelemetryLog::create([
            'lab_session_id' => $session->id,
            'event_type' => 'check_progress',
            'payload' => [
                'language' => $request->language,
                'correctness_score' => $evaluation['correctness_score'] ?? null,
                'completed_tasks_count' => count($completedTasks),
            ],
        ]);

        // Broadcast live leaderboard update
        $this->broadcastLeaderboardUpdate($session);

        $studentEvaluation = \Illuminate\Support\Arr::except($evaluation, [
            'ai_grade_summary',
            'competencies',
            'code_quality_notes',
            'summary',
        ]);

        return response()->json([
            'status' => 'success',
            'completed_tasks' => $completedTasks,
            'performance_score' => $session->performance_score,
            'evaluation' => $studentEvaluation,
        ]);
    }

    /**
     * Submit session, execute code, grade, map competencies, and mark completed.
     */
    public function submitSession(Request $request, int $sessionId)
    {
        $session = LabSession::with('laboratory')->findOrFail($sessionId);

        // A finished, timed-out or live-closed attempt keeps its grade: no regrading
        if (!$session->acceptsWork()) {
            return $this->attemptClosedResponse($session);
        }

        $request->validate([
            'code' => 'nullable|string',
            'files' => 'nullable|array',
            'language' => 'required|string',
            'diagnostics' => 'nullable|array',
        ]);

        return $this->whileGrading($session, fn () => $this->gradeSubmission($request, $session));
    }

    private function gradeSubmission(Request $request, LabSession $session)
    {
        // Whole seconds, to compare with the stored ended_at
        $gradingStartedAt = now()->startOfSecond();
        // Vercel stops the function at 60 s; the grade must be saved well before that
        $deadline = now()->addSeconds(self::SUBMIT_BUDGET_SECONDS);

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

        // Keep the code before grading, so a request cut off mid-grade doesn't lose it.
        // The attempt stays open, so the student can submit again.
        if (!$this->updateIfInProgress($session, null, ['submitted_code' => $code, 'submitted_files' => $request->input('files')])) {
            return $this->attemptClosedResponse($session->refresh());
        }

        // AI task-completion evaluation
        $diagnostics = $this->submittedFileDiagnostics($request);
        $evaluationService = app(\App\Services\LlmEvaluationService::class);
        $evaluation = $evaluationService->evaluate($session, $code, $request->language, $diagnostics);

        $completedTasks = [];
        if (isset($evaluation['tasks']) && is_array($evaluation['tasks'])) {
            foreach ($evaluation['tasks'] as $taskEval) {
                if (!empty($taskEval['completed'])) {
                    $completedTasks[] = (int) $taskEval['id'];
                }
            }
        }

        $finalScore = (float) ($evaluation['correctness_score'] ?? 0.0);
        $completed = $this->updateIfInProgress($session, $gradingStartedAt, [
            'status' => 'completed',
            'ended_at' => now(),
            'completed_tasks' => $completedTasks,
            'performance_score' => $finalScore,
            'ai_grade_summary' => $evaluation['ai_grade_summary'] ?? null,
            'submitted_code' => $code,
            'submitted_files' => $request->input('files'),
        ]);

        if (!$completed) {
            return $this->attemptClosedResponse($session->refresh());
        }
        $session = $completed;

        // Competency scores follow from the update above (LabSession's save hook)

        // The run doesn't affect the grade; it only shows the student their output or compile
        // error. It gets whatever is left of the budget once the grade is saved, or is skipped.
        $secondsLeft = (int) now()->diffInSeconds($deadline, false);
        $executionResult = $secondsLeft >= self::MIN_EXECUTION_SECONDS
            ? $this->sandboxService->execute($code, $request->language, min(25, $secondsLeft))
            : ['status' => 'skipped'];

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

        // Broadcast live leaderboard update
        $this->broadcastLeaderboardUpdate($session);

        // Compare the new submission with the cohort once, here, rather than on every view
        // of the monitoring page
        app(\App\Services\CodeSimilarityService::class)->refreshLabReportQuietly($session->lab_id);

        $labTitle = $session->laboratory->title ?? 'your lab';
        $session->notifyStudents(
            "Lab submitted: {$labTitle}",
            "Your submission for '{$labTitle}' was received and graded. Score: {$finalScore}% (" . count($completedTasks) . ' tasks passed).'
                . (!empty($evaluation['provisional']) ? ' This grade is provisional until your instructor reviews it.' : '')
        );

        $studentEvaluation = \Illuminate\Support\Arr::except($evaluation, [
            'ai_grade_summary',
            'competencies',
            'code_quality_notes',
            'summary',
        ]);

        return response()->json([
            'status' => 'success',
            'completed_tasks' => $completedTasks,
            'performance_score' => $session->performance_score,
            // The extension shows any execution errors as a compile error, so leave out the
            // "code execution is not set up" result: the code was still graded
            'execution' => in_array($executionResult['status'] ?? null, ['unavailable', 'skipped'], true) ? null : $executionResult,
            'evaluation' => $studentEvaluation,
            'summary' => $this->submissionSummary($session->fresh('laboratory')),
        ]);
    }

    /** Seconds a submit may use in all (Vercel stops the function at 60), and the least worth giving a code run. */
    private const SUBMIT_BUDGET_SECONDS = 50;
    private const MIN_EXECUTION_SECONDS = 5;

    /**
     * The answer to grading or submitting an attempt that is no longer open.
     */
    private function attemptClosedResponse(LabSession $session)
    {
        return response()->json([
            'error' => 'session_not_in_progress',
            'message' => $session->status === 'in_progress'
                ? 'Time is up for this lab, so it can no longer be graded.'
                : 'This lab attempt has already ended, so it can no longer be graded.',
            'session_status' => $session->status,
        ], 409);
    }

    /**
     * Grade while holding the session's grading lock, so a check-progress and a submit (or
     * two of either) never grade one attempt at once. The lock is in the database cache
     * store, shared by every serverless instance, and expires on its own if a request dies.
     */
    private function whileGrading(LabSession $session, \Closure $grade)
    {
        $lock = \Illuminate\Support\Facades\Cache::store('database')->lock("lab_session_{$session->id}_grading", 90);

        if (!$lock->get()) {
            return response()->json([
                'error' => 'grading_in_progress',
                'message' => 'This lab is already being graded. Wait for that result before trying again.',
            ], 409);
        }

        try {
            return $grade();
        } finally {
            $lock->release();
        }
    }

    /**
     * Save a grade only while the attempt is still open, checked under a row lock. A submit
     * also lands if the attempt was closed after its grading began (time ran out or the live
     * lab ended mid-grade), since it was sent in time. Returns null when nothing was saved.
     */
    private function updateIfInProgress(LabSession $session, ?\Carbon\CarbonInterface $acceptClosedSince, array $attributes): ?LabSession
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($session, $acceptClosedSince, $attributes) {
            $current = LabSession::whereKey($session->id)->lockForUpdate()->first();

            $open = $current && ($current->status === 'in_progress'
                || ($acceptClosedSince && $current->ended_at && $current->ended_at->gte($acceptClosedSince)));
            if (!$open) {
                return null;
            }

            $current->setRelation('laboratory', $session->laboratory);
            $current->update($attributes);

            return $current;
        });
    }

    /**
     * What the student sees once a lab is submitted: time spent, score, tasks, typing
     * speed, and, in a live lab, their place on the leaderboard.
     */
    private function submissionSummary(LabSession $session): array
    {
        $lab = $session->laboratory;
        $isLive = $lab && $lab->isLiveLab();

        // Time is capped at the limit, the same way the leaderboard counts it
        $timeLimitSeconds = $lab ? ($isLive ? $lab->getLiveTotalDurationSeconds() : ($lab->time_limit ?? 0) * 60) : 0;
        $elapsed = $session->started_at
            ? (int) $session->started_at->diffInSeconds($session->ended_at ?: now(), true)
            : 0;
        if ($timeLimitSeconds > 0 && $elapsed > $timeLimitSeconds) {
            $elapsed = $timeLimitSeconds;
        }

        $place = null;
        $participants = null;
        if ($isLive) {
            $board = collect($this->getLeaderboard($session->id)->getData(true)['leaderboard'] ?? []);
            $entry = $board->firstWhere('id', $session->id) ?? $board->firstWhere('user_id', $session->user_id);
            $place = $entry['rank'] ?? null;
            $participants = $board->count();
        }

        return [
            'time_spent_seconds' => $elapsed,
            'score' => (float) $session->performance_score,
            'tasks_completed' => is_array($session->completed_tasks) ? count($session->completed_tasks) : 0,
            'tasks_total' => is_array($lab?->tasks_definition) ? count($lab->tasks_definition) : 0,
            'wpm' => (int) ($session->calculateOverallWpm() ?: $session->wpm),
            'is_live' => $isLive,
            'place' => $place,
            'participants' => $participants,
        ];
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

        // Broadcast real-time line diff update
        try {
            event(new \App\Events\DiffUpdated($session->id, $diffStats, $contributions));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Failed to broadcast DiffUpdated: " . $e->getMessage());
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

        $chatPayload = [
            'id' => $chat->id,
            'user_id' => $chat->user_id,
            'user_name' => $chat->user_name,
            'initials' => $initials ?: 'ST',
            'avatar_color' => $chat->avatar_color,
            'message' => $chat->message,
            'code_snippet' => $chat->code_snippet,
            'time' => $chat->created_at ? $chat->created_at->format('H:i') : now()->format('H:i'),
            'created_at' => $chat->created_at,
        ];

        // Broadcast real-time ephemeral chat to team channel
        try {
            event(new \App\Events\ChatMessageSent($session->id, $chatPayload));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Failed to broadcast ChatMessageSent: " . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'chat' => $chatPayload,
        ]);
    }

    /**
     * Retrieve live leaderboard rankings for the laboratory session.
     */
    public function getLeaderboard(int $sessionId)
    {
        $session = LabSession::with('laboratory')->findOrFail($sessionId);
        $lab = $session->laboratory;
        $timeLimitSeconds = $lab ? ($lab->isLiveLab() ? $lab->getLiveTotalDurationSeconds() : ($lab->time_limit ?? 0) * 60) : 0;

        $sessions = LabSession::with(['user', 'group'])
            ->where('lab_id', $session->lab_id)
            ->get()
            ->filter(fn($s) => $s->user !== null)
            ->groupBy(function ($s) {
                return $s->group_id ? 'g_' . $s->group_id : 'u_' . $s->user_id;
            })
            ->map(function ($userSessions) use ($timeLimitSeconds) {
                $bestSession = $userSessions->sort(function ($a, $b) {
                    $aTasks = is_array($a->completed_tasks) ? count($a->completed_tasks) : 0;
                    $bTasks = is_array($b->completed_tasks) ? count($b->completed_tasks) : 0;
                    if ($aTasks !== $bTasks) {
                        return $bTasks <=> $aTasks;
                    }
                    $aEnd = $a->ended_at ?: now();
                    $bEnd = $b->ended_at ?: now();
                    $aDur = $a->started_at ? max(0, abs((int) $a->started_at->diffInSeconds($aEnd, true))) : 9999999;
                    $bDur = $b->started_at ? max(0, abs((int) $b->started_at->diffInSeconds($bEnd, true))) : 9999999;
                    if ($aDur !== $bDur) {
                        return $aDur <=> $bDur;
                    }
                    return $b->id <=> $a->id;
                })->first();

                $s = $bestSession;
                $tasksCount = is_array($s->completed_tasks) ? count($s->completed_tasks) : 0;
                $duration = 0;
                if ($s->started_at) {
                    $end = $s->ended_at ?: now();
                    $duration = max(0, abs((int) $s->started_at->diffInSeconds($end, true)));
                    if ($timeLimitSeconds > 0 && $duration > $timeLimitSeconds) {
                        $duration = $timeLimitSeconds;
                    }
                }

                $name = $s->user->name ?? 'Student';
                $nameParts = explode(' ', $name);
                $initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));

                $hours = floor($duration / 3600);
                $mins = floor(($duration % 3600) / 60);
                $secs = $duration % 60;
                $elapsedTime = $hours > 0
                    ? sprintf('%d:%02d:%02d', $hours, $mins, $secs)
                    : sprintf('%02d:%02d', $mins, $secs);

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
                    'elapsed_time' => $elapsedTime,
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
     * Broadcast live leaderboard data over WebSocket.
     */
    protected function broadcastLeaderboardUpdate(LabSession $session)
    {
        try {
            $lbResponse = $this->getLeaderboard($session->id);
            $lbData = $lbResponse->getData(true);
            event(new \App\Events\LeaderboardUpdated($session->id, $lbData));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Failed to broadcast LeaderboardUpdated: " . $e->getMessage());
        }
    }

    /**
     * Leave a lab session (the extension's Exit, the lab page's "stop proctoring").
     *
     * For the student this only records that they left: the attempt stays open, so they can
     * come back and submit, and it ends through submit, time running out or the instructor.
     * Ending it here used to finalize it ungraded, which cost students their only attempt.
     * Staff still end the session outright, as from the monitoring page.
     */
    public function endSession(Request $request, int $sessionId)
    {
        $session = LabSession::findOrFail($sessionId);

        if (!in_array($request->user()?->role, ['admin', 'instructor'], true)) {
            if ($session->status === 'in_progress') {
                TelemetryLog::create([
                    'lab_session_id' => $session->id,
                    'event_type' => 'session_left',
                    'payload' => ['timestamp' => now()->toIso8601String()],
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'You left the lab. Your attempt stays open until you submit it or time runs out.',
                'session' => $session,
            ]);
        }

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
    public function reopenSession(Request $request, int $sessionId)
    {
        // Reopening a completed session allows resubmission, so only staff may do it.
        if (!in_array($request->user()?->role, ['admin', 'instructor'], true)) {
            return response()->json([
                'error' => 'Unauthorized action. Only instructors or admins can reopen a session.',
            ], 403);
        }

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
     * Heartbeat / Liveness ping from student browser or VS Code workspace.
     */
    public function pingSession(Request $request, int $sessionId)
    {
        $session = LabSession::with('laboratory')->find($sessionId);
        if (!$session) {
            return response()->json(['error' => 'Session not found'], 404);
        }

        $lab = $session->laboratory;
        $timeLimit = $lab ? ($lab->time_limit ?: 60) : 60;
        $timeLimitSec = $timeLimit * 60;
        $elapsed = $session->started_at ? (int) $session->started_at->diffInSeconds(now(), true) : 0;

        // Auto-expire if session exceeded laboratory time limit
        if ($session->status === 'in_progress' && $timeLimitSec > 0 && $elapsed >= $timeLimitSec) {
            $session->update([
                'status' => 'abandoned',
                'ended_at' => now(),
                'last_ping_at' => now(),
            ]);
            return response()->json([
                'status' => 'abandoned',
                'is_active' => false,
                'message' => 'Laboratory session time limit has expired.',
                'time_remaining_seconds' => 0,
            ]);
        }

        if ($session->status !== 'in_progress') {
            return response()->json([
                'status' => $session->status,
                'is_active' => false,
                'message' => "Session is {$session->status}.",
                'time_remaining_seconds' => 0,
            ]);
        }

        $isVscode = $request->input('source') === 'vscode' 
            || $request->header('X-Client') === 'vscode'
            || $request->input('client') === 'vscode_extension';

        if ($isVscode) {
            TelemetryLog::create([
                'lab_session_id' => $session->id,
                'event_type' => 'vscode_ping',
                'payload' => [
                    'source' => 'vscode',
                    'extension_version' => $this->extensionVersionFrom($request),
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);
        }

        $updateData = ['last_ping_at' => now()];
        if ($request->has('wpm')) {
            $updateData['wpm'] = (int) $request->input('wpm');
        }
        if ($request->has('keystroke_count')) {
            $updateData['keystroke_count'] = (int) $request->input('keystroke_count');
        }
        $session->update($updateData);

        $remainingSeconds = max(0, $timeLimitSec - $elapsed);
        if ($lab && $lab->isLiveLab()) {
            $remainingSeconds = $lab->getRemainingLiveSeconds();
        }

        return response()->json([
            'status' => 'success',
            'session_id' => $session->id,
            'session_status' => $session->status,
            'connection_state' => 'active',
            'is_active' => true,
            'time_remaining_seconds' => $remainingSeconds,
            'timestamp' => now()->toIso8601String(),
            ...$this->extensionVersionStatus(
                $isVscode ? $this->extensionVersionFrom($request) : $this->vscodeConnection($session->id)['extension_version']
            ),
        ]);
    }

    /**
     * The extension build making this request. Builds before version reporting send no
     * header, which counts as an empty version.
     */
    private function extensionVersionFrom(Request $request): string
    {
        $version = trim((string) $request->header('X-Extension-Version', ''));

        return preg_match('/^\d+(\.\d+){0,3}$/', $version) ? $version : '';
    }

    /**
     * What the extension last told the server about this session, from the telemetry rows
     * (the cache is per request on Vercel): its last ping time, the build that sent it, and
     * whether a clean shutdown came after it.
     *
     * @return array{last_ping: ?\Carbon\Carbon, extension_version: ?string, disconnected: bool}
     */
    private function vscodeConnection(int $sessionId): array
    {
        $lastPing = TelemetryLog::where('lab_session_id', $sessionId)
            ->where('event_type', 'vscode_ping')
            ->latest('id')
            ->first();
        $lastDisconnectId = TelemetryLog::where('lab_session_id', $sessionId)
            ->where('event_type', 'vscode_disconnected')
            ->max('id');

        $disconnected = $lastDisconnectId !== null && (!$lastPing || $lastDisconnectId > $lastPing->id);

        return [
            // created_at is filled in by the database, so the model holds it as a string
            'last_ping' => $disconnected || !$lastPing ? null : \Carbon\Carbon::parse($lastPing->created_at),
            // Pings logged before versions were recorded say nothing about the build
            'extension_version' => $lastPing && is_array($lastPing->payload) && array_key_exists('extension_version', $lastPing->payload)
                ? (string) $lastPing->payload['extension_version']
                : null,
            'disconnected' => $disconnected,
        ];
    }

    /**
     * Whether the extension connected to this session is older than the minimum supported build.
     * Null until the extension has pinged at least once.
     */
    private function extensionVersionStatus(?string $version): array
    {
        $minVersion = (string) config('services.vscode_extension.min_version', '0.0.0');

        return [
            'extension_version' => $version ?: null,
            'extension_min_version' => $minVersion,
            'extension_outdated' => $version === null ? null : ($version === '' || version_compare($version, $minVersion, '<')),
            'extension_download_url' => asset(config('services.vscode_extension.download_path')),
        ];
    }

    /**
     * The VS Code extension reports that its window is closing, so the browser page
     * can leave camera mode without waiting for the ping to go stale.
     */
    public function vscodeDisconnect(int $sessionId)
    {
        $session = LabSession::find($sessionId);
        if (!$session) {
            return response()->json(['error' => 'Session not found'], 404);
        }

        // Counts as closed until the next ping (see vscodeConnection)
        TelemetryLog::create([
            'lab_session_id' => $session->id,
            'event_type' => 'vscode_disconnected',
            'payload' => [
                'source' => 'vscode',
                'timestamp' => now()->toIso8601String(),
            ],
        ]);

        return response()->json(['status' => 'success', 'vscode_connected' => false]);
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
        $this->authorizeLabManager($lab);
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
        $this->authorizeLabManager($lab);
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
        $this->authorizeLabManager($lab);
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
            Anomaly::create([
                'lab_session_id' => $session->id,
                'type' => 'no_face',
                'severity' => 'high',
                'description' => 'Pre-lab facial verification failed: No face detected in frame.',
            ] + $this->cameraSnapshotAttributes(['image_base64' => $imageBase64], ['pre_lab' => true]));

            return response()->json([
                'status' => 'error',
                'verified' => false,
                'error' => 'no_face_detected',
                'message' => 'No face detected in camera reference frame. Please look directly at the webcam.',
            ], 422);
        }

        if ($faceCount > 1) {
            Anomaly::create([
                'lab_session_id' => $session->id,
                'type' => 'multiple_faces',
                'severity' => 'medium',
                'description' => 'Pre-lab facial verification failed: Multiple faces detected.',
            ] + $this->cameraSnapshotAttributes(['image_base64' => $imageBase64], ['pre_lab' => true, 'face_count' => $faceCount]));

            return response()->json([
                'status' => 'error',
                'verified' => false,
                'error' => 'multiple_faces',
                'message' => 'Multiple faces detected in frame. Only the enrolled student may be present.',
            ], 422);
        }

        // Successfully verified. The reference frame isn't kept: nothing reads it, and a
        // webcam picture of a student who passed has no place in storage.
        TelemetryLog::create([
            'lab_session_id' => $session->id,
            'event_type' => 'webcam_prelab_verification',
            'payload' => [
                'verified' => true,
                'face_count' => $faceCount,
                'low_light' => $request->boolean('low_light'),
                'verified_at' => now()->toISOString(),
            ],
        ]);

        \App\Http\Controllers\LabAttemptController::rememberCameraCheck($request, $session->lab_id, $request->boolean('low_light'));

        return response()->json([
            'status' => 'success',
            'verified' => true,
            'message' => 'Camera permission and AI presence verified successfully. Workspace unlocked.',
        ]);
    }

    /**
     * Feature 8: Pre-Lab Camera Permission & AI Presence Verification Gate (Lab Level)
     */
    public function verifyCameraLab(Request $request, int $labId)
    {
        $lab = Laboratory::findOrFail($labId);
        $this->authorizeLabViewer($lab);

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

        \App\Http\Controllers\LabAttemptController::rememberCameraCheck($request, $labId, $request->boolean('low_light'));

        return response()->json([
            'status' => 'success',
            'verified' => true,
            'message' => 'Camera permission and AI presence verified. Workspace unlocked.',
        ]);
    }
}
