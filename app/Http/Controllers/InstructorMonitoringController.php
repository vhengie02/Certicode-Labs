<?php

namespace App\Http\Controllers;

use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Anomaly;
use App\Models\TelemetryLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InstructorMonitoringController extends Controller
{
    /**
     * Display live instructor monitoring dashboard for a laboratory.
     */
    public function show(Request $request, int $labId)
    {
        $this->authorizeInstructor();

        $laboratory = Laboratory::with(['module.schoolClass.instructor', 'module.schoolClass.students'])->findOrFail($labId);
        $laboratory->checkAndAutoCloseLive();
        LabSession::autoExpireStaleSessions($laboratory->id);
        $schoolClass = $laboratory->module ? $laboratory->module->schoolClass : null;

        $sortBy = $laboratory->is_group_lab ? $request->query('sort', 'name') : 'name'; // 'name' or 'group'

        $sessionsQuery = LabSession::with(['user', 'group', 'anomalies', 'overriddenByUser'])
            ->where('lab_id', $laboratory->id);

        $sessions = $sessionsQuery->get();

        // Sort collection
        if ($sortBy === 'group') {
            $sessions = $sessions->sortBy(function ($s) {
                return $s->group ? $s->group->name : ($s->user ? $s->user->name : '');
            })->values();
        } else {
            $sessions = $sessions->sortBy(function ($s) {
                if (!$s->user) {
                    return '';
                }
                $parts = explode(' ', trim($s->user->name));
                return end($parts); // sort by last name
            })->values();
        }

        $totalStudents = $sessions->count();
        $activeSessions = $sessions->filter(fn($s) => $s->status === 'in_progress' && $s->isActivelyConnected())->count();
        $completedSessions = $sessions->where('status', 'completed')->count();
        $totalAnomalies = Anomaly::whereIn('lab_session_id', $sessions->pluck('id'))->count();
        $avgWpm = $sessions->count() > 0 ? round($sessions->avg('wpm')) : 0;

        $similarityService = app(\App\Services\CodeSimilarityService::class);
        $plagiarismAnalysis = $similarityService->analyzeLabCohort($laboratory->id, 75.0, 50.0);

        return view('instructor.monitoring.session', compact(
            'laboratory',
            'schoolClass',
            'sessions',
            'sortBy',
            'totalStudents',
            'activeSessions',
            'completedSessions',
            'totalAnomalies',
            'avgWpm',
            'plagiarismAnalysis'
        ));
    }

    /**
     * Run on-demand cohort plagiarism and code similarity analysis.
     */
    public function checkPlagiarism(Request $request, int $labId)
    {
        $this->authorizeInstructor();

        $laboratory = Laboratory::findOrFail($labId);
        $threshold = (float) $request->query('threshold', 75.0);

        $similarityService = app(\App\Services\CodeSimilarityService::class);
        $results = $similarityService->analyzeLabCohort($laboratory->id, $threshold, 50.0);

        return response()->json([
            'status' => 'success',
            'analysis' => $results,
        ]);
    }

    /**
     * Stream live telemetry JSON data for AJAX polling or dashboard update.
     */
    public function streamData(int $labId)
    {
        $this->authorizeInstructor();

        $laboratory = Laboratory::findOrFail($labId);
        $laboratory->checkAndAutoCloseLive();
        LabSession::autoExpireStaleSessions($laboratory->id);

        $sharedCountdown = null;
        if ($laboratory->isLiveLab()) {
            $remaining = $laboratory->getRemainingLiveSeconds();
            $sharedCountdown = [
                'remaining_seconds' => $remaining,
                'remaining_formatted' => sprintf('%02d:%02d', floor($remaining / 60), $remaining % 60),
                'live_status' => $laboratory->live_status,
                'is_expired' => $laboratory->isLiveClosed(),
            ];
        }

        $sessions = LabSession::with(['user', 'group', 'anomalies', 'overriddenByUser'])
            ->where('lab_id', $laboratory->id)
            ->get()
            ->map(function ($s) {
                $tasksCount = is_array($s->completed_tasks) ? count($s->completed_tasks) : 0;
                $duration = 0;
                if ($s->started_at) {
                    $end = $s->ended_at ?: now();
                    $duration = $end->diffInSeconds($s->started_at);
                }

                return [
                    'id' => $s->id,
                    'user_name' => $s->user->name ?? 'Student',
                    'email' => $s->user->email ?? '',
                    'group_name' => $s->group->name ?? null,
                    'is_team' => (bool) $s->group_id,
                    'status' => $s->status,
                    'connection_state' => $s->getConnectionState(),
                    'is_connected' => $s->isActivelyConnected(),
                    'is_idle' => $s->isIdle(),
                    'is_offline' => $s->isOffline(),
                    'last_ping_human' => $s->last_ping_at ? $s->last_ping_at->diffForHumans() : ($s->started_at ? $s->started_at->diffForHumans() : 'Never'),
                    'wpm' => $s->wpm ?? 0,
                    'keystroke_count' => $s->keystroke_count ?? 0,
                    'focus_lost_count' => $s->focus_lost_count ?? 0,
                    'paste_anomaly_count' => $s->paste_anomaly_count ?? 0,
                    'tasks_completed' => $tasksCount,
                    'elapsed_time' => sprintf('%02d:%02d', floor($duration / 60), $duration % 60),
                    'diff_stats' => $s->diff_stats ?? ['added' => 0, 'deleted' => 0],
                    'code_contributions' => $s->code_contributions ?? [],
                    'performance_score' => $s->performance_score,
                    'instructor_grade_override' => $s->instructor_grade_override,
                    'effective_score' => $s->effective_score,
                    'is_overridden' => $s->isGradeOverridden(),
                    'ai_grade_summary' => $s->ai_grade_summary,
                    'override_reason' => $s->instructor_override_reason,
                    'overridden_at' => $s->instructor_overridden_at ? $s->instructor_overridden_at->diffForHumans() : null,
                    'overridden_by_name' => $s->overriddenByUser->name ?? null,
                    'anomalies' => $s->anomalies->map(function ($a) {
                        return [
                            'id' => $a->id,
                            'type' => $a->type,
                            'severity' => $a->severity,
                            'description' => $a->description,
                            'metadata' => $a->metadata,
                            'image_path' => $a->image_path,
                            'created_at' => $a->created_at ? $a->created_at->diffForHumans() : '',
                        ];
                    }),
                ];
            });

        return response()->json([
            'status' => 'success',
            'availability_mode' => $laboratory->availability_mode ?? 'open',
            'shared_countdown' => $sharedCountdown,
            'active_workspaces_count' => $sessions->where('is_connected', true)->count(),
            'total_students_count' => $sessions->count(),
            'sessions' => $sessions,
        ]);
    }

    /**
     * Override grade for a student/team lab session.
     */
    public function overrideGrade(Request $request, int $id)
    {
        $this->authorizeInstructor();

        $session = LabSession::with(['user', 'laboratory'])->findOrFail($id);

        $request->validate([
            'override_score' => 'required|numeric|min:0|max:100',
            'override_reason' => 'nullable|string|max:1000',
        ]);

        $overrideScore = round((float) $request->input('override_score'), 2);
        $reason = $request->input('override_reason');

        $session->update([
            'instructor_grade_override' => $overrideScore,
            'instructor_override_reason' => $reason,
            'instructor_overridden_at' => now(),
            'overridden_by' => Auth::id(),
        ]);

        // Update StudentCompetency to reflect overridden effective grade
        if ($session->user) {
            $competency = \App\Models\Competency::firstOrCreate([
                'code' => 'COMP-JAVA-01',
            ], [
                'name' => 'Java OOP and Custom Exceptions Mastery',
            ]);

            \App\Models\StudentCompetency::updateOrCreate([
                'user_id' => $session->user->id,
                'competency_id' => $competency->id,
            ], [
                'score_achieved' => max($overrideScore, 0.0),
            ]);
        }

        TelemetryLog::create([
            'lab_session_id' => $session->id,
            'event_type' => 'instructor_grade_override',
            'payload' => [
                'instructor_id' => Auth::id(),
                'instructor_name' => Auth::user()->name ?? 'Instructor',
                'original_score' => $session->performance_score,
                'override_score' => $overrideScore,
                'reason' => $reason,
                'timestamp' => now()->toIso8601String(),
            ],
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Grade successfully overridden.',
                'session' => [
                    'id' => $session->id,
                    'performance_score' => $session->performance_score,
                    'instructor_grade_override' => $session->instructor_grade_override,
                    'effective_score' => $session->effective_score,
                    'is_overridden' => $session->isGradeOverridden(),
                    'override_reason' => $session->instructor_override_reason,
                    'overridden_at' => $session->instructor_overridden_at ? $session->instructor_overridden_at->diffForHumans() : null,
                    'overridden_by_name' => Auth::user()->name ?? 'Instructor',
                ],
            ]);
        }

        return back()->with('success', 'Grade successfully overridden.');
    }

    /**
     * Authorize instructor or admin.
     */
    protected function authorizeInstructor()
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            abort(403, 'Unauthorized action.');
        }
        $role = $user->role;
        if ($role !== 'admin' && $role !== 'instructor') {
            abort(403, 'Unauthorized action.');
        }
    }
}
