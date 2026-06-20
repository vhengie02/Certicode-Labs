<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Laboratory extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'github_repo_template',
        'tasks_definition',
        'time_limit',
        'is_group_lab',
    ];

    protected $casts = [
        'tasks_definition' => 'array',
        'is_group_lab' => 'boolean',
    ];

    /**
     * Get the lab sessions associated with this laboratory.
     */
    public function labSessions()
    {
        return $this->hasMany(LabSession::class, 'lab_id');
    }

    /**
     * Get the groups created for this laboratory.
     */
    public function groups()
    {
        return $this->hasMany(Group::class, 'lab_id');
    }
}
