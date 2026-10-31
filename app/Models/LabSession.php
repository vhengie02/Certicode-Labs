<?php

namespace App\Models;

use App\Events\AnomalyDetected;
use App\Notifications\ClassActivityNotification;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LabSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'lab_id',
        'user_id',
        'group_id',
        'github_repo_url',
        'started_at',
        'ended_at',
        'status',
        'performance_score',
        'completed_tasks',
        'diff_stats',
        'code_contributions',
        'wpm',
        'keystroke_count',
        'focus_lost_count',
        'paste_anomaly_count',
        'submitted_code',
        'submitted_files',
        'ai_grade_summary',
        'instructor_grade_override',
        'instructor_override_reason',
        'instructor_overridden_at',
        'overridden_by',
        'closed_at',
        'last_ping_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'closed_at' => 'datetime',
        'last_ping_at' => 'datetime',
        'extension_token' => 'encrypted',
        'instructor_overridden_at' => 'datetime',
        'performance_score' => 'float',
        'instructor_grade_override' => 'float',
        'completed_tasks' => 'array',
        'submitted_files' => 'array',
        'ai_grade_summary' => 'array',
        'diff_stats' => 'array',
        'code_contributions' => 'array',
        'wpm' => 'integer',
        'keystroke_count' => 'integer',
        'focus_lost_count' => 'integer',
        'paste_anomaly_count' => 'integer',
    ];

    protected $hidden = [
        'extension_token',
    ];

    /**
     * Return this session's VS Code extension token, generating it on first use.
     * The token travels in the vscode:// deep link and authorizes /api/v1 calls for this session only.
     */
    public function issueExtensionToken(): string
    {
        $token = $this->currentExtensionToken();
        if (! $token) {
            $token = Str::random(48);
            // Written with a query rather than save(): save() decrypts the old value to detect changes,
            // which throws when that value was encrypted with a previous APP_KEY.
            $encrypted = Crypt::encryptString($token);
            static::whereKey($this->getKey())->update(['extension_token' => $encrypted]);
            $this->setRawAttributes(['extension_token' => $encrypted] + $this->getAttributes(), true);
        }

        return $token;
    }

    /**
     * The stored extension token, or null when there is none or it was encrypted with a
     * previous APP_KEY (after a key rotation the student simply reconnects from the website).
     */
    public function currentExtensionToken(): ?string
    {
        try {
            return $this->extension_token ?: null;
        } catch (DecryptException) {
            return null;
        }
    }

    /**
     * Whether a user may act on this session: its owner, a teammate in the same group,
     * an admin, or the instructor who owns the class the lab belongs to. A teammate is a
     * member of the group, or anyone with their own session in it.
     */
    public function isAccessibleBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ((int) $this->user_id === (int) $user->id || $user->role === 'admin') {
            return true;
        }

        if ($user->role === 'instructor') {
            $classId = Module::whereKey(Laboratory::whereKey($this->lab_id)->value('module_id'))->value('class_id');

            return $user->canManageClass($classId);
        }

        return $this->group_id && (
            static::where('group_id', $this->group_id)->where('user_id', $user->id)->exists()
            || Group::whereKey($this->group_id)->whereHas('members', fn ($q) => $q->where('users.id', $user->id))->exists()
        );
    }

    /**
     * Ids of the sessions that make up this attempt's team on the lab: every teammate starts
     * their own session, so team chat and contributions span all of them. A solo attempt is
     * just itself.
     */
    public function teamSessionIds(): array
    {
        if (! $this->group_id) {
            return [$this->id];
        }

        return static::where('lab_id', $this->lab_id)
            ->where('group_id', $this->group_id)
            ->pluck('id')
            ->push($this->id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Code contributions for the whole team, one entry per student with their share of the
     * team's lines. Each session keeps the entries it has seen; the newest entry per student wins.
     */
    public function teamContributions(): array
    {
        $entries = static::whereIn('id', $this->teamSessionIds())
            ->get(['id', 'code_contributions'])
            ->flatMap(fn ($s) => $s->code_contributions ?? [])
            ->filter(fn ($c) => isset($c['user_id']))
            ->sortByDesc(fn ($c) => $c['last_active_at'] ?? '')
            ->unique('user_id')
            ->values()
            ->all();

        $totalLines = array_sum(array_map(fn ($c) => ($c['lines_added'] ?? 0) + ($c['lines_modified'] ?? 0), $entries));
        foreach ($entries as &$c) {
            $lines = ($c['lines_added'] ?? 0) + ($c['lines_modified'] ?? 0);
            $c['contribution_percent'] = $totalLines > 0 ? round(($lines / $totalLines) * 100, 1) : round(100 / count($entries), 1);
        }

        return $entries;
    }

    /**
     * Keep figures derived from completed sessions in step with them, whichever path changed
     * the session (extension, website, instructor, live-lab close, override):
     * - cached progress is dropped whenever the completed state can have changed, since
     *   certificate checks read it;
     * - competency scores are recalculated whenever a completed session is created,
     *   regraded, reopened or deleted.
     */
    protected static function booted()
    {
        static::saved(function (LabSession $session) {
            if ($session->status === 'completed' || $session->wasChanged('status')) {
                $session->forgetCachedProgress();
            }
        });
        static::created(function (LabSession $session) {
            if ($session->status === 'completed') {
                StudentCompetency::refreshFor($session->user, $session->laboratory);
            }
        });
        static::updated(function (LabSession $session) {
            if (! $session->changesCompetencyEvidence()) {
                return;
            }
            // A session moved to another student or lab no longer counts for the old one either
            if ($session->wasChanged(['user_id', 'lab_id'])) {
                StudentCompetency::refreshFor(
                    User::find($session->getOriginal('user_id')),
                    Laboratory::find($session->getOriginal('lab_id'))
                );
                $session->unsetRelation('user')->unsetRelation('laboratory');
            }
            StudentCompetency::refreshFor($session->user, $session->laboratory);
        });
        static::deleted(function (LabSession $session) {
            $session->forgetCachedProgress();
            if ($session->status === 'completed') {
                StudentCompetency::refreshFor($session->user, $session->laboratory);
            }
        });
    }

    /**
     * Whether the update just made changes what competency scoring sees: the session is, or
     * was until this update, completed, and its status or grade moved. (Called from the
     * `updated` event, where getOriginal() still holds the values from before the update.)
     */
    private function changesCompetencyEvidence(): bool
    {
        if (! $this->wasChanged(['status', 'performance_score', 'instructor_grade_override', 'lab_id', 'user_id'])) {
            return false;
        }

        return $this->status === 'completed' || $this->getOriginal('status') === 'completed';
    }

    private function forgetCachedProgress(): void
    {
        $lab = $this->laboratory ?: Laboratory::find($this->lab_id);
        if (! $lab || ! $lab->module_id) {
            return;
        }

        Cache::forget("user_{$this->user_id}_module_{$lab->module_id}_progress");
        $module = $lab->module ?: Module::find($lab->module_id);
        if ($module) {
            if ($module->parent_id) {
                Cache::forget("user_{$this->user_id}_module_{$module->parent_id}_progress");
            }
            if ($module->class_id) {
                Cache::forget("user_{$this->user_id}_class_{$module->class_id}_progress");
            }
        }
    }

    /**
     * Get the laboratory.
     */
    public function laboratory()
    {
        return $this->belongsTo(Laboratory::class, 'lab_id');
    }

    /**
     * Get the student/user who started this session.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the group associated with this session (if group lab).
     */
    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Send a lab notification to whoever owns this attempt: the student, plus every
     * member of the team on a group lab. It opens the attempt's results page unless
     * another link is given. A failed send never breaks the caller.
     */
    public function notifyStudents(string $title, string $message, ?string $url = null): void
    {
        $recipients = collect([$this->user])
            ->merge($this->group_id ? ($this->group?->members ?? collect()) : collect())
            ->filter()
            ->unique('id');

        foreach ($recipients as $student) {
            try {
                $student->notify(new ClassActivityNotification(
                    $title,
                    $message,
                    $url ?? route('sessions.show', $this->id),
                    'lab'
                ));
            } catch (\Throwable $e) {
                Log::warning('Lab notification failed: '.$e->getMessage());
            }
        }
    }

    /**
     * Get the telemetry logs.
     */
    public function telemetryLogs()
    {
        return $this->hasMany(TelemetryLog::class);
    }

    /**
     * Get the anomalies detected during this session.
     */
    public function anomalies()
    {
        return $this->hasMany(Anomaly::class);
    }

    /**
     * Get ephemeral team chats for this session.
     */
    public function chats()
    {
        return $this->hasMany(LabSessionChat::class, 'lab_session_id');
    }

    /**
     * Get the instructor who performed a grade override.
     */
    public function overriddenByUser()
    {
        return $this->belongsTo(User::class, 'overridden_by');
    }

    /**
     * The grade rule in SQL, matching getEffectiveScoreAttribute: the instructor's override if
     * set, else the AI score. Pass the table name or alias the query uses. Unlike the PHP form,
     * a session with neither value stays NULL, so avg() and max() skip it.
     */
    public static function effectiveGradeSql(string $table = 'lab_sessions'): string
    {
        return "coalesce({$table}.instructor_grade_override, {$table}.performance_score)";
    }

    /**
     * Determine effective score (instructor override takes precedence over AI score).
     */
    public function getEffectiveScoreAttribute(): float
    {
        return $this->instructor_grade_override !== null
            ? (float) $this->instructor_grade_override
            : (float) ($this->performance_score ?? 0.0);
    }

    /**
     * The lock held while this attempt is graded (check-progress or submit), so two gradings
     * never run at once. It is in the database cache store, shared by every serverless
     * instance, and expires on its own if a request dies.
     */
    public function gradingLock(): Lock
    {
        return Cache::store('database')->lock("lab_session_{$this->id}_grading", 90);
    }

    /**
     * Whether a submit is still being graded. A submit sent just before the attempt ended (time
     * ran out, the live lab closed) is still graded and saved, so until then the attempt's
     * result isn't final. Only for an attempt that has ended: no new grading can start on it,
     * so briefly taking the lock to check can't hold one up.
     */
    public function isBeingGraded(): bool
    {
        $lock = $this->gradingLock();
        if (! $lock->get()) {
            return true;
        }
        $lock->release();

        return false;
    }

    /**
     * Whether the grade is still the provisional one from automatic checks, waiting for the
     * instructor. An instructor's grade replaces it.
     */
    public function needsGradeReview(): bool
    {
        return ! $this->isGradeOverridden() && ! empty($this->ai_grade_summary['needs_review']);
    }

    /**
     * The grader's feedback as the student saw it when they submitted: overall, code quality,
     * and the task checklist as it was graded (text, done, note). It is kept in
     * ai_grade_summary under 'student_feedback' and is the only part of that field students
     * may see; the rest is the instructor's. 'tasks' is empty for an attempt never submitted.
     *
     * @return array{overall: string, code_quality: string, tasks: list<array{id: int, task: string, done: bool, feedback: string}>}
     */
    public function studentFeedback(): array
    {
        $feedback = $this->ai_grade_summary['student_feedback'] ?? null;
        $feedback = is_array($feedback) ? $feedback : [];

        $tasks = [];
        foreach (is_array($feedback['tasks'] ?? null) ? $feedback['tasks'] : [] as $task) {
            if (is_array($task) && is_string($task['task'] ?? null)) {
                $tasks[] = [
                    'id' => (int) ($task['id'] ?? 0),
                    'task' => $task['task'],
                    'done' => (bool) ($task['done'] ?? false),
                    'feedback' => is_string($task['feedback'] ?? null) ? $task['feedback'] : '',
                ];
            }
        }

        return [
            'overall' => is_string($feedback['overall'] ?? null) ? $feedback['overall'] : '',
            'code_quality' => is_string($feedback['code_quality'] ?? null) ? $feedback['code_quality'] : '',
            'tasks' => $tasks,
        ];
    }

    /**
     * Seconds spent on the attempt, from start to finish (or now, while it is open), capped at
     * the time limit or a live lab's countdown, the same way the leaderboard counts it.
     */
    public function timeSpentSeconds(): int
    {
        $lab = $this->laboratory;
        $isLive = $lab && $lab->isLiveLab();
        $timeLimitSeconds = $lab ? ($isLive ? $lab->getLiveTotalDurationSeconds() : ($lab->time_limit ?? 0) * 60) : 0;

        $elapsed = $this->started_at
            ? (int) $this->started_at->diffInSeconds($this->ended_at ?: now(), true)
            : 0;

        return $timeLimitSeconds > 0 ? min($elapsed, $timeLimitSeconds) : $elapsed;
    }

    /**
     * The labs a student has completed at least once, keyed by lab id. A retaken lab appears
     * once, so progress can't be pushed up by completing the same lab again.
     */
    public static function completedLabIdsFor(int $userId, ?array $labIds = null): array
    {
        return static::where('user_id', $userId)
            ->where('status', 'completed')
            ->when($labIds !== null, fn ($q) => $q->whereIn('lab_id', $labIds))
            ->distinct()
            ->pluck('lab_id', 'lab_id')
            ->toArray();
    }

    /**
     * Progress over a set of labs: how many of them are in $completedLabIds (keyed by lab id).
     */
    public static function progressOver(array $labIds, array $completedLabIds): array
    {
        $total = count($labIds);
        $completed = count(array_intersect($labIds, array_keys($completedLabIds)));

        return [
            'completed' => $completed,
            'total' => $total,
            'percent' => $total > 0 ? round(($completed / $total) * 100) : 0,
        ];
    }

    /**
     * Check if grade has been overridden by an instructor.
     */
    public function isGradeOverridden(): bool
    {
        return $this->instructor_grade_override !== null;
    }

    /**
     * How long after the time limit or live countdown runs out work is still taken, so a
     * submit sent just before the end isn't lost to network lag.
     */
    public const LATE_GRACE_SECONDS = 60;

    /**
     * Whether the same student has another attempt open on this lab. Only one may be open at
     * a time (a partial unique index), so this one can't be reopened until that one ends.
     */
    public function hasOtherOpenAttempt(): bool
    {
        return $this->user_id !== null && static::where('lab_id', $this->lab_id)
            ->where('user_id', $this->user_id)
            ->where('status', 'in_progress')
            ->whereKeyNot($this->id)
            ->exists();
    }

    /**
     * Whether this attempt can still be graded: it is in progress, and its time limit (or,
     * in a live lab, the shared countdown) hasn't run out beyond the grace period.
     */
    public function acceptsWork(): bool
    {
        if ($this->status !== 'in_progress') {
            return false;
        }

        $lab = $this->laboratory;
        if (! $lab) {
            return true;
        }

        if ($lab->isLiveLab()) {
            if ($lab->live_status === 'closed') {
                return false;
            }
            if ($lab->live_status === 'active') {
                return $lab->getLiveElapsedSeconds() < $lab->getLiveTotalDurationSeconds() + self::LATE_GRACE_SECONDS;
            }

            return true;
        }

        if ($lab->time_limit > 0 && $this->started_at) {
            return $this->started_at->diffInSeconds(now(), true) < $lab->time_limit * 60 + self::LATE_GRACE_SECONDS;
        }

        return true;
    }

    /**
     * Check if the session is currently actively connected with a recent heartbeat.
     * Pings are dispatched every 15-20s. A 75-second window accommodates minor network jitter.
     */
    public function isActivelyConnected(): bool
    {
        if ($this->status !== 'in_progress') {
            return false;
        }

        if ($this->last_ping_at) {
            return $this->last_ping_at->diffInSeconds(now()) <= 75;
        }

        // If newly started without ping yet, allow 60s grace period from started_at
        return $this->started_at ? $this->started_at->diffInSeconds(now()) <= 60 : false;
    }

    /**
     * Check if session has not pinged recently (75s to 5 mins).
     */
    public function isIdle(): bool
    {
        if ($this->status !== 'in_progress') {
            return false;
        }

        if ($this->last_ping_at) {
            $diff = $this->last_ping_at->diffInSeconds(now());

            return $diff > 75 && $diff <= 300;
        }

        if ($this->started_at) {
            $diff = $this->started_at->diffInSeconds(now());

            return $diff > 60 && $diff <= 300;
        }

        return false;
    }

    /**
     * Check if session has had no heartbeat for more than 5 minutes.
     */
    public function isOffline(): bool
    {
        if ($this->status !== 'in_progress') {
            return false;
        }

        return ! $this->isActivelyConnected() && ! $this->isIdle();
    }

    /**
     * Get a human-readable connectivity state string for telemetry and views.
     */
    public function getConnectionState(): string
    {
        if ($this->status === 'completed') {
            return 'completed';
        }
        if ($this->status === 'abandoned') {
            return 'abandoned';
        }
        if ($this->status === 'in_progress') {
            if ($this->isActivelyConnected()) {
                return 'active';
            }
            if ($this->isIdle()) {
                return 'idle';
            }

            return 'offline';
        }

        return $this->status;
    }

    /**
     * Automatically sweep and expire abandoned or time-limit-exceeded in-progress sessions.
     */
    public static function autoExpireStaleSessions(?int $labId = null, ?int $userId = null): int
    {
        $query = static::where('status', 'in_progress')->with('laboratory');
        if ($labId) {
            $query->where('lab_id', $labId);
        }
        if ($userId) {
            $query->where('user_id', $userId);
        }

        $sessions = $query->get();
        $expiredCount = 0;

        foreach ($sessions as $session) {
            $lab = $session->laboratory;
            $timeLimitMinutes = $lab ? ($lab->time_limit ?: 60) : 60;
            $startedAt = $session->started_at ?: $session->created_at;

            // 1. Exceeded the lab's official time limit (in minutes)
            $exceededTimeLimit = $startedAt && $startedAt->diffInMinutes(now(), true) >= $timeLimitMinutes;

            // 2. Dead/abandoned session: no ping or telemetry in over 30 minutes
            $lastActivity = $session->last_ping_at ?: $session->updated_at ?: $startedAt;
            $abandoned = $lastActivity && $lastActivity->diffInMinutes(now(), true) >= 30;

            if ($exceededTimeLimit || $abandoned) {
                $session->update([
                    'status' => 'abandoned',
                    'ended_at' => $session->ended_at ?: now(),
                    'closed_at' => now(),
                ]);
                $expiredCount++;
            }
        }

        return $expiredCount;
    }

    /**
     * Record an idle timeout anomaly and broadcast it in real time (Feature 11).
     *
     * In a Live Lab, notification includes shared countdown context:
     * "Student X has been idle for {N} min; {M} min remain in this Live Lab"
     * In an Open Lab, notification is without countdown context:
     * "Student X has been idle for {N} min"
     */
    public function recordIdleAnomaly(int $idleMinutes = 10, ?array $additionalMetadata = []): Anomaly
    {
        $lab = $this->laboratory;
        $isLive = $lab && $lab->isLiveLab();
        $studentName = $this->user ? $this->user->name : 'Student';

        if ($isLive) {
            $remainingSec = $lab->getRemainingLiveSeconds();
            $remMin = max(0, (int) floor($remainingSec / 60));
            $description = "Student {$studentName} has been idle for {$idleMinutes} min; {$remMin} min remain in this Live Lab";
        } else {
            $description = "Student {$studentName} has been idle for {$idleMinutes} min";
        }

        $severity = $idleMinutes >= 20 ? 'high' : 'medium';

        $metadata = array_merge([
            'idle_minutes' => $idleMinutes,
            'is_live' => (bool) $isLive,
            'shared_remaining_minutes' => $isLive ? ($remMin ?? 0) : null,
        ], $additionalMetadata ?? []);

        $anomaly = Anomaly::create([
            'lab_session_id' => $this->id,
            'type' => 'idle_timeout',
            'severity' => $severity,
            'description' => $description,
            'metadata' => $metadata,
        ]);

        try {
            event(new AnomalyDetected($this->id, [
                'id' => $anomaly->id,
                'type' => $anomaly->type,
                'severity' => $anomaly->severity,
                'description' => $anomaly->description,
                'image_path' => null,
                'created_at' => $anomaly->created_at ? $anomaly->created_at->toIso8601String() : now()->toIso8601String(),
                'user_id' => $this->user_id,
                'student_name' => $studentName,
            ]));
        } catch (\Throwable $e) {
            Log::warning('Failed to broadcast AnomalyDetected: '.$e->getMessage());
        }

        return $anomaly;
    }

    /**
     * Calculate overall session average WPM without excluding idle intervals.
     * Keeps idle time as part of the student's overall session average divisor.
     */
    public function calculateOverallWpm(): int
    {
        $keystrokes = (int) ($this->keystroke_count ?? 0);
        if ($keystrokes <= 0) {
            return 0;
        }

        $started = $this->started_at ?: $this->created_at ?: now();
        $ended = $this->ended_at ?: now();
        $elapsedMinutes = max($started->diffInSeconds($ended, true) / 60, 0.1);

        $words = $keystrokes / 5.0;

        return (int) round($words / $elapsedMinutes);
    }
}
