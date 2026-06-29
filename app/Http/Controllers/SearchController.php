<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Models\Module;
use App\Models\Laboratory;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Handle global search queries.
     */
    public function search(Request $request)
    {
        $q = $request->query('q', '');
        if (strlen($q) < 2) {
            return response()->json([
                'classes' => [],
                'modules' => [],
                'laboratories' => [],
            ]);
        }

        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'classes' => [],
                'modules' => [],
                'laboratories' => [],
            ], 401);
        }

        // 1. Resolve Class query based on user role
        if ($user->role === 'student') {
            $classQuery = $user->classes();
        } elseif ($user->role === 'instructor') {
            $classQuery = SchoolClass::where('instructor_id', $user->id);
        } else {
            $classQuery = SchoolClass::query();
        }
        $classes = $classQuery->where(function($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                      ->orWhere('code', 'like', "%{$q}%")
                      ->orWhere('description', 'like', "%{$q}%");
            })
            ->limit(5)
            ->get(['school_classes.id', 'name', 'code']);

        // Get class IDs for scoped queries
        if ($user->role === 'student') {
            $classIds = $user->classes()->pluck('school_classes.id');
        } elseif ($user->role === 'instructor') {
            $classIds = SchoolClass::where('instructor_id', $user->id)->pluck('id');
        } else {
            $classIds = null;
        }

        // 2. Resolve Module query based on user role
        $moduleQuery = Module::query();
        if ($classIds !== null) {
            $moduleQuery->whereIn('class_id', $classIds);
        }
        $modules = $moduleQuery->where(function($query) use ($q) {
                $query->where('title', 'like', "%{$q}%")
                      ->orWhere('description', 'like', "%{$q}%")
                      ->orWhere('content', 'like', "%{$q}%");
            })
            ->limit(5)
            ->get(['id', 'title', 'class_id']);

        // 3. Resolve Laboratory query based on user role
        $labQuery = Laboratory::query();
        if ($classIds !== null) {
            $labQuery->whereIn('module_id', Module::whereIn('class_id', $classIds)->pluck('id'));
        }
        $laboratories = $labQuery->where(function($query) use ($q) {
                $query->where('title', 'like', "%{$q}%")
                      ->orWhere('description', 'like', "%{$q}%");
            })
            ->limit(5)
            ->get(['id', 'title', 'module_id']);

        return response()->json([
            'classes' => $classes->map(function($c) {
                return [
                    'label' => $c->name . " ({$c->code})",
                    'url' => route('classes.show', $c->id),
                    'type' => 'Class',
                ];
            }),
            'modules' => $modules->map(function($m) {
                return [
                    'label' => $m->title,
                    'url' => route('modules.show', [$m->class_id, $m->id]),
                    'type' => 'Module',
                ];
            }),
            'laboratories' => $laboratories->map(function($l) {
                return [
                    'label' => $l->title,
                    'url' => route('laboratories.show', $l->id),
                    'type' => 'Laboratory',
                ];
            }),
        ]);
    }
}
