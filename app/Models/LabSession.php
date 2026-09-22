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
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'closed_at' => 'datetime',
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
}
