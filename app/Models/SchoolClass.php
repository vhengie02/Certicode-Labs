<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolClass extends Model
{
    use HasFactory;

    protected $table = 'school_classes';

    protected $fillable = [
        'name',
        'code',
        'instructor_id',
        'description',
        'passing_threshold',
        'scheduled_end_date',
        'status',
    ];

    protected $casts = [
        'passing_threshold' => 'integer',
        'scheduled_end_date' => 'datetime',
    ];

    /**
     * Check if the class is concluded/ended.
     */
    public function isEnded(): bool
    {
        if (in_array($this->status, ['completed', 'closed'], true)) {
            return true;
        }
        if ($this->scheduled_end_date && now()->gte($this->scheduled_end_date)) {
            return true;
        }
        return false;
    }

    /**
     * Get the instructor of this class.
     */
    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    /**
     * Get the students enrolled in this class.
     */
    public function students()
    {
        return $this->belongsToMany(User::class, 'class_student', 'class_id', 'student_id')
                    ->withPivot('status')
                    ->withTimestamps();
    }

    /**
     * Get the modules associated with this class.
     */
    public function modules()
    {
        return $this->hasMany(Module::class, 'class_id')->orderBy('order_index');
    }

    /**
     * Calculate aggregate student progress for the class.
     */
    public function getStudentProgress(User $student)
    {
        $cacheKey = "user_{$student->id}_class_{$this->id}_progress";

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 180, function () use ($student) {
            $labIds = [];
            // Gather all laboratory IDs associated with the modules of this class
            foreach ($this->modules as $module) {
                $labIds = array_merge($labIds, $module->getAllLaboratoryIds());
            }
            $labIds = array_unique($labIds);
            $totalLabs = count($labIds);
            
            if ($totalLabs === 0) {
                return [
                    'completed' => 0,
                    'total' => 0,
                    'percent' => 0,
                ];
            }

            $completedCount = \App\Models\LabSession::whereIn('lab_id', $labIds)
                ->where('user_id', $student->id)
                ->where('status', 'completed')
                ->count();

            return [
                'completed' => $completedCount,
                'total' => $totalLabs,
                'percent' => round(($completedCount / $totalLabs) * 100),
            ];
        });
    }
}
