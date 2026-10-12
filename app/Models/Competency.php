<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Competency extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
    ];

    /**
     * Get the student competency records mapping users to this competency.
     */
    public function studentCompetencies()
    {
        return $this->hasMany(StudentCompetency::class);
    }

    /**
     * Labs that assess this competency.
     */
    public function laboratories()
    {
        return $this->belongsToMany(Laboratory::class, 'laboratory_competency');
    }
}
