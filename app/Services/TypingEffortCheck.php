<?php

namespace App\Services;

use App\Events\AnomalyDetected;
use App\Models\Anomaly;
use App\Models\LabSession;
use App\Models\TelemetryLog;
use Illuminate\Support\Facades\Log;

/**
 * Flags a finished attempt whose code is much larger than what the student typed or was
 * flagged for pasting. This covers code that reached the file without any editor event the
 * extension can see. It only raises a flag for the instructor and never changes the grade.
 */
class TypingEffortCheck
{
    /** New code below this size is too small to judge. Whitespace doesn't count. */
    private const MIN_NEW_CHARS = 100;

    /**
     * Share of the new code that must be accounted for. It is low on purpose: completions over
     * 24 characters, snippets and generated getters are not counted as typing, and a typical
     * attempt types far more than it keeps.
     */
    private const MIN_ACCOUNTED_SHARE = 0.35;

    /** Below this share there is almost no typing behind the code, so the flag is high severity. */
    private const HIGH_SEVERITY_SHARE = 0.1;

    /**
     * For a request that has already saved the grade: a failure here is logged, never thrown.
     */
    public function flagQuietly(LabSession $session): void
    {
        try {
            $this->flag($session);
        } catch (\Throwable $e) {
            Log::warning("Typing effort check failed for session {$session->id}: ".$e->getMessage());
        }
    }

    public function flag(LabSession $session): ?Anomaly
    {
        // A team's code can come from any teammate's editor, and keystrokes are per session
        if ($session->group_id || $session->laboratory?->is_group_lab) {
            return null;
        }

        $newChars = $this->newChars((string) $session->submitted_code, $session);
        if ($newChars < self::MIN_NEW_CHARS) {
            return null;
        }

        $typed = (int) $session->keystroke_count;
        $pasted = $this->flaggedPasteChars($session);
        $share = ($typed + $pasted) / $newChars;
        if ($share >= self::MIN_ACCOUNTED_SHARE) {
            return null;
        }

        // A reopened attempt that is submitted again is the same finding
        if (Anomaly::where('lab_session_id', $session->id)->where('type', 'low_typing_effort')->exists()) {
            return null;
        }

        $anomaly = Anomaly::create([
            'lab_session_id' => $session->id,
            'type' => 'low_typing_effort',
            'severity' => $share < self::HIGH_SEVERITY_SHARE ? 'high' : 'medium',
            'description' => "Submitted about {$newChars} characters of new code, but only {$typed} were typed and {$pasted} flagged as pasted in VS Code.",
            'metadata' => ['new_chars' => $newChars, 'typed_chars' => $typed, 'pasted_chars' => $pasted],
        ]);

        try {
            event(new AnomalyDetected($session->id, [
                'id' => $anomaly->id,
                'type' => $anomaly->type,
                'severity' => $anomaly->severity,
                'description' => $anomaly->description,
                'snapshot_url' => null,
                'created_at' => $anomaly->created_at?->toIso8601String() ?? now()->toIso8601String(),
                'user_id' => $session->user_id,
                'student_name' => $session->user->name ?? 'Student',
            ]));
        } catch (\Throwable $e) {
            Log::warning('Failed to broadcast AnomalyDetected: '.$e->getMessage());
        }

        return $anomaly;
    }

    /**
     * Characters on lines that aren't in the starter code, not counting whitespace.
     */
    private function newChars(string $code, LabSession $session): int
    {
        $normalize = fn (string $line): string => preg_replace('/\s+/', '', $line);

        $starterLines = [];
        foreach ($session->laboratory?->getStarterFilesList() ?? [] as $file) {
            foreach (preg_split('/\R/', (string) ($file['content'] ?? '')) as $line) {
                $line = $normalize($line);
                $starterLines[$line] = ($starterLines[$line] ?? 0) + 1;
            }
        }

        $chars = 0;
        foreach (preg_split('/\R/', $code) as $line) {
            $line = $normalize($line);
            if (($starterLines[$line] ?? 0) > 0) {
                $starterLines[$line]--;

                continue;
            }
            $chars += mb_strlen($line);
        }

        return $chars;
    }

    /**
     * Code the instructor already has a paste flag for, so it isn't flagged a second time.
     */
    private function flaggedPasteChars(LabSession $session): int
    {
        return (int) TelemetryLog::where('lab_session_id', $session->id)
            ->where('event_type', 'paste_anomaly')
            ->get(['payload'])
            ->sum(fn ($log) => (int) ($log->payload['pasted_length'] ?? 0));
    }
}
