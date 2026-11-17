<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
     * A passed pre-lab camera check. Its payload keeps the picture the check passed on
     * (`image_base64`, with `has_picture`) for the class instructor, until it expires.
     */
    public const CAMERA_CHECK_EVENT = 'webcam_prelab_verification';

    /**
     * Omit the base64 camera check picture (~30-60 KB) from `payload` when listing logs.
     * The instructor opens it through `camera-checks.picture`, which reads the full row.
     */
    public function scopeWithoutCameraPicture($query)
    {
        $table = $this->getTable();
        $payload = match ($query->getConnection()->getDriverName()) {
            'pgsql' => "({$table}.payload::jsonb - 'image_base64')",
            'sqlite' => "json_remove({$table}.payload, '$.image_base64')",
            default => null,
        };
        if ($payload === null) {
            return $query;
        }

        return $query
            ->select(array_map(fn ($column) => "{$table}.{$column}", ['id', 'lab_session_id', 'event_type', 'created_at']))
            ->selectRaw("{$payload} as payload");
    }

    /**
     * Remove the picture from camera checks older than `app.snapshot_retention_days`, the same
     * limit as flag snapshots. The check itself stays, with `picture_expired` so the instructor
     * can see a picture existed. Returns the rows changed.
     */
    public static function expireOldCameraPictures(int $chunk = 50): int
    {
        $old = static::where('event_type', self::CAMERA_CHECK_EVENT)
            ->whereNotNull('payload->image_base64')
            ->where('created_at', '<', now()->subDays((int) config('app.snapshot_retention_days')));

        if ((new static)->getConnection()->getDriverName() === 'pgsql') {
            return $old->update([
                'payload' => DB::raw("((coalesce(payload::jsonb, '{}'::jsonb) - 'image_base64') || '{\"has_picture\": false, \"picture_expired\": true}'::jsonb)::json"),
            ]);
        }

        $changed = 0;
        $old->select(['id', 'payload'])->chunkById($chunk, function ($logs) use (&$changed) {
            foreach ($logs as $log) {
                $payload = $log->payload ?? [];
                unset($payload['image_base64']);
                $log->forceFill(['payload' => ['has_picture' => false, 'picture_expired' => true] + $payload])->saveQuietly();
                $changed++;
            }
        });

        return $changed;
    }

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
