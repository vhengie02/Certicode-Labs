<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Laboratory extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'github_repo_template',
        'tasks_definition',
        'reference_solution',
        'rubric',
        'test_cases',
        'time_limit',
        'is_group_lab',
        'module_id',
        'views_count',
        'starter_files',
        'availability_mode',
        'live_duration_minutes',
        'live_status',
        'live_started_at',
        'live_elapsed_seconds',
    ];

    protected $casts = [
        'tasks_definition' => 'array',
        'test_cases' => 'array',
        'is_group_lab' => 'boolean',
        'starter_files' => 'array',
        'live_duration_minutes' => 'integer',
        'live_started_at' => 'datetime',
        'live_elapsed_seconds' => 'integer',
    ];

    /**
     * Get normalized starter files with backward-compatibility fallback.
     */
    public function getStarterFilesList(): array
    {
        if (!empty($this->starter_files) && is_array($this->starter_files) && count($this->starter_files) > 0) {
            return $this->starter_files;
        }

        $defaultContent = $this->reference_solution ?: "// CertiCode Labs - Starter Code\npublic class TaskManager {\n    public static void main(String[] args) {\n        System.out.println(\"Hello, CertiCode Labs!\");\n    }\n}\n";

        return [
            [
                'name' => 'TaskManager.java',
                'content' => $defaultContent,
                'is_primary' => true,
                'is_readonly' => false,
            ]
        ];
    }

    /**
     * Check if this laboratory is set to Live Lab availability mode.
     */
    public function isLiveLab(): bool
    {
        return ($this->availability_mode ?? 'open') === 'live';
    }

    /**
     * Check if this laboratory is set to Open Lab availability mode.
     */
    public function isOpenLab(): bool
    {
        return !$this->isLiveLab();
    }

    /**
     * Check if live lab is active with countdown running.
     */
    public function isLiveActive(): bool
    {
        return $this->isLiveLab() && $this->live_status === 'active' && $this->getRemainingLiveSeconds() > 0;
    }

    /**
     * Check if live lab has not yet been opened by instructor.
     */
    public function isLiveNotStarted(): bool
    {
        return $this->isLiveLab() && ($this->live_status === 'not_started' || empty($this->live_status));
    }

    /**
     * Check if live lab is closed or expired.
     */
    public function isLiveClosed(): bool
    {
        if (!$this->isLiveLab()) {
            return false;
        }
        return $this->live_status === 'closed' || ($this->live_status === 'active' && $this->getRemainingLiveSeconds() <= 0);
    }

    /**
     * Get the total duration window in seconds for the live lab.
     */
    public function getLiveTotalDurationSeconds(): int
    {
        $minutes = $this->live_duration_minutes ?: $this->time_limit ?: 60;
        return (int) ($minutes * 60);
    }

    /**
     * Get the total elapsed seconds across all runs for this live lab window.
     */
    public function getLiveElapsedSeconds(): int
    {
        if (!$this->isLiveLab()) {
            return 0;
        }

        if ($this->isLiveNotStarted()) {
            return 0;
        }

        $baseElapsed = (int) ($this->live_elapsed_seconds ?? 0);

        if ($this->live_status === 'active' && $this->live_started_at) {
            $currentRun = (int) $this->live_started_at->diffInSeconds(now(), true);
            return min($this->getLiveTotalDurationSeconds(), $baseElapsed + $currentRun);
        }

        return min($this->getLiveTotalDurationSeconds(), $baseElapsed);
    }

    /**
     * Get the remaining seconds of the shared live lab countdown.
     */
    public function getRemainingLiveSeconds(): int
    {
        if (!$this->isLiveLab()) {
            return ($this->time_limit ?? 0) * 60;
        }

        if ($this->isLiveNotStarted()) {
            return $this->getLiveTotalDurationSeconds();
        }

        $totalDuration = $this->getLiveTotalDurationSeconds();
        $elapsed = $this->getLiveElapsedSeconds();

        return max(0, $totalDuration - $elapsed);
    }

    /**
     * Manually open the live lab and start the shared countdown.
     */
    public function openLive(?int $durationMinutes = null): bool
    {
        if ($durationMinutes && $durationMinutes > 0) {
            $this->live_duration_minutes = $durationMinutes;
        } elseif (!$this->live_duration_minutes) {
            $this->live_duration_minutes = $this->time_limit ?: 60;
        }

        $this->live_status = 'active';
        $this->live_started_at = now();
        $this->live_elapsed_seconds = 0;
        $this->save();

        return true;
    }

    /**
     * Close/End the live lab session, auto-submitting in-progress sessions.
     */
    public function closeLive(): void
    {
        $this->live_elapsed_seconds = $this->getLiveElapsedSeconds();
        $this->live_status = 'closed';
        $this->live_started_at = null;
        $this->save();

        // Auto-close and assess all in-progress student sessions for this lab (Feature 7A sequence)
        $inProgressSessions = $this->labSessions()->where('status', 'in_progress')->get();
        foreach ($inProgressSessions as $session) {
            $completedCount = is_array($session->completed_tasks) ? count($session->completed_tasks) : 0;
            $tasksDef = is_array($this->tasks_definition) ? count($this->tasks_definition) : 1;
            $score = $tasksDef > 0 ? round(($completedCount / $tasksDef) * 100, 2) : 0.0;

            $session->update([
                'status' => 'completed',
                'ended_at' => $session->ended_at ?: now(),
                'closed_at' => now(),
                'performance_score' => $session->performance_score > 0 ? $session->performance_score : $score,
            ]);

            // Map student competency
            $user = $session->user;
            if ($user) {
                $competency = \App\Models\Competency::firstOrCreate([
                    'code' => 'COMP-JAVA-01',
                ], [
                    'name' => 'Java OOP and Custom Exceptions Mastery',
                ]);

                \App\Models\StudentCompetency::updateOrCreate([
                    'user_id' => $user->id,
                    'competency_id' => $competency->id,
                ], [
                    'score_achieved' => max($session->performance_score, $score, 0.0),
                ]);
            }

            // Purge ephemeral chat
            \App\Models\LabSessionChat::where('lab_session_id', $session->id)->delete();
        }
    }

    /**
     * Reopen a closed live lab.
     * Note: Does NOT grant a fresh duration. Only leftover remaining seconds carry forward.
     */
    public function reopenLive(?int $extendMinutes = null): bool
    {
        $remaining = $this->getRemainingLiveSeconds();

        if ($remaining <= 0) {
            if ($extendMinutes && $extendMinutes > 0) {
                $this->live_duration_minutes = ($this->live_duration_minutes ?? 60) + $extendMinutes;
            } else {
                return false; // Time fully exhausted, cannot reopen without extension
            }
        }

        $this->live_status = 'active';
        $this->live_started_at = now();
        $this->save();

        return true;
    }

    /**
     * Automatically close the live lab if its countdown has reached zero.
     */
    public function checkAndAutoCloseLive(): bool
    {
        if ($this->isLiveLab() && $this->live_status === 'active' && $this->getRemainingLiveSeconds() <= 0) {
            $this->closeLive();
            return true;
        }

        return false;
    }

    /**
     * Get the module this laboratory belongs to.
     */
    public function module()
    {
        return $this->belongsTo(Module::class, 'module_id');
    }

    /**
     * Get unique views for this laboratory.
     */
    public function views()
    {
        return $this->hasMany(LaboratoryView::class);
    }

    /**
     * Get the lab sessions associated with this laboratory.
     */
    public function labSessions()
    {
        return $this->hasMany(LabSession::class, 'lab_id');
    }

    /**
     * Get the groups created for this laboratory.
     */
    public function groups()
    {
        return $this->hasMany(Group::class, 'lab_id');
    }
}
