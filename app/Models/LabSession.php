<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    /**
     * Invalidate cached progress upon session completion.
     */
    protected static function booted()
    {
        static::saved(function (LabSession $session) {
            if ($session->status === 'completed') {
                $lab = $session->laboratory ?: Laboratory::find($session->lab_id);
                if ($lab && $lab->module_id) {
                    \Illuminate\Support\Facades\Cache::forget("user_{$session->user_id}_module_{$lab->module_id}_progress");
                    $module = $lab->module ?: Module::find($lab->module_id);
                    if ($module) {
                        if ($module->parent_id) {
                            \Illuminate\Support\Facades\Cache::forget("user_{$session->user_id}_module_{$module->parent_id}_progress");
                        }
                        if ($module->class_id) {
                            \Illuminate\Support\Facades\Cache::forget("user_{$session->user_id}_class_{$module->class_id}_progress");
                        }
                    }
                }
            }
        });
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
     * Determine effective score (instructor override takes precedence over AI score).
     */
    public function getEffectiveScoreAttribute(): float
    {
        return $this->instructor_grade_override !== null
            ? (float) $this->instructor_grade_override
            : (float) ($this->performance_score ?? 0.0);
    }

    /**
     * Check if grade has been overridden by an instructor.
     */
    public function isGradeOverridden(): bool
    {
        return $this->instructor_grade_override !== null;
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

        return !$this->isActivelyConnected() && !$this->isIdle();
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
    public static function autoExpireStaleSessions(?int $labId = null): int
    {
        $query = static::where('status', 'in_progress')->with('laboratory');
        if ($labId) {
            $query->where('lab_id', $labId);
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
            event(new \App\Events\AnomalyDetected($this->id, [
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
            \Illuminate\Support\Facades\Log::warning("Failed to broadcast AnomalyDetected: " . $e->getMessage());
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
