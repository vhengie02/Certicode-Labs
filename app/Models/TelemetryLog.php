<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelemetryLog extends Model
{
    use HasFactory;

    public $timestamps = false; // Only created_at is present, and set automatically by database

    protected $fillable = [
        'lab_session_id',
        'event_type',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    /**
     * Get the lab session.
     */
    public function labSession()
    {
        return $this->belongsTo(LabSession::class);
    }
}
