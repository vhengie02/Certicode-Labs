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
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
}
