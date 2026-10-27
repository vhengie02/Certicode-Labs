<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

class TelemetryLog extends Model
{
    use HasFactory, MassPrunable;

    public $timestamps = false; // Only created_at is present, and set automatically by database

    /**
     * Rows that only matter while the lab is running: the connection check reads the latest
     * heartbeat, and the session row keeps the typing speed. Everything else is history.
     */
    public const SHORT_LIVED_EVENTS = ['vscode_ping', 'wpm_update'];

    /**
     * Records only the server writes: grading, regrades, code runs and the camera check. The
     * telemetry endpoint refuses them, so a client can't forge one (the student's results page
     * shows the regrade history from these rows).
     */
    public const SERVER_EVENTS = [
        'instructor_grade_override',
        'session_completed',
        'check_progress',
        'code_execution',
        'github_sync',
        'webcam_prelab_verification',
    ];

    protected $fillable = [
        'lab_session_id',
        'event_type',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    /**
     * `model:prune` deletes heartbeats and typing-speed samples older than
     * `app.telemetry_retention_days`. They arrive every few seconds per student.
     */
    public function prunable()
    {
        return static::whereIn('event_type', self::SHORT_LIVED_EVENTS)
            ->where('created_at', '<', now()->subDays((int) config('app.telemetry_retention_days')));
    }

    /**
     * Get the lab session.
     */
    public function labSession()
    {
        return $this->belongsTo(LabSession::class);
    }
}
