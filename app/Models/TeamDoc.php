<?php

namespace App\Models;

use App\Services\LabTeams;
use App\Support\TeamSync;
use Illuminate\Database\Eloquent\Model;

/**
 * The document one team attempt types in together. It holds what lets the teammates reach
 * each other: the name of their realtime channel, which nobody can guess, and the key their
 * edits are encrypted with. Both go only to members of the team.
 */
class TeamDoc extends Model
{
    /** A plain append is refused beyond either limit, until a teammate compacts the document. */
    public const MAX_UPDATES = 400;

    public const MAX_PAYLOAD_CHARS = 8_000_000;

    protected $fillable = ['group_id', 'lab_id', 'team_started_at', 'topic', 'key', 'rotation', 'payload_chars'];

    protected $casts = [
        'team_started_at' => 'datetime',
        'key' => 'encrypted',
    ];

    protected $hidden = ['key'];

    public function updates()
    {
        return $this->hasMany(TeamDocUpdate::class);
    }

    /**
     * The document of the team attempt this session belongs to, made the first time a
     * teammate asks. Null when shared typing is off, or the session is not an open attempt
     * of a team.
     */
    public static function forSession(LabSession $session): ?self
    {
        if (! static::allowedFor($session)) {
            return null;
        }

        $attempt = [
            'group_id' => $session->group_id,
            'lab_id' => $session->lab_id,
            'team_started_at' => $session->team_started_at,
        ];

        // With the lab row locked, two teammates asking at once get one document
        return static::where($attempt)->first()
            ?? app(LabTeams::class)->locked($session->laboratory, fn () => static::where($attempt)->first() ?? static::create($attempt + [
                'topic' => 'td-'.bin2hex(random_bytes(24)),
                'key' => base64_encode(random_bytes(32)),
            ]));
    }

    /**
     * A new channel and key, for when someone leaves the team mid-lab: they keep the old ones,
     * which no longer reach anyone. The stored edits stay.
     */
    public function rotate(): void
    {
        $this->update([
            'topic' => 'td-'.bin2hex(random_bytes(24)),
            'key' => base64_encode(random_bytes(32)),
            'rotation' => $this->rotation + 1,
        ]);
    }

    /**
     * Whether this session types together with its team: shared typing is set up, the session
     * is an open attempt of a team, and no teammate's attempt in it is graded yet. A graded one
     * means the leader submitted and this session was reopened afterwards: it goes on alone,
     * as a solo attempt would.
     */
    public static function allowedFor(LabSession $session): bool
    {
        if (! TeamSync::enabled() || $session->status !== 'in_progress' || ! $session->group_id || ! $session->team_started_at) {
            return false;
        }

        return ! LabSession::where('group_id', $session->group_id)
            ->where('lab_id', $session->lab_id)
            ->where('team_started_at', $session->team_started_at)
            ->where('status', 'completed')
            ->exists();
    }

    /** The document of a team attempt, if one was made; never makes one. */
    public static function existingFor(LabSession $session): ?self
    {
        return $session->group_id && $session->team_started_at
            ? static::where(['group_id' => $session->group_id, 'lab_id' => $session->lab_id, 'team_started_at' => $session->team_started_at])->first()
            : null;
    }

    public function lastUpdateId(): int
    {
        return (int) $this->updates()->max('id');
    }

    /** Whether the document has grown past what a teammate should have to load piece by piece. */
    public function needsCompaction(): bool
    {
        return $this->payload_chars >= self::MAX_PAYLOAD_CHARS
            || $this->updates()->count() >= self::MAX_UPDATES;
    }

    /**
     * Delete the documents of team attempts that are over: no teammate has that attempt open.
     *
     * @return int How many were deleted
     */
    public static function pruneEnded(): int
    {
        $ended = static::query()->whereNotExists(fn ($q) => $q->selectRaw('1')->from('lab_sessions')
            ->whereColumn('lab_sessions.group_id', 'team_docs.group_id')
            ->whereColumn('lab_sessions.lab_id', 'team_docs.lab_id')
            ->whereColumn('lab_sessions.team_started_at', 'team_docs.team_started_at')
            ->where('lab_sessions.status', 'in_progress'));

        return $ended->delete();
    }
}
