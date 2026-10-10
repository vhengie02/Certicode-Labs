<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'username',
        'gender',
        'email',
        'password',
        'role',
        'github_username',
        'gmail',
        'gmail_verified_at',
        'gmail_verification_code',
        'notify_class',
        'notify_module',
        'notify_lab',
        'notify_certificate',
        'notify_email_channel',
        'auth_user_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'gmail_verification_code',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'gmail_verified_at' => 'datetime',
            'instructor_requested_at' => 'datetime',
            'notify_class' => 'boolean',
            'notify_module' => 'boolean',
            'notify_lab' => 'boolean',
            'notify_certificate' => 'boolean',
            'notify_email_channel' => 'boolean',
        ];
    }

    /**
     * Get the classes the user is enrolled in (status = enrolled).
     */
    public function classes()
    {
        return $this->belongsToMany(SchoolClass::class, 'class_student', 'student_id', 'class_id')
                    ->wherePivot('status', 'enrolled')
                    ->withPivot('status')
                    ->withTimestamps();
    }

    /**
     * Get the classes the user is invited to (status = invited).
     */
    public function invitedClasses()
    {
        return $this->belongsToMany(SchoolClass::class, 'class_student', 'student_id', 'class_id')
                    ->wherePivot('status', 'invited')
                    ->withPivot('status')
                    ->withTimestamps();
    }

    /**
     * Get the classes instructed by this user.
     */
    public function instructedClasses()
    {
        return $this->hasMany(SchoolClass::class, 'instructor_id');
    }

    /**
     * Get the lab sessions started by the user.
     */
    public function labSessions()
    {
        return $this->hasMany(LabSession::class);
    }

    /**
     * Get the competencies achieved by the student.
     */
    public function studentCompetencies()
    {
        return $this->hasMany(StudentCompetency::class);
    }

    /**
     * Get the certificates issued to the user.
     */
    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }

    /**
     * Get the groups the user is a member of.
     */
    public function groups()
    {
        return $this->belongsToMany(Group::class, 'group_members')
                    ->withPivot('contribution_score')
                    ->withTimestamps();
    }

    /**
     * Route notifications for the mail channel.
     *
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return array<string, string>|string
     */
    public function routeNotificationForMail($notification)
    {
        if (!empty($this->gmail) && !empty($this->gmail_verified_at)) {
            return $this->gmail;
        }

        return $this->email;
    }

    /**
     * Record a request for instructor access. Self-service sign-ups never get the instructor
     * role directly: they stay students until an admin approves, and admins are notified.
     */
    public function requestInstructorAccess(): void
    {
        if ($this->role !== 'student' || $this->instructor_requested_at) {
            return;
        }

        $this->forceFill(['instructor_requested_at' => now()])->save();

        foreach (static::where('role', 'admin')->get() as $admin) {
            try {
                $admin->notify(new \App\Notifications\ClassActivityNotification(
                    'Instructor access requested',
                    "{$this->name} ({$this->email}) asked for instructor access.",
                    route('admin.instructor-requests.index'),
                    'info'
                ));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Could not notify admin about instructor request: ' . $e->getMessage());
            }
        }
    }

    public function hasPendingInstructorRequest(): bool
    {
        return $this->role === 'student' && $this->instructor_requested_at !== null;
    }

    /**
     * Whether this user may see and change a class and everything in it: its modules, labs,
     * student sessions, telemetry and grades. Admins manage every class; an instructor only
     * the classes they own. Accepts a class, a class id, or null (no class: never allowed).
     */
    public function canManageClass(SchoolClass|int|null $class): bool
    {
        if ($this->role === 'admin') {
            return true;
        }
        if ($this->role !== 'instructor' || $class === null) {
            return false;
        }

        $instructorId = $class instanceof SchoolClass
            ? $class->instructor_id
            : SchoolClass::whereKey($class)->value('instructor_id');

        return $instructorId !== null && (int) $instructorId === (int) $this->id;
    }
}
