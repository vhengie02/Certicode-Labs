<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The stored result of CodeSimilarityService::analyzeLabCohort for one lab.
 */
class LabSimilarityReport extends Model
{
    protected $fillable = [
        'lab_id',
        'analysis',
        'computed_at',
    ];

    protected $casts = [
        'analysis' => 'array',
        'computed_at' => 'datetime',
    ];

    public function laboratory()
    {
        return $this->belongsTo(Laboratory::class, 'lab_id');
    }
}
