<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentCompetency extends Model
{
    use HasFactory;

    protected $table = 'student_competencies';

    protected $fillable = [
        'user_id',
        'competency_id',
        'score_achieved',
    ];

    protected $casts = [
        'score_achieved' => 'float',
    ];

    /**
     * Get the student.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the competency description.
     */
    public function competency()
    {
        return $this->belongsTo(Competency::class);
    }

    /**
     * Recalculate a student's score for every competency the lab assesses: the average, over
     * all labs tagged with that competency, of the student's best completed grade on each lab
     * (an instructor override counts instead of the AI grade). Labs with no competencies
     * credit nothing, and a competency with no completed work left loses its score. Runs
     * automatically from LabSession's save hook; returns what it did, for the backfill command.
     *
     * @return array{created: int, changed: int, removed: int, unchanged: int}
     */
    public static function refreshFor(?User $user, ?Laboratory $lab): array
    {
        $outcome = ['created' => 0, 'changed' => 0, 'removed' => 0, 'unchanged' => 0];
        if (!$user || !$lab) {
            return $outcome;
        }

        foreach ($lab->competencies()->pluck('competencies.id') as $competencyId) {
            $outcome[static::refreshCompetency($user->id, (int) $competencyId)]++;
        }

        return $outcome;
    }

    /**
     * Recalculate one student's score for one competency and say what happened:
     * 'created', 'changed', 'removed' or 'unchanged'. The student's row in `users` is locked
     * for the duration, so two sessions completing at once can't both insert a score row.
     */
    public static function refreshCompetency(int $userId, int $competencyId): string
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($userId, $competencyId) {
            User::whereKey($userId)->lockForUpdate()->value('id');

            $labIds = \Illuminate\Support\Facades\DB::table('laboratory_competency')
                ->where('competency_id', $competencyId)
                ->pluck('laboratory_id');

            $bestPerLab = LabSession::where('user_id', $userId)
                ->whereIn('lab_id', $labIds)
                ->where('status', 'completed')
                ->groupBy('lab_id')
                ->selectRaw('lab_id, max(' . LabSession::effectiveGradeSql() . ') as best')
                ->pluck('best');

            $existing = static::where('user_id', $userId)->where('competency_id', $competencyId)->first();

            // No completed work left for this competency (e.g. the only session was reopened):
            // the score goes, rather than staying at what it was
            if ($bestPerLab->isEmpty()) {
                if (!$existing) {
                    return 'unchanged';
                }
                $existing->delete();
                return 'removed';
            }

            $score = round(max(0.0, (float) $bestPerLab->avg()), 2);
            if (!$existing) {
                static::create(['user_id' => $userId, 'competency_id' => $competencyId, 'score_achieved' => $score]);
                return 'created';
            }
            // Compare at the stored precision, so a float column's round-trip isn't a "change"
            if (round((float) $existing->score_achieved, 2) === $score) {
                return 'unchanged';
            }
            $existing->update(['score_achieved' => $score]);
            return 'changed';
        });
    }
}
