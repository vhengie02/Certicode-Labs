<?php

namespace App\Http\Controllers;

use App\Models\Laboratory;
use Illuminate\Http\Request;

/**
 * A student's attempt at a lab from the website: starting it (live-lab and camera gates,
 * VS Code deep link), opening and completing the session, and downloading starter files.
 * Instructors create and run the labs themselves in LaboratoryController.
 */
class LabAttemptController extends Controller
{
    use Concerns\AuthorizesClassAccess;

    /**
     * Start a new laboratory session for a student and redirect to VS Code.
     */
    public function startSession(Request $request, int $id)
    {
        $laboratory = Laboratory::findOrFail($id);
        $this->authorizeLabViewer($laboratory);
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        $laboratory->recordViewBy($user);

        // Feature 9: Live Lab availability gating
        if ($laboratory->isLiveLab()) {
            $laboratory->checkAndAutoCloseLive();

            if ($laboratory->isLiveNotStarted()) {
                return back()->with('error', 'This Live Lab has not been started yet. Please wait for the instructor to manually open the session.');
            }

            if ($laboratory->isLiveClosed() || $laboratory->getRemainingLiveSeconds() <= 0) {
                return back()->with('error', 'The countdown for this Live Lab has expired. Late joiners are not permitted to start.');
            }
        }

        // Feature 8: Pre-Lab Camera Proctoring Verification Gate for Students
        $isCameraVerified = $request->boolean('camera_verified') || session("camera_verified_lab_{$laboratory->id}", false);
        if ($user && $user->role === 'student' && !$isCameraVerified) {
            return back()->with('error', 'Webcam presence verification is mandatory before launching the workspace. Please complete the camera check.');
        }

        // Find existing in-progress session
        $session = \App\Models\LabSession::where('lab_id', $laboratory->id)
            ->where('user_id', $user ? $user->id : null)
            ->where('status', 'in_progress')
            ->latest()
            ->first();

        // If session exists but has exceeded its time limit, mark it abandoned and start fresh
        if ($session && $laboratory->time_limit > 0) {
            $elapsed = $session->started_at ? (int) $session->started_at->diffInSeconds(now(), true) : 0;
            if ($elapsed >= ($laboratory->time_limit * 60)) {
                $session->update([
                    'status' => 'abandoned',
                    'ended_at' => now(),
                ]);
                $session = null;
            }
        }

        if (!$session && $user && !$laboratory->canStartNewAttempt($user)) {
            $message = 'You have used all your attempts for this lab. Ask your instructor if you need another one.';
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['error' => 'attempts_exhausted', 'message' => $message], 403);
            }

            return back()->with('error', $message);
        }

        if (!$session) {
            $session = \App\Models\LabSession::create([
                'lab_id' => $laboratory->id,
                'user_id' => $user ? $user->id : null,
                'status' => 'in_progress',
                'started_at' => now(),
                'performance_score' => 0.0,
            ]);
        }

        // Attach prelab verification telemetry if verified
        if ($isCameraVerified && $session) {
            \App\Models\TelemetryLog::firstOrCreate([
                'lab_session_id' => $session->id,
                'event_type' => 'webcam_prelab_verification',
            ], [
                'payload' => [
                    'status' => 'granted',
                    'face_count' => 1,
                    'verified_at' => now()->toIso8601String(),
                ],
            ]);
        }

        $backendUrl = request()->getSchemeAndHttpHost();
        $vscodeUrl = "vscode://certicode.certicode-labs/connect?sessionId={$session->id}&endpoint=" . urlencode($backendUrl)
            . "&backendUrl=" . urlencode($backendUrl) . "&token=" . urlencode($session->issueExtensionToken());

        if ($request->wantsJson() || $request->ajax() || $request->header('Accept') === 'application/json') {
            return response()->json([
                'status' => 'success',
                'session_id' => $session->id,
                'vscode_url' => $vscodeUrl,
                'endpoint' => $backendUrl,
                'backend_url' => $backendUrl,
            ]);
        }

        return redirect($vscodeUrl);
    }

    /**
     * Display or redirect to the laboratory workspace session.
     */
    public function showWorkspace(int $id)
    {
        $session = \App\Models\LabSession::findOrFail($id);

        if (auth()->id() !== $session->user_id) {
            if (auth()->user()->role === 'student') {
                abort(403, 'Unauthorized.');
            }
            $this->authorizeSessionManager($session);
        }

        return redirect()->route('laboratories.show', $session->lab_id);
    }

    /**
     * Complete and grade the laboratory workspace session.
     */
    public function completeSession(int $id)
    {
        $session = \App\Models\LabSession::findOrFail($id);

        // Check ownership
        if (auth()->id() !== $session->user_id) {
            if (auth()->user()->role === 'student') {
                abort(403, 'Unauthorized.');
            }
            $this->authorizeSessionManager($session);
        }

        // Only an open attempt can be completed: a timed-out or already finished one keeps its result
        if (!$session->acceptsWork()) {
            return back()->with('error', 'This lab attempt has already ended, so it can no longer be completed.');
        }

        $session->update([
            'status' => 'completed',
            'ended_at' => now(),
        ]);

        // Log completion in telemetry
        \App\Models\TelemetryLog::create([
            'lab_session_id' => $session->id,
            'event_type' => 'session_completed',
            'payload' => [
                'final_score' => $session->performance_score,
                'completed_tasks_count' => count($session->completed_tasks ?? []),
            ],
        ]);

        // Find associated class to redirect back
        $lab = $session->laboratory;

        $session->notifyStudents(
            "Lab submitted: {$lab->title}",
            "Your submission for '{$lab->title}' was received. Score: {$session->performance_score}%."
        );
        $classId = null;
        if ($lab->module_id) {
            $module = \App\Models\Module::find($lab->module_id);
            if ($module) {
                $classId = $module->class_id;
            }
        }

        $redirect = $classId ? redirect()->route('classes.show', $classId) : redirect()->route('classes.index');
        return $redirect->with('success', "Laboratory session completed successfully! Final Performance Score: {$session->performance_score}%.");
    }

    /**
     * Download starter files as a zip archive or single file.
     */
    public function downloadStarterFiles(int $id)
    {
        $laboratory = Laboratory::findOrFail($id);
        $this->authorizeLabViewer($laboratory);
        $files = $laboratory->getStarterFilesList();

        if (empty($files)) {
            return back()->with('error', 'No starter files found for this laboratory.');
        }

        // If single file, download directly
        if (count($files) === 1) {
            $file = $files[0];
            $fileName = $file['name'] ?? 'starter_code.txt';
            $content = $file['content'] ?? '';

            return response($content, 200, [
                'Content-Type' => 'text/plain',
                'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            ]);
        }

        // Multiple files: pack into a zip archive
        $zipName = \Illuminate\Support\Str::slug($laboratory->title) . '-starter-files.zip';
        $tempPath = tempnam(sys_get_temp_dir(), 'certicode_zip_');
        $zip = new \ZipArchive();

        if ($zip->open($tempPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            foreach ($files as $file) {
                $name = $file['name'] ?? 'file.txt';
                $content = $file['content'] ?? '';
                $zip->addFromString($name, $content);
            }
            $zip->close();

            return response()->download($tempPath, $zipName)->deleteFileAfterSend(true);
        }

        return back()->with('error', 'Failed to generate starter files archive.');
    }
}
