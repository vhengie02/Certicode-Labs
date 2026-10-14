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
    public function getStudentProgress(User $user, ?array $completedLabIds = null)
    {
        $labIds = $this->getAllLaboratoryIds();
        $totalLabs = count($labIds);
        if ($totalLabs === 0) {
            return null;
        }

        if ($completedLabIds !== null) {
            return LabSession::progressOver($labIds, $completedLabIds);
        }

        $cacheKey = "user_{$user->id}_module_{$this->id}_progress";

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 180, fn () => LabSession::progressOver(
            $labIds,
            LabSession::completedLabIdsFor($user->id, $labIds)
        ));
    }

    /**
     * This module's id plus every section nested under it, at any depth.
     */
    public function subtreeIds(): array
    {
        $ids = [$this->id];
        $frontier = [$this->id];
        while ($frontier) {
            $frontier = static::whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = array_merge($ids, $frontier);
        }

        return $ids;
    }

    /**
     * Recursively collect all laboratory IDs for this module and its children.
     */
    public function getAllLaboratoryIds(): array
    {
        $ids = $this->relationLoaded('laboratories')
            ? $this->laboratories->pluck('id')->toArray()
            : $this->laboratories()->pluck('id')->toArray();

        $children = $this->relationLoaded('children')
            ? $this->children
            : $this->children()->with('laboratories')->get();

        foreach ($children as $child) {
            $ids = array_merge($ids, $child->getAllLaboratoryIds());
        }

        return array_unique($ids);
    }
}
