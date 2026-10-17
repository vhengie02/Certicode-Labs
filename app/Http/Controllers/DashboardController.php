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
    public function show(Request $request)
    {
        return view('dashboard', ['isNew' => $this->hasNotStarted($request->user())]);
    }

    /**
     * True until the account has done anything, so the greeting says "Welcome", not "Welcome back".
     * One cheap existence check; the full checklist is built in sections().
     */
    private function hasNotStarted(User $user): bool
    {
        return match ($user->role) {
            'admin' => false,
            'instructor' => !$user->instructedClasses()->exists(),
            default => !$user->classes()->exists() && !$user->labSessions()->exists(),
        };
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
        // Close timed-out or silent sessions first, so "Continue" never offers a lab that can't resume
        LabSession::autoExpireStaleSessions(userId: $user->id);

        $sessionCounts = $user->labSessions()
            ->selectRaw("count(distinct case when status = 'completed' then lab_id end) as completed")
            ->selectRaw("count(case when status = 'in_progress' then 1 end) as in_progress")
            ->first();

        $classCount = $user->classes()->count();
        $certificateCount = $user->certificates()->count();
        $completed = (int) $sessionCounts->completed;

        return [
            'stats' => [
                ['label' => 'Classes', 'value' => $classCount, 'hint' => 'enrolled'],
                ['label' => 'Labs completed', 'value' => $completed, 'hint' => 'all time'],
                ['label' => 'In progress', 'value' => (int) $sessionCounts->in_progress, 'hint' => 'open sessions'],
                ['label' => 'Certificates', 'value' => $certificateCount, 'hint' => 'earned'],
            ],
            // Getting-started steps tick themselves off from real activity
            'setup' => [
                ['title' => 'Join a class', 'body' => 'Ask your instructor for the class code and enter it on the Classes page, or accept an invitation.', 'done' => $classCount > 0, 'action' => 'Enter a class code', 'href' => route('classes.index') . '#join_class_code'],
                ['title' => 'Install the VS Code extension', 'body' => 'Labs run inside VS Code. Install the Certicode extension and sign in with this account.', 'done' => $user->labSessions()->exists(), 'action' => 'Download the extension', 'href' => asset('downloads/certicode-labs.vsix'), 'download' => true],
                ['title' => 'Take your first lab', 'body' => 'Start a lab from your class. Your camera is checked while you work, and your code is graded when you submit.', 'done' => $completed > 0, 'action' => 'Open my classes', 'href' => route('classes.index')],
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

        $ownLabs = Laboratory::whereHas('module', fn ($m) => $m->whereIn('class_id', $classIds));
        $classHref = $classIds->isNotEmpty() ? route('classes.show', $classIds->first()) : route('classes.create');

        return [
            'setup' => [
                ['title' => 'Create a class', 'body' => 'Give it a name and a pass mark. Certicode generates the code students join with.', 'done' => $classIds->isNotEmpty(), 'action' => 'New class', 'href' => route('classes.create')],
                ['title' => 'Add a module', 'body' => 'Modules are the chapters of your class: readings, files and the labs that go with them.', 'done' => DB::table('modules')->whereIn('class_id', $classIds)->exists(), 'action' => 'Open your class', 'href' => $classHref],
                ['title' => 'Create a lab', 'body' => "Write the task, add starter files and tests, and choose whether it runs live or on the student's own time.", 'done' => (clone $ownLabs)->exists(), 'action' => 'Open your class', 'href' => $classHref],
                ['title' => 'Bring in your students', 'body' => 'Share the class code, or invite students by email from the class page.', 'done' => DB::table('class_student')->whereIn('class_id', $classIds)->where('status', 'enrolled')->exists(), 'action' => 'Open your class', 'href' => $classHref],
                ['title' => 'Run a lab and review it', 'body' => 'Watch sessions on the monitoring page, check integrity flags and adjust grades where needed.', 'done' => LabSession::whereIn('lab_id', (clone $ownLabs)->select('id'))->exists(), 'action' => 'All classes', 'href' => route('classes.index')],
            ],
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
