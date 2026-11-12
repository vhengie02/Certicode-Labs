<?php

namespace App\Models;

use App\Services\LabTeams;
use App\Support\TeamSync;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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

    protected $fillable = ['group_id', 'lab_id', 'team_started_at', 'topic', 'key'];

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
        if (! TeamSync::enabled() || $session->status !== 'in_progress' || ! $session->group_id || ! $session->team_started_at) {
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

    public function lastUpdateId(): int
    {
        return (int) $this->updates()->max('id');
    }

    /** Whether the document has grown past what a teammate should have to load piece by piece. */
    public function needsCompaction(): bool
    {
        return $this->updates()->count() >= self::MAX_UPDATES
            || (int) $this->updates()->sum(DB::raw('length(payload)')) >= self::MAX_PAYLOAD_CHARS;
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
