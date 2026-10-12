<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Anomaly;
use App\Models\Certificate;
use App\Models\Competency;
use App\Models\LabSession;
use App\Models\SchoolClass;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin → Reports: platform activity for a chosen period, per-class and per-competency
 * tables, and CSV downloads of the underlying records.
 */
class ReportController extends Controller
{
    public const PERIODS = ['7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days', '365' => 'Last 12 months', 'all' => 'All time'];

    /** Grade of a session: the instructor's override when there is one, otherwise the AI grade */
    private const GRADE_SQL = 'coalesce(lab_sessions.instructor_grade_override, lab_sessions.performance_score)';

    public function index(Request $request)
    {
        [$period, $since] = $this->period($request);

        $sessionsInPeriod = LabSession::query()->when($since, fn ($q) => $q->where('lab_sessions.created_at', '>=', $since));
        $completedInPeriod = (clone $sessionsInPeriod)->where('status', 'completed');

        $kpis = [
            ['New accounts', User::query()->when($since, fn ($q) => $q->where('created_at', '>=', $since))->count(), 'Sign-ups in this period'],
            ['Lab sessions', (clone $sessionsInPeriod)->count(), 'Labs started in this period'],
            ['Submissions', (clone $completedInPeriod)->count(), 'Sessions finished and graded'],
            ['Average grade', $this->roundOrNull((clone $completedInPeriod)->avg(DB::raw(self::GRADE_SQL))), 'Of submissions, overrides included'],
            ['Certificates', Certificate::query()->when($since, fn ($q) => $q->where('issued_at', '>=', $since))->count(), 'Issued in this period'],
            ['Integrity flags', Anomaly::query()->when($since, fn ($q) => $q->where('created_at', '>=', $since))->count(), Anomaly::whereRaw('resolved = false')->count() . ' still open overall'],
        ];

        $roles = User::query()->selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role');

        $weeks = $this->weeklySeries($since);

        $flagsByType = Anomaly::query()
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->orderByDesc('total')
            ->pluck('total', 'type');

        $classes = $this->classRows($since);

        $competencies = Competency::query()
            ->withCount(['laboratories', 'studentCompetencies'])
            ->withAvg('studentCompetencies', 'score_achieved')
            ->orderBy('code')
            ->get();

        return view('admin.reports.index', compact('period', 'kpis', 'roles', 'weeks', 'flagsByType', 'classes', 'competencies'));
    }

    public function export(Request $request, string $type): StreamedResponse
    {
        [$period, $since] = $this->period($request);
        abort_unless(in_array($type, ['users', 'classes', 'sessions', 'flags', 'certificates'], true), 404);

        [$header, $rows] = match ($type) {
            'users' => [
                ['ID', 'Name', 'Email', 'Role', 'Joined', 'Lab sessions'],
                User::withCount('labSessions')->orderBy('id')->cursor()->map(fn ($u) => [
                    $u->id, $u->name, $u->email, $u->role, $u->created_at?->toDateTimeString(), $u->lab_sessions_count,
                ]),
            ],
            'classes' => [
                ['ID', 'Class', 'Join code', 'Instructor', 'Status', 'Students', 'Labs', 'Submissions', 'Average grade', 'Certificates'],
                collect($this->classRows($since))->map(fn ($c) => [
                    $c['id'], $c['name'], $c['code'], $c['instructor'], $c['status'], $c['students'], $c['labs'], $c['submissions'], $c['avg_grade'], $c['certificates'],
                ]),
            ],
            'sessions' => [
                ['Session', 'Student', 'Email', 'Lab', 'Class', 'Status', 'AI grade', 'Override', 'Started', 'Ended'],
                LabSession::with(['user:id,name,email', 'laboratory:id,title,module_id', 'laboratory.module:id,class_id', 'laboratory.module.schoolClass:id,name'])
                    ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
                    ->orderBy('id')->cursor()->map(fn ($s) => [
                        $s->id, $s->user?->name, $s->user?->email, $s->laboratory?->title, $s->laboratory?->module?->schoolClass?->name,
                        $s->status, $s->performance_score, $s->instructor_grade_override, $s->started_at, $s->ended_at,
                    ]),
            ],
            'flags' => [
                ['Flag', 'Session', 'Student', 'Type', 'Severity', 'Description', 'Resolved', 'Raised'],
                Anomaly::withoutSnapshotData()->with('labSession:id,user_id', 'labSession.user:id,name')
                    ->when($since, fn ($q) => $q->where('anomalies.created_at', '>=', $since))
                    ->orderBy('anomalies.id')->cursor()->map(fn ($a) => [
                        $a->id, $a->lab_session_id, $a->labSession?->user?->name, $a->type, $a->severity, $a->description,
                        $a->resolved ? 'yes' : 'no', $a->created_at?->toDateTimeString(),
                    ]),
            ],
            'certificates' => [
                ['Certificate', 'Student', 'Email', 'Class', 'Verification code', 'Issued'],
                Certificate::with(['user:id,name,email', 'schoolClass:id,name'])
                    ->when($since, fn ($q) => $q->where('issued_at', '>=', $since))
                    ->orderBy('id')->cursor()->map(fn ($c) => [
                        $c->id, $c->user?->name, $c->user?->email, $c->schoolClass?->name, $c->verification_code, $c->issued_at,
                    ]),
            ],
        };

        $filename = "certicode-{$type}-" . ($period === 'all' ? 'all-time' : "last-{$period}-days") . '-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // Excel reads the file as UTF-8
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, array_map([$this, 'csvSafe'], $row));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Spreadsheets run cells starting with = + - @ as formulas; prefix them so names can't inject one. */
    private function csvSafe(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }

