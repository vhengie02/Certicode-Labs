<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\SchoolClass;
use Illuminate\Support\Str;

class CertificateController extends Controller
{
    /**
     * Claim a certificate for completing a class.
     */
    public function claim(int $class_id)
    {
        $user = auth()->user();
        $class = SchoolClass::with('modules.laboratories')->findOrFail($class_id);

        // Access check: must be enrolled
        $isEnrolled = $class->students()->where('student_id', $user->id)->wherePivot('status', 'enrolled')->exists();
        if (! $isEnrolled) {
            abort(403, 'You are not enrolled in this class.');
        }

        $result = Certificate::issueFor($class, $user);
        $certificate = $result['certificate'];
        if (! $certificate) {
            $progress = $result['progress'];
            $passScore = $class->lab_pass_score ?? SchoolClass::DEFAULT_LAB_PASS_SCORE;
            $message = "You need {$passScore}% or more on {$result['threshold']}% of this class's labs to claim a certificate. You've passed {$progress['passed']} of {$progress['total']}.";
            if ($progress['under_review'] > 0) {
                $message .= " Your instructor still has to review {$progress['under_review']} ".Str::plural('grade', $progress['under_review']).'.';
            }

            return redirect()->back()->with('error', $message);
        }

        if ($result['created']) {
            $certificate->notifyOwner(
                "Certificate Earned: {$class->name}",
                "Congratulations! You met the pass mark in '{$class->name}' and earned your certificate. Anyone can verify it with its QR code."
            );
        }

        return redirect()->route('certificates.show', $certificate->id)
            ->with('success', 'Your certificate has been successfully issued!');
    }

    /**
     * Display the specified certificate.
     */
    public function show(int $id)
    {
        $certificate = Certificate::with(['user', 'schoolClass.instructor'])->findOrFail($id);
        $user = auth()->user();

        // The student it was issued to, the class's instructor, or an admin
        if ($user->id !== $certificate->user_id && ! $user->canManageClass($certificate->schoolClass)) {
            abort(403, 'Unauthorized.');
        }

        return view('certificates.show', ['certificate' => $certificate, 'record' => $certificate->record()]);
    }

    /**
     * Publicly verify a certificate using its verification code.
     */
    public function verify($code)
    {
        $certificate = Certificate::with(['user', 'schoolClass.instructor'])
            ->where('verification_code', strtoupper(trim((string) $code)))
            ->first();

        if (! $certificate || ! $certificate->user || ! $certificate->schoolClass) {
            return response()->view('certificates.verify-failed', ['code' => mb_substr((string) $code, 0, 64)], 404);
        }

        return view('certificates.verify-success', ['certificate' => $certificate, 'record' => $certificate->record()]);
    }
}
