<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'lab_id',
        'leader_id',
    ];

    /**
     * The member who submits the lab for the team. Null until a teammate opens the lab or
     * someone is chosen.
     */
    public function leader()
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    /**
     * Get the laboratory associated with this group.
     */
    public function laboratory()
    {
        return $this->belongsTo(Laboratory::class, 'lab_id');
    }

    /**
     * Get the members belonging to this group.
     */
    public function members()
    {
        return $this->belongsToMany(User::class, 'group_members')
            ->withPivot('contribution_score', 'leader_vote')
            ->withTimestamps();
    }

    /**
     * Get the lab sessions performed by this group.
     */
    public function labSessions()
    {
        return $this->hasMany(LabSession::class, 'group_id');
    }
}
