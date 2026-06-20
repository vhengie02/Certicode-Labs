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
}
