<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'lab_id',
        'user_id',
        'group_id',
        'github_repo_url',
        'started_at',
        'ended_at',
        'status',
        'performance_score',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'performance_score' => 'float',
    ];

    /**
     * Get the laboratory.
     */
    public function laboratory()
    {
        return $this->belongsTo(Laboratory::class, 'lab_id');
    }

    /**
     * Get the student/user who started this session.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the group associated with this session (if group lab).
     */
    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Get the telemetry logs.
     */
    public function telemetryLogs()
    {
        return $this->hasMany(TelemetryLog::class);
    }

    /**
     * Get the anomalies detected during this session.
     */
    public function anomalies()
    {
        return $this->hasMany(Anomaly::class);
    }
}