        return $value;
    }

    private function period(Request $request): array
    {
        $period = (string) $request->query('period', '30');
        if (!array_key_exists($period, self::PERIODS)) {
            $period = '30';
        }

        return [$period, $period === 'all' ? null : now()->subDays((int) $period)->startOfDay()];
    }

    private function roundOrNull($value): ?float
    {
        return $value === null ? null : round((float) $value, 1);
    }

    /**
     * Sign-ups and lab sessions per week. "All time" shows the last 26 weeks.
     */
    private function weeklySeries(?\DateTimeInterface $since): array
    {
        $start = CarbonImmutable::parse($since ?? now()->subWeeks(25))->startOfWeek();
        $weeks = [];
        for ($w = $start; $w <= now(); $w = $w->addWeek()) {
            $weeks[$w->toDateString()] = ['label' => $w->format('M j'), 'signups' => 0, 'sessions' => 0];
        }

        $bucket = function (string $table, string $column) use ($start, &$weeks) {
            $days = DB::table($table)->where('created_at', '>=', $start)
                ->selectRaw('date(created_at) as day, count(*) as total')
                ->groupBy('day')->pluck('total', 'day');
            foreach ($days as $day => $total) {
                $key = CarbonImmutable::parse($day)->startOfWeek()->toDateString();
                if (isset($weeks[$key])) {
                    $weeks[$key][$column] += (int) $total;
                }
            }
        };
        $bucket('users', 'signups');
        $bucket('lab_sessions', 'sessions');

        return array_values($weeks);
    }

    /**
     * One row per class: enrolment, labs, submissions and grades in the period, certificates.
     */
    private function classRows(?\DateTimeInterface $since): array
    {
        $classes = SchoolClass::with('instructor:id,name')
            ->withCount([
                'students as enrolled_count' => fn ($q) => $q->where('class_student.status', 'enrolled'),
            ])
            ->orderBy('name')
            ->get();

        $labCounts = DB::table('laboratories')->join('modules', 'modules.id', '=', 'laboratories.module_id')
            ->groupBy('modules.class_id')->selectRaw('modules.class_id, count(*) as total')->pluck('total', 'class_id');

        $grades = DB::table('lab_sessions')
            ->join('laboratories', 'laboratories.id', '=', 'lab_sessions.lab_id')
            ->join('modules', 'modules.id', '=', 'laboratories.module_id')
            ->where('lab_sessions.status', 'completed')
            ->when($since, fn ($q) => $q->where('lab_sessions.created_at', '>=', $since))
            ->groupBy('modules.class_id')
            ->selectRaw('modules.class_id, count(*) as submissions, avg(' . self::GRADE_SQL . ') as avg_grade')
            ->get()->keyBy('class_id');

        $certificates = DB::table('certificates')->groupBy('class_id')
            ->selectRaw('class_id, count(*) as total')->pluck('total', 'class_id');

        return $classes->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'code' => $c->code,
            'instructor' => $c->instructor->name ?? 'Unassigned',
            'status' => $c->isEnded() ? 'ended' : 'active',
            'students' => (int) $c->enrolled_count,
            'labs' => (int) ($labCounts[$c->id] ?? 0),
            'submissions' => (int) ($grades[$c->id]->submissions ?? 0),
            'avg_grade' => isset($grades[$c->id]) ? round((float) $grades[$c->id]->avg_grade, 1) : null,
            'certificates' => (int) ($certificates[$c->id] ?? 0),
        ])->all();
    }
}
