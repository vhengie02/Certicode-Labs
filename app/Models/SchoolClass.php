<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SchoolClass extends Model
{
    use HasFactory;

    protected $table = 'school_classes';

    public const DEFAULT_LAB_PASS_SCORE = 75;

    protected $fillable = [
        'name',
        'code',
        'instructor_id',
        'description',
        'passing_threshold',
        'lab_pass_score',
        'scheduled_end_date',
        'status',
    ];

    protected $casts = [
        'passing_threshold' => 'integer',
        'lab_pass_score' => 'integer',
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
            $children = $childrenByParent[$module->id] ?? new Collection;
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
     * Every laboratory in the modules of this class.
     */
    private function allLaboratoryIds(): array
    {
        $labIds = [];
        foreach ($this->modules as $module) {
            $labIds = array_merge($labIds, $module->getAllLaboratoryIds());
        }

        return array_unique($labIds);
    }

    /**
     * Where a student stands for the certificate. A lab counts once an attempt reaches the
     * class's lab pass score. A grade still waiting for the instructor doesn't count yet:
     * it comes from pattern checks, which can't tell working code from code that only names
     * the right things. Not cached, because a grade change must show at once.
     *
     * @return array{passed: int, total: int, percent: int|float, under_review: int}
     */
    public function certificateProgress(User $student): array
    {
        $labIds = $this->allLaboratoryIds();
        $attempts = LabSession::where('user_id', $student->id)
            ->where('status', 'completed')
            ->whereIn('lab_id', $labIds)
            ->get(['id', 'lab_id', 'performance_score', 'instructor_grade_override', 'ai_grade_summary']);

        $passScore = $this->lab_pass_score ?? self::DEFAULT_LAB_PASS_SCORE;
        $passed = $attempts
            ->filter(fn (LabSession $attempt) => ! $attempt->needsGradeReview() && $attempt->effective_score >= $passScore)
            ->pluck('lab_id')
            ->unique();
        $underReview = $attempts
            ->filter(fn (LabSession $attempt) => $attempt->needsGradeReview())
            ->pluck('lab_id')
            ->unique()
            ->diff($passed);

        $progress = LabSession::progressOver($labIds, $passed->flip()->all());

        return [
            'passed' => $progress['completed'],
            'total' => $progress['total'],
            'percent' => $progress['percent'],
            'under_review' => $underReview->count(),
        ];
    }

    /**
     * Calculate aggregate student progress for the class.
     */
    public function getStudentProgress(User $student, ?array $completedLabIds = null)
    {
        $labIds = $this->allLaboratoryIds();
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

        return Cache::remember($cacheKey, 180, fn () => LabSession::progressOver(
            $labIds,
            LabSession::completedLabIdsFor($student->id, $labIds)
        ));
    }
}
