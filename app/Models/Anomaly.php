<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Anomaly extends Model
{
    use HasFactory;

    protected $fillable = [
        'lab_session_id',
        'type',
        'severity',
        'description',
        'resolved',
    ];

    protected $casts = [
        'resolved' => 'boolean',
    ];

    /**
     * Get the lab session.
     */
    public function labSession()
    {
        return $this->belongsTo(LabSession::class);
    }
}
