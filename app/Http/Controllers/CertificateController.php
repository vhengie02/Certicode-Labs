<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CertificateController extends Controller
{
    /**
     * Claim a certificate for completing a class.
     */
    public function claim($class_id)
    {
        $user = auth()->user();
        $class = SchoolClass::with('modules.laboratories')->findOrFail($class_id);

        // Access check: must be enrolled
        $isEnrolled = $class->students()->where('student_id', $user->id)->wherePivot('status', 'enrolled')->exists();
        if (!$isEnrolled) {
            abort(403, 'You are not enrolled in this class.');
        }

        // Verify progress is 100%
        $progress = $class->getStudentProgress($user);
        if ($progress['percent'] < 100 || $progress['total'] === 0) {
            return redirect()->back()->with('error', 'You must complete all laboratories in this class before claiming a certificate.');
        }

        // Check if certificate already exists
        $certificate = Certificate::where('user_id', $user->id)
            ->where('class_id', $class->id)
            ->first();

        if (!$certificate) {
            // Generate unique verification code
            $code = 'CERT-' . strtoupper(Str::random(12));
            while (Certificate::where('verification_code', $code)->exists()) {
                $code = 'CERT-' . strtoupper(Str::random(12));
            }

            // Create certificate
            $certificate = Certificate::create([
                'user_id' => $user->id,
                'class_id' => $class->id,
                'verification_code' => $code,
                'qr_code_path' => 'certificates/qr-' . $code . '.svg',
                'issued_at' => now(),
            ]);

            // Notify user
            $user->notify(new \App\Notifications\ClassActivityNotification(
                "Certificate Earned: {$class->name}",
                "Congratulations! You have completed all laboratories in '{$class->name}' and earned your verified competency certificate.",
                route('certificates.show', $certificate->id),
                'certificate'
            ));
        }

        return redirect()->route('certificates.show', $certificate->id)
            ->with('success', 'Your certificate has been successfully issued!');
    }

    /**
     * Display the specified certificate.
     */
    public function show($id)
    {
        $certificate = Certificate::with(['user', 'schoolClass.instructor'])->findOrFail($id);
        $user = auth()->user();

        // Check permissions (student owner, instructor, or admin)
        if ($user->role === 'student' && $user->id !== $certificate->user_id) {
            abort(403, 'Unauthorized.');
        }

        return view('certificates.show', compact('certificate'));
    }

    /**
     * Publicly verify a certificate using its verification code.
     */
    public function verify($code)
    {
        $certificate = Certificate::with(['user', 'schoolClass.modules.laboratories', 'schoolClass.instructor'])
            ->where('verification_code', strtoupper($code))
            ->first();

        if (!$certificate) {
            return view('certificates.verify-failed', ['code' => $code]);
        }

        return view('certificates.verify-success', compact('certificate'));
    }
}
