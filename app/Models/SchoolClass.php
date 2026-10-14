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
     * Load every module of the class (with its laboratories) in two queries and wire up
     * each module's `children` relation in memory, so walking the tree at any depth
     * (e.g. Module::getAllLaboratoryIds) issues no further queries.
     */
    public function loadModuleTree(?\Closure $laboratoryConstraint = null): static
    {
        $this->load(['modules.laboratories' => $laboratoryConstraint ?? fn ($query) => $query]);

        $childrenByParent = $this->modules->groupBy('parent_id');
        foreach ($this->modules as $module) {
            $children = $childrenByParent[$module->id] ?? new \Illuminate\Database\Eloquent\Collection();
            $module->setRelation('children', $children->sortBy('order_index')->values());
        }

        return $this;
    }

    /**
     * End the class and certify every enrolled student who meets the pass mark. Used by the
     * instructor's "end class" action and the scheduled auto-conclude. Returns how many
     * certificates were newly issued.
     */
    public function concludeAndCertify(): int
    {
        $this->update(['status' => 'completed']);
        // Modules and labs in two queries, so each student's progress check walks the tree in memory
        $this->loadModuleTree();

        $issued = 0;
        foreach ($this->students()->wherePivot('status', 'enrolled')->get() as $student) {
            $result = Certificate::issueFor($this, $student);
            if ($result['created']) {
                $result['certificate']->notifyOwner(
                    "Course Completed: {$this->name}",
                    "Congratulations! You completed '{$this->name}' with {$result['progress']['percent']}% (threshold: {$result['threshold']}%) and earned your official certificate."
                );
                $issued++;
            }
        }

        return $issued;
    }

    /**
     * Calculate aggregate student progress for the class.
     */
    public function getStudentProgress(User $student, ?array $completedLabIds = null)
    {
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

        if ($completedLabIds !== null) {
            return LabSession::progressOver($labIds, $completedLabIds);
        }

        $cacheKey = "user_{$student->id}_class_{$this->id}_progress";

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 180, fn () => LabSession::progressOver(
            $labIds,
            LabSession::completedLabIdsFor($student->id, $labIds)
        ));
    }
}
