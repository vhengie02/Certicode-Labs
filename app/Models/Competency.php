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
    ];

    /**
     * Get the student competency records mapping users to this competency.
     */
    public function studentCompetencies()
    {
        return $this->hasMany(StudentCompetency::class);
    }
}
