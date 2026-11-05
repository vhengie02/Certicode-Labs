<?php

namespace App\Support;

use App\Models\Anomaly;
use App\Models\LabSession;
use App\Models\LabSessionChat;
use App\Models\LaboratoryView;
use App\Models\ModuleView;
use App\Models\SchoolClass;
use App\Models\TelemetryLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Self-service account deletion. A plain delete cascades through lab_sessions and certificates,
 * so anyone whose records other people rely on is scrubbed instead: their sign-in, contact details
 * and monitoring data go, and their name and grades stay so certificates still verify and
 * teammates keep their group lab.
 */
class AccountDeletion
{
    /**
     * Why this account can't delete itself yet, or null when it can.
     */
    public function blocker(User $user): ?string
    {
        if ($user->role === 'admin' && User::where('role', 'admin')->count() <= 1) {
            return "You're the only admin, so this account can't be deleted. Make someone else an admin first.";
        }

        // Classes cascade-delete with their instructor, taking every student's work with them
        $classCount = SchoolClass::where('instructor_id', $user->id)->count();
        if ($classCount > 0) {
            return "You teach {$classCount} ".Str::plural('class', $classCount).'. Ask an admin to reassign '
                .($classCount === 1 ? 'it' : 'them').' before deleting your account.';
        }

        return null;
    }

    /**
     * Whether deleting this account leaves their name and grades in place.
     */
    public function keepsRecord(User $user): bool
    {
        return $user->certificates()->exists()
            || $user->labSessions()->whereNotNull('group_id')->exists();
    }

    public function delete(User $user): void
    {
        $sessionIds = LabSession::where('user_id', $user->id)->pluck('id');
        $legacyFiles = Anomaly::whereIn('lab_session_id', $sessionIds)
            ->where('image_path', 'like', 'storage/%')
            ->pluck('image_path');

        DB::transaction(function () use ($user) {
            $email = $user->email;

            // Messages in a shared chat stay readable for teammates, without the author's name
            LabSessionChat::where('user_id', $user->id)->update(['user_id' => null, 'user_name' => 'Deleted user']);
            $user->notifications()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            if ($this->keepsRecord($user)) {
                $this->scrub($user);
            } else {
                $user->delete();
            }
        });

        foreach ($legacyFiles as $path) {
            Storage::disk('public')->delete(Str::after($path, 'storage/'));
        }
    }

    private function scrub(User $user): void
    {
        // A group lab's session is shared: its record stays, minus this student's camera images
        $ownSessions = LabSession::where('user_id', $user->id)->whereNull('group_id')->pluck('id');
        $groupSessions = LabSession::where('user_id', $user->id)->whereNotNull('group_id')->pluck('id');

        TelemetryLog::whereIn('lab_session_id', $ownSessions)->delete();
        Anomaly::whereIn('lab_session_id', $ownSessions)->delete();
        LabSession::whereIn('id', $ownSessions)->update([
            'submitted_code' => null,
            'submitted_files' => null,
            'github_repo_url' => null,
        ]);
        LabSession::where('user_id', $user->id)->update(['extension_token' => null]);

        Anomaly::whereIn('lab_session_id', $groupSessions)->whereNotNull('image_path')->get()
            ->each(function (Anomaly $flag) {
                $flag->update([
                    'image_path' => null,
                    'metadata' => collect($flag->metadata ?? [])->except('image_base64')->all(),
                ]);
            });

        LaboratoryView::where('user_id', $user->id)->delete();
        ModuleView::where('user_id', $user->id)->delete();

        // Off every roster, except the classes a certificate points at
        DB::table('class_student')
            ->where('student_id', $user->id)
            ->whereNotIn('class_id', $user->certificates()->whereNotNull('class_id')->pluck('class_id'))
            ->delete();

        $user->forceFill([
            'email' => "deleted-{$user->id}@deleted.invalid",
            'username' => "deleted-{$user->id}",
            'password' => Str::random(64),
            'remember_token' => null,
            'email_verified_at' => null,
            'gender' => null,
            'github_username' => null,
            'gmail' => null,
            'gmail_verified_at' => null,
            'gmail_verification_code' => null,
            'auth_user_id' => null,
            'instructor_requested_at' => null,
            'timezone' => null,
            'date_style' => null,
            'notify_email_channel' => false,
            'notify_class' => false,
            'notify_module' => false,
            'notify_lab' => false,
            'notify_grade' => false,
            'notify_certificate' => false,
            'notify_integrity' => false,
        ])->save();
    }
}
