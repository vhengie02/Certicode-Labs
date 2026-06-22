<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_id',
        'parent_id',
        'title',
        'description',
        'content',
        'order_index',
        'views_count',
    ];

    /**
     * Get the parent module (if this is a sub-module).
     */
    public function parent()
    {
        return $this->belongsTo(Module::class, 'parent_id');
    }

    /**
     * Get the sub-modules (children).
     */
    public function children()
    {
        return $this->hasMany(Module::class, 'parent_id')->orderBy('order_index');
    }

    /**
     * Get unique views for this module.
     */
    public function views()
    {
        return $this->hasMany(ModuleView::class);
    }

    /**
     * Get the class this module belongs to.
     */
    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * Get the coding challenges (laboratories) inside this module.
     */
    public function laboratories()
    {
        return $this->hasMany(Laboratory::class, 'module_id');
    }

    /**
     * Get the file attachments for this module.
     */
    public function attachments()
    {
        return $this->hasMany(ModuleAttachment::class);
    }

    /**
     * Get student progress for this module.
     */
    public function getStudentProgress(User $user)
    {
        $labIds = $this->getAllLaboratoryIds();
        $totalLabs = count($labIds);
        if ($totalLabs === 0) {
            return null;
        }

        $completedCount = LabSession::whereIn('lab_id', $labIds)
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->count();

        return [
            'completed' => $completedCount,
            'total' => $totalLabs,
            'percent' => round(($completedCount / $totalLabs) * 100),
        ];
    }

    /**
     * Recursively collect all laboratory IDs for this module and its children.
     */
    public function getAllLaboratoryIds(): array
    {
        $ids = $this->laboratories()->pluck('id')->toArray();

        foreach ($this->children as $child) {
            $ids = array_merge($ids, $child->getAllLaboratoryIds());
        }

        return array_unique($ids);
    }
}
