<?php

namespace App\Http\Controllers;

use App\Models\Anomaly;
use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\TelemetryLog;
use App\Models\User;
use App\Services\CodeSimilarityService;
use App\Support\CameraCheckFrame;
use App\Support\DeviceLabel;
use App\Support\ServerFaceCount;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * A student's attempt at a lab from the website: starting it (live-lab and camera gates,
 * VS Code deep link), opening and completing the session, and downloading starter files.
 * Instructors create and run the labs themselves in LaboratoryController.
 */
class LabAttemptController extends Controller
{
    use Concerns\AuthorizesClassAccess;

    /**
     * Start a new laboratory session for a student and redirect to VS Code.
     */
    public function startSession(Request $request, int $id)
    {
        $laboratory = Laboratory::findOrFail($id);
        $this->authorizeLabViewer($laboratory);
        /** @var User|null $user */
        $user = auth()->user();

        $laboratory->recordViewBy($user);

        // Labs run in VS Code, so a student on a phone or tablet can read the lab but not start it
        if ($user && $user->role === 'student' && DeviceLabel::isHandheld($request->userAgent())) {
            $message = "Labs run in VS Code, so they can't be started on a phone or tablet. Open this lab on a computer.";
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['error' => 'handheld_device', 'message' => $message], 403);
            }

