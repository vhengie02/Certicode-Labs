<?php

namespace App\Support;

use App\Models\Anomaly;
use App\Models\LabSession;
use App\Models\LabSessionChat;
use App\Models\SchoolClass;
use App\Models\TelemetryLog;
use App\Models\User;

/**
 * Writes everything we hold about one user as a JSON document. Columns are listed by hand, so a
 * new secret column can't reach an export by accident. Rows are streamed because a student's
 * telemetry can be long.
 */
class AccountExport
{
    private const PROFILE_FIELDS = [
        'name', 'first_name', 'last_name', 'username', 'email', 'gmail', 'github_username', 'gender',
        'role', 'timezone', 'date_style', 'notify_email_channel', 'notify_class', 'notify_module',
        'notify_lab', 'notify_grade', 'notify_certificate', 'notify_integrity', 'created_at',
    ];

    private const SESSION_FIELDS = [
        'id', 'lab_id', 'group_id', 'github_repo_url', 'started_at', 'ended_at', 'status',
        'performance_score', 'completed_tasks', 'diff_stats', 'code_contributions', 'wpm',
        'submitted_code', 'submitted_files',
    ];

    public function __construct(private User $user) {}

    public function stream(): void
    {
        $sessionIds = LabSession::where('user_id', $this->user->id)->select('id');

        echo '{"exported_at":'.json_encode(now()->toIso8601String());
        $this->section('profile', $this->user->only(self::PROFILE_FIELDS));
        $this->section('classes_joined', $this->user->classes()->pluck('name'));
        $this->section('classes_taught', SchoolClass::where('instructor_id', $this->user->id)->pluck('name'));
        $this->section('certificates', $this->user->certificates()->with('schoolClass:id,name')->get()->map(fn ($certificate) => [
            'class' => $certificate->schoolClass?->name,
            'verification_code' => $certificate->verification_code,
            'issued_at' => $certificate->issued_at,
        ]));
        $this->section('competencies', $this->user->studentCompetencies()->get()->makeHidden(['id', 'user_id']));
        $this->section('notifications', $this->user->notifications()->get(['data', 'read_at', 'created_at']));

        $this->rows('lab_sessions', LabSession::where('user_id', $this->user->id), fn ($session) => $session->only(self::SESSION_FIELDS));
        $this->rows('telemetry', TelemetryLog::whereIn('lab_session_id', $sessionIds), fn ($log) => $log->only(['lab_session_id', 'event_type', 'payload', 'created_at']));
        $this->rows('integrity_flags', Anomaly::whereIn('lab_session_id', $sessionIds), fn ($flag) => [
            'lab_session_id' => $flag->lab_session_id,
            'type' => $flag->type,
            'severity' => $flag->severity,
            'description' => $flag->description,
            // Snapshots live here as base64: far too large for this file
            'details' => collect($flag->metadata ?? [])->except('image_base64'),
            'has_camera_snapshot' => $flag->image_path !== null,
            'created_at' => $flag->created_at,
        ]);
        $this->rows('chat_messages', LabSessionChat::where('user_id', $this->user->id), fn ($chat) => $chat->only(['lab_session_id', 'message', 'code_snippet', 'created_at']));
        echo '}';
    }

    private function section(string $name, mixed $value): void
    {
        echo ','.json_encode($name).':'.json_encode($value);
    }

    private function rows(string $name, $query, callable $shape): void
    {
        echo ','.json_encode($name).':[';
        $first = true;
        foreach ($query->lazyById(500) as $row) {
            echo ($first ? '' : ',').json_encode($shape($row));
            $first = false;
        }
        echo ']';
    }
}
