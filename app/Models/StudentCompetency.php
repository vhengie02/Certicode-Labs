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
     * credit nothing.
     */
    public static function refreshFor(?User $user, ?Laboratory $lab): void
    {
        if (!$user || !$lab) {
            return;
        }

        foreach ($lab->competencies()->pluck('competencies.id') as $competencyId) {
            $labIds = \Illuminate\Support\Facades\DB::table('laboratory_competency')
                ->where('competency_id', $competencyId)
                ->pluck('laboratory_id');

            $bestPerLab = LabSession::where('user_id', $user->id)
                ->whereIn('lab_id', $labIds)
                ->where('status', 'completed')
                ->groupBy('lab_id')
                ->selectRaw('lab_id, max(' . LabSession::effectiveGradeSql() . ') as best')
                ->pluck('best');

            if ($bestPerLab->isEmpty()) {
                continue;
            }

            static::updateOrCreate(
                ['user_id' => $user->id, 'competency_id' => $competencyId],
                ['score_achieved' => round(max(0.0, (float) $bestPerLab->avg()), 2)]
            );
        }
    }
}