            return back()->with('error', $message);
        }

        // Feature 9: Live Lab availability gating
        if ($laboratory->isLiveLab()) {
            $laboratory->checkAndAutoCloseLive();

            if ($laboratory->isLiveNotStarted()) {
                return back()->with('error', 'This Live Lab has not been started yet. Please wait for the instructor to manually open the session.');
            }

            if ($laboratory->isLiveClosed() || $laboratory->getRemainingLiveSeconds() <= 0) {
                return back()->with('error', 'The countdown for this Live Lab has expired. Late joiners are not permitted to start.');
            }
        }

        // Feature 8: Pre-Lab Camera Proctoring Verification Gate for Students. Only the server's
        // own record of a recent camera check counts, never a flag the browser sends.
        $cameraCheck = self::recentCameraCheck($laboratory->id);
        $isCameraVerified = $cameraCheck !== null;
        if ($user && $user->role === 'student' && ! $isCameraVerified) {
            $message = 'Webcam presence verification is mandatory before launching the workspace. Please complete the camera check.';
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['error' => 'camera_not_verified', 'message' => $message], 403);
            }

            return back()->with('error', $message);
        }

        // Resume the open attempt or start one (shared with the extension API's start route)
        $session = $laboratory->findOrStartAttemptFor($user);
        if (! $session) {
            $message = 'You have used all your attempts for this lab. Ask your instructor if you need another one.';
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['error' => 'attempts_exhausted', 'message' => $message], 403);
            }

            return back()->with('error', $message);
        }

        // Attach the pre-lab camera check, with its picture, to the attempt
        if ($isCameraVerified) {
            self::recordCameraCheckOn($session, $cameraCheck);
        }

        $backendUrl = request()->getSchemeAndHttpHost();
        $vscodeUrl = "vscode://certicode.certicode-labs/connect?sessionId={$session->id}&endpoint=".urlencode($backendUrl)
            .'&backendUrl='.urlencode($backendUrl).'&token='.urlencode($session->issueExtensionToken());

        if ($request->wantsJson() || $request->ajax() || $request->header('Accept') === 'application/json') {
            return response()->json([
                'status' => 'success',
                'session_id' => $session->id,
                'vscode_url' => $vscodeUrl,
                'endpoint' => $backendUrl,
                'backend_url' => $backendUrl,
            ]);
        }

        return redirect($vscodeUrl);
    }

    /** How long a passed camera check lets the student start the lab. */
    public const CAMERA_CHECK_TTL_MINUTES = 10;

    /** Failed camera checks in a row before the student is locked out of the lab. */
    public const CAMERA_MAX_ATTEMPTS = 3;

    /** How long that lockout lasts. */
    public const CAMERA_BLOCK_MINUTES = 15;

    /**
     * Record a passed camera check in the student's session; the lab start reads it back and
     * moves the picture onto the attempt.
     *
     * @param  array{server_check: string, server_face_count: int|null}|null  $serverCheck  From ServerFaceCount::check()
     */
    public static function rememberCameraCheck(Request $request, int $labId, bool $lowLight, CameraCheckFrame $frame, ?array $serverCheck = null): void
    {
        if ($request->hasSession()) {
            $request->session()->put("camera_verified_lab_{$labId}", [
                'verified_at' => now()->toIso8601String(),
                'low_light' => $lowLight,
                'frame' => self::cameraFramePayload($frame, $serverCheck),
            ]);
            $request->session()->forget("camera_failures_lab_{$labId}");
        }
    }

    /**
     * What a passed check keeps of its picture: the picture itself for the class instructor, a
     * fingerprint to refuse it for a later check, why it needs a look, if it does, and the
     * server's own face count when that is on (`server_check`, `server_face_count`).
     *
     * @param  array{server_check: string, server_face_count: int|null}|null  $serverCheck  From ServerFaceCount::check()
     * @return array<string, mixed>
     */
    public static function cameraFramePayload(CameraCheckFrame $frame, ?array $serverCheck = null): array
    {
        return array_filter([
            'has_picture' => true,
            'image_base64' => $frame->dataUri(),
            'frame_sha256' => $frame->sha256(),
            'frame_notes' => $frame->notes,
        ]) + ($serverCheck ?? []);
    }

    /**
     * Whether this exact picture already passed a camera check of the student's. A webcam never
     * produces the same bytes twice, so a match is a saved picture sent again.
     */
    public static function cameraFrameUsedBefore(?User $user, CameraCheckFrame $frame): bool
    {
        if (! $user) {
            return false;
        }

        return TelemetryLog::where('event_type', TelemetryLog::CAMERA_CHECK_EVENT)
            ->whereIn('lab_session_id', LabSession::where('user_id', $user->id)->select('id'))
            ->where('payload->frame_sha256', $frame->sha256())
            ->exists();
    }

    /**
     * Record the camera check the student passed before starting (or resuming) this attempt,
     * with its picture, and drop the picture from the login session. An attempt that already
     * has a check keeps that one.
     *
     * @param  array{verified_at: string, low_light: bool, frame?: array<string, mixed>}  $cameraCheck
     */
    public static function recordCameraCheckOn(LabSession $session, array $cameraCheck): void
    {
        $frame = $cameraCheck['frame'] ?? [];

        // low_light marks a check made in a dim room; it still found a face.
        $log = TelemetryLog::firstOrCreate([
            'lab_session_id' => $session->id,
            'event_type' => TelemetryLog::CAMERA_CHECK_EVENT,
        ], [
            'payload' => [
                'status' => 'granted',
                'face_count' => 1,
                'low_light' => (bool) ($cameraCheck['low_light'] ?? false),
                'verified_at' => $cameraCheck['verified_at'] ?? now()->toIso8601String(),
            ] + $frame,
        ]);

        if ($log->wasRecentlyCreated) {
            self::flagUnusualCameraCheck($session, $frame);
        }

        $key = "camera_verified_lab_{$session->lab_id}";
        if (is_array(session($key))) {
            session()->put($key, Arr::except(session($key), 'frame'));
        }
    }

    /**
     * Flag an attempt whose camera check passed but needs the instructor's eye, with the
     * picture: one close to blank (low severity, a dark room can produce one too), or one in
     * which the server counted a different number of faces than the browser did (see
     * ServerFaceCount for why that flags rather than fails).
     *
     * @param  array<string, mixed>  $frame  From cameraFramePayload()
     */
    public static function flagUnusualCameraCheck(LabSession $session, array $frame): void
    {
        if (empty($frame['image_base64'])) {
            return;
        }

        if (! empty($frame['frame_notes'])) {
            Anomaly::create([
                'lab_session_id' => $session->id,
                'type' => 'camera_check_unusual',
                'severity' => 'low',
                'description' => 'The pre-lab camera check passed on a picture that is almost blank. Check that it shows the student.',
                'image_path' => Anomaly::DB_SNAPSHOT,
                'metadata' => [
                    'pre_lab' => true,
                    'frame_notes' => $frame['frame_notes'],
                    'image_base64' => $frame['image_base64'],
                ],
            ]);
        }

        if (($frame['server_check'] ?? null) === ServerFaceCount::DISAGREED) {
            $serverCount = (int) $frame['server_face_count'];

            Anomaly::create([
                'lab_session_id' => $session->id,
                'type' => 'camera_check_mismatch',
                // Nobody in the picture could be a dim room; a second person is the bigger worry
                'severity' => $serverCount > 1 ? 'high' : 'medium',
                'description' => $serverCount > 1
                    ? "The pre-lab camera check passed with one face, but the server found {$serverCount} in the picture. Check whether someone else is with the student."
                    : 'The pre-lab camera check passed with one face, but the server found none in the picture. Check that it shows the student.',
                'image_path' => Anomaly::DB_SNAPSHOT,
                'metadata' => [
                    'pre_lab' => true,
                    'face_count' => 1,
                    'server_face_count' => $serverCount,
                    'image_base64' => $frame['image_base64'],
                ],
            ]);
        }
    }

    /**
     * Count a failed camera check. Kept on the server so reloading the page doesn't reset it;
     * in the session because the cache doesn't survive between requests on Vercel.
     *
     * @return array{attempts_used: int, blocked: bool}
     */
    public static function recordFailedCameraCheck(Request $request, int $labId): array
    {
        if (! $request->hasSession()) {
            return ['attempts_used' => 0, 'blocked' => false];
        }

        $key = "camera_failures_lab_{$labId}";
        $attempts = (int) ($request->session()->get($key)['attempts'] ?? 0) + 1;
        $blocked = $attempts >= self::CAMERA_MAX_ATTEMPTS;

        $request->session()->put($key, [
            'attempts' => $attempts,
            'blocked_until' => $blocked ? now()->addMinutes(self::CAMERA_BLOCK_MINUTES)->toIso8601String() : null,
        ]);

        if ($blocked) {
            $request->session()->forget("camera_verified_lab_{$labId}");
        }

        return ['attempts_used' => $attempts, 'blocked' => $blocked];
    }

    /** Minutes left on the student's camera lockout for this lab; 0 when they aren't locked out. */
    public static function cameraBlockMinutesLeft(Request $request, int $labId): int
    {
        if (! $request->hasSession()) {
            return 0;
        }

        $key = "camera_failures_lab_{$labId}";
        $blockedUntil = $request->session()->get($key)['blocked_until'] ?? null;
        if (! $blockedUntil) {
            return 0;
        }

        $secondsLeft = now()->diffInSeconds(Carbon::parse($blockedUntil), false);
        if ($secondsLeft <= 0) {
            $request->session()->forget($key);

            return 0;
        }

        return (int) ceil($secondsLeft / 60);
    }

    /** Failed camera checks counted so far for this lab. An ended lockout starts them over. */
    public static function cameraFailedAttempts(Request $request, int $labId): int
    {
        if (! $request->hasSession()) {
            return 0;
        }

        self::cameraBlockMinutesLeft($request, $labId);

        return (int) ($request->session()->get("camera_failures_lab_{$labId}")['attempts'] ?? 0);
    }

    /**
     * The camera check this browser session passed for the lab in the last few minutes, if any.
     *
     * @return array{verified_at: string, low_light: bool}|null
     */
    public static function recentCameraCheck(int $labId): ?array
    {
        $check = session("camera_verified_lab_{$labId}");
        if (! is_array($check) || empty($check['verified_at'])) {
            return null;
        }

        $verifiedAt = Carbon::parse($check['verified_at']);

        return $verifiedAt->gt(now()->subMinutes(self::CAMERA_CHECK_TTL_MINUTES)) ? $check : null;
    }

    /**
     * An attempt's results page once it has ended: the grade (and the instructor's regrade and
     * reason, if any), the task checklist with the grader's feedback, and the session's stats.
     * An attempt still in progress opens its lab page instead, where the student works on it.
     * Open to whoever the attempt belongs to (the student and their team) and to class staff.
     */
    public function showWorkspace(int $id)
    {
        $session = LabSession::with(['laboratory', 'user', 'group.members', 'overriddenByUser'])->findOrFail($id);
        abort_unless($session->isAccessibleBy(auth()->user()), 403, 'Unauthorized.');

        if ($session->status === 'in_progress') {
            return redirect()->route('laboratories.show', $session->lab_id);
        }

        // A submit sent just before the attempt ended may still be grading: show that rather
        // than a "timed out" or "ended" verdict the grade is about to replace. Only an attempt
        // that ended in the last couple of minutes without a submitted grade can be in that state.
        $mayStillBeGrading = ($session->status === 'abandoned' || $session->ai_grade_summary === null)
            && (! $session->ended_at || $session->ended_at->gt(now()->subMinutes(2)));
        if ($mayStillBeGrading && $session->isBeingGraded()) {
            return view('sessions.grading', ['session' => $session, 'lab' => $session->laboratory]);
        }

        return view('sessions.summary', $this->resultsFor($session));
    }

    /**
     * Everything the results page shows. Only student-safe parts of the grade record are read:
     * the review flag and the student's own feedback, never the instructor's AI summary.
     */
    private function resultsFor(LabSession $session): array
    {
        $lab = $session->laboratory;
        $viewer = auth()->user();
        $isStaff = in_array($viewer->role, ['instructor', 'admin'], true);
        $isOwner = (int) $viewer->id === (int) $session->user_id;

        // The owner's attempts at this lab, oldest first, so each gets its number. A teammate
        // sees only the team's attempts, not the owner's other ones.
        $attempts = LabSession::where('lab_id', $session->lab_id)
            ->where('user_id', $session->user_id)
            ->when(! $isOwner && ! $isStaff, fn ($q) => $q->where('group_id', $session->group_id))
            ->orderBy('id')
            ->get(['id', 'lab_id', 'user_id', 'status', 'performance_score', 'instructor_grade_override', 'started_at', 'ended_at']);
        // Only completed attempts count, and the best grade among them is the one kept
        $counting = $attempts->where('status', 'completed')->sortByDesc(fn ($a) => $a->effective_score)->first();
        $attemptNumber = $attempts->search(fn ($a) => $a->id === $session->id);

        // The checklist as it was graded; an attempt never submitted has none saved, so it is
        // matched against the lab's current tasks
        $feedback = $session->studentFeedback();
        $tasks = $feedback['tasks'];
        if (! $tasks) {
            $completedIds = array_map('intval', is_array($session->completed_tasks) ? $session->completed_tasks : []);
            $tasks = collect($lab ? $lab->studentTasks() : [])->map(fn ($task) => [
                'id' => (int) $task['id'],
                'task' => (string) $task['task'],
                'done' => in_array((int) $task['id'], $completedIds, true),
                'feedback' => '',
            ])->all();
        }

        // Every regrade is logged (only the server writes these rows); the session itself keeps
        // only the latest one. Read defensively all the same: a bad row mustn't break the page.
        $number = fn ($value) => is_numeric($value) ? (float) $value : null;
        $regrades = TelemetryLog::where('lab_session_id', $session->id)
            ->where('event_type', 'instructor_grade_override')
            ->orderBy('id')
            ->get()
            ->map(function ($log) use ($number) {
                $payload = is_array($log->payload) ? $log->payload : [];
                $timestamp = $payload['timestamp'] ?? null;

                return [
                    'by' => is_string($payload['instructor_name'] ?? null) ? $payload['instructor_name'] : 'Instructor',
                    'from' => $number($payload['previous_score'] ?? null),
                    'original' => $number($payload['original_score'] ?? null),
                    'score' => $number($payload['override_score'] ?? null) ?? 0.0,
                    'reason' => is_string($payload['reason'] ?? null) ? $payload['reason'] : null,
                    'at' => is_string($timestamp) ? rescue(fn () => Carbon::parse($timestamp), null, false) : null,
                ];
            });
        // The grade the student had before the latest change, as logged then: the AI score can
        // change afterwards (a reopened attempt is resubmitted). Older logs lack previous_score.
        $latest = $regrades->last();
        $previousGrade = (float) ($latest['from']
            ?? ($regrades->count() > 1 ? $regrades[$regrades->count() - 2]['score'] : null)
            ?? $latest['original'] ?? $session->performance_score ?? 0);

        $diff = is_array($session->diff_stats) ? $session->diff_stats : [];

        // Flags an instructor has cleared no longer count against the attempt
        $codeFlags = Anomaly::where('lab_session_id', $session->id)
            ->whereIn('type', ['large_insert', 'low_typing_effort'])
            ->where('resolved', false)
            ->pluck('type');

        return [
            'session' => $session,
            'lab' => $lab,
            'isStaff' => $isStaff,
            'isOwner' => $isOwner,
            // Typing and focus figures are the owner's own; only they and staff see them
            'showPersonalStats' => $isOwner || $isStaff,
            // Only staff read the code here; showWorkspace has already checked this one manages the class
            'submittedFiles' => $isStaff ? $session->submittedFileList() : [],
            'attemptNumber' => $attemptNumber === false ? null : $attemptNumber + 1,
            'attempts' => $attempts,
            'counting' => $counting,
            'graded' => $session->status === 'completed' || $session->isGradeOverridden(),
            'submitted' => $session->ai_grade_summary !== null,
            'previousGrade' => $previousGrade,
            // An instructor can save the grade unchanged, to confirm a provisional one
            'confirmed' => $session->isGradeOverridden()
                && round($previousGrade, 2) === round((float) $session->instructor_grade_override, 2),
            'regrades' => $regrades,
            'tasks' => $tasks,
            'feedback' => $feedback,
            'stats' => [
                'time_spent_seconds' => $session->timeSpentSeconds(),
                'time_limit_minutes' => $lab && ! $lab->isLiveLab() ? (int) ($lab->time_limit ?? 0) : null,
                'tasks_done' => count(array_filter($tasks, fn ($t) => $t['done'])),
                'tasks_total' => count($tasks),
                'wpm' => (int) ($session->calculateOverallWpm() ?: $session->wpm),
                'keystrokes' => (int) ($session->keystroke_count ?? 0),
                'lines_added' => (int) ($diff['lines_added'] ?? $diff['linesAdded'] ?? $diff['added'] ?? 0),
                'lines_deleted' => (int) ($diff['lines_deleted'] ?? $diff['linesDeleted'] ?? $diff['deleted'] ?? 0),
                'focus_lost' => (int) ($session->focus_lost_count ?? 0),
                'pastes' => (int) ($session->paste_anomaly_count ?? 0),
                'large_inserts' => $codeFlags->filter(fn ($type) => $type === 'large_insert')->count(),
                'low_typing_effort' => $codeFlags->contains('low_typing_effort'),
            ],
        ];
    }

    /**
     * Complete and grade the laboratory workspace session.
     */
    public function completeSession(int $id)
    {
        $session = LabSession::findOrFail($id);

        // Check ownership
        if (auth()->id() !== $session->user_id) {
            if (auth()->user()->role === 'student') {
                abort(403, 'Unauthorized.');
            }
            $this->authorizeSessionManager($session);
        }

        // Only an open attempt can be completed: a timed-out or already finished one keeps its result
        if (! $session->acceptsWork()) {
            return back()->with('error', 'This lab attempt has already ended, so it can no longer be completed.');
        }

        // A team's attempt ends for everyone at once, with one grade, when its leader submits
        // from VS Code. Finishing one teammate's alone would leave them with their own score.
        if ($session->group_id) {
            return back()->with('error', 'This is a team attempt. Your team leader submits it for the whole team from VS Code.');
        }

        $session->update([
            'status' => 'completed',
            'ended_at' => now(),
        ]);
        app(CodeSimilarityService::class)->refreshLabReportQuietly($session->lab_id);

        // Log completion in telemetry
        TelemetryLog::create([
            'lab_session_id' => $session->id,
            'event_type' => 'session_completed',
            'payload' => [
                'final_score' => $session->performance_score,
                'completed_tasks_count' => count($session->completed_tasks ?? []),
            ],
        ]);

        $lab = $session->laboratory;

        $session->notifyStudents(
            "Lab submitted: {$lab->title}",
            "Your submission for '{$lab->title}' was received. Score: {$session->performance_score}%.",
            type: 'grade',
        );
        $session->notifyInstructorOfSubmission();

        return redirect()->route('sessions.show', $session->id)
            ->with('success', "Laboratory session completed successfully! Final Performance Score: {$session->performance_score}%.");
    }

    /**
     * Download starter files as a zip archive or single file.
     */
    public function downloadStarterFiles(int $id)
    {
        $laboratory = Laboratory::findOrFail($id);
        $this->authorizeLabViewer($laboratory);
        $files = $laboratory->getStarterFilesList();

        if (empty($files)) {
            return back()->with('error', 'No starter files found for this laboratory.');
        }

        // If single file, download directly
        if (count($files) === 1) {
            $file = $files[0];
            $fileName = $file['name'] ?? 'starter_code.txt';
            $content = $file['content'] ?? '';

            return response($content, 200, [
                'Content-Type' => 'text/plain',
                'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            ]);
        }

        // Multiple files: pack into a zip archive
        $zipName = Str::slug($laboratory->title).'-starter-files.zip';
        $tempPath = tempnam(sys_get_temp_dir(), 'certicode_zip_');
        $zip = new \ZipArchive;

        if ($zip->open($tempPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            foreach ($files as $file) {
                $name = $file['name'] ?? 'file.txt';
                $content = $file['content'] ?? '';
                $zip->addFromString($name, $content);
            }
            $zip->close();

            return response()->download($tempPath, $zipName)->deleteFileAfterSend(true);
        }

        return back()->with('error', 'Failed to generate starter files archive.');
    }
}
