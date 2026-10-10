<?php

namespace App\Http\Controllers;

use App\Models\Anomaly;
use App\Models\LabSession;
use App\Models\Laboratory;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The dashboard loads in two steps so the page appears at once: `show` renders the frame
 * with skeleton placeholders (no data queries), then the browser fetches `sections`,
 * which runs the queries and returns the finished HTML.
 */
class DashboardController extends Controller
{
    public function show()
    {
        return view('dashboard');
    }

    public function sections(Request $request)
    {
        // It's a fragment; opened directly it would render without the app layout
        if (!$request->ajax()) {
            return redirect()->route('dashboard');
        }

        /** @var User $user */
        $user = $request->user();
        $data = Cache::store('file')->remember(
            "user_dashboard_sections_{$user->id}_{$user->role}",
            30,
            fn () => match ($user->role) {
                'admin' => $this->adminData(),
                'instructor' => $this->instructorData($user),
                default => $this->studentData($user),
            }
        );

        return response()
            ->view('dashboard.sections', ['role' => $user->role] + $data)
            ->header('Cache-Control', 'private, no-store');
    }

    private function studentData(User $user): array
    {
        $sessionCounts = $user->labSessions()
            ->selectRaw("count(distinct case when status = 'completed' then lab_id end) as completed")
            ->selectRaw("count(case when status = 'in_progress' then 1 end) as in_progress")
            ->first();

        return [
            'stats' => [
                ['label' => 'Classes', 'value' => $user->classes()->count(), 'hint' => 'enrolled'],
                ['label' => 'Labs completed', 'value' => (int) $sessionCounts->completed, 'hint' => 'all time'],
                ['label' => 'In progress', 'value' => (int) $sessionCounts->in_progress, 'hint' => 'open sessions'],
                ['label' => 'Certificates', 'value' => $user->certificates()->count(), 'hint' => 'earned'],
            ],
            'invitations' => $user->invitedClasses()->count(),
            'inProgress' => $user->labSessions()
                ->where('status', 'in_progress')
                ->with('laboratory:id,title,module_id', 'laboratory.module:id,class_id', 'laboratory.module.schoolClass:id,name')
                ->latest('started_at')
                ->take(4)
                ->get(['id', 'lab_id', 'started_at', 'status']),
            'certificates' => $user->certificates()->with('schoolClass:id,name')->latest('issued_at')->get(),
        ];
    }

    private function instructorData(User $user): array
    {
        $classIds = $user->instructedClasses()->pluck('id');
        // Booleans are written as SQL literals: the pgsql connection emulates prepares (needed
        // behind Supabase's pooler), which sends `false` as 0, and Postgres rejects boolean = 0.
        $ownSessions = fn ($q) => $q->whereHas('laboratory.module', fn ($m) => $m->whereIn('class_id', $classIds));

        return [
            'stats' => [
                ['label' => 'Active classes', 'value' => SchoolClass::whereIn('id', $classIds)->whereNotIn('status', ['completed', 'closed'])->count(), 'hint' => 'not yet ended'],
                ['label' => 'Students', 'value' => DB::table('class_student')->whereIn('class_id', $classIds)->where('status', 'enrolled')->distinct()->count('student_id'), 'hint' => 'across your classes'],
                ['label' => 'Live now', 'value' => Laboratory::whereHas('module', fn ($m) => $m->whereIn('class_id', $classIds))->where('availability_mode', 'live')->where('live_status', 'active')->count(), 'hint' => 'labs running'],
                ['label' => 'Open flags', 'value' => Anomaly::whereRaw('resolved = false')->whereHas('labSession', $ownSessions)->count(), 'hint' => 'need review'],
            ],
            'recentAnomalies' => Anomaly::withoutSnapshotData()
                ->whereHas('labSession', $ownSessions)
                ->with('labSession:id,lab_id,user_id', 'labSession.user:id,name', 'labSession.laboratory:id,title')
                ->latest()
                ->take(6)
                ->get(),
            'classes' => SchoolClass::whereIn('id', $classIds)
                ->withCount(['students' => fn ($q) => $q->where('class_student.status', 'enrolled')])
                ->latest()
                ->take(5)
                ->get(['id', 'name', 'code', 'status', 'scheduled_end_date', 'created_at']),
        ];
    }

    private function adminData(): array
    {
        $roles = User::query()->selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role');

        return [
            'stats' => [
                ['label' => 'Students', 'value' => (int) ($roles['student'] ?? 0), 'hint' => 'accounts'],
                ['label' => 'Instructors', 'value' => (int) ($roles['instructor'] ?? 0), 'hint' => 'approved'],
                ['label' => 'Classes', 'value' => SchoolClass::count(), 'hint' => 'all time'],
                ['label' => 'Lab sessions', 'value' => LabSession::count(), 'hint' => 'all time'],
            ],
            'pendingCount' => User::where('role', 'student')->whereNotNull('instructor_requested_at')->count(),
            'pendingRequests' => User::where('role', 'student')
                ->whereNotNull('instructor_requested_at')
                ->oldest('instructor_requested_at')
                ->take(5)
                ->get(['id', 'name', 'email', 'instructor_requested_at']),
        ];
    }
}
