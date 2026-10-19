<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected User $instructor;

    protected User $student;

    protected SchoolClass $schoolClass;

    protected Laboratory $laboratory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instructor = User::create([
            'name' => 'Instructor Doe',
            'email' => 'instructor@example.com',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $this->student = User::create([
            'name' => 'Student Jane',
            'email' => 'student@example.com',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $this->schoolClass = SchoolClass::create([
            'name' => 'Java Basics',
            'code' => 'JAVA101',
            'instructor_id' => $this->instructor->id,
        ]);

        $module = Module::create([
            'class_id' => $this->schoolClass->id,
            'title' => 'Exceptions',
            'content' => 'Syllabus content',
        ]);

        $this->laboratory = Laboratory::create([
            'title' => 'Custom Exceptions',
            'description' => 'Throw your own.',
            'time_limit' => 60,
            'module_id' => $module->id,
            'tasks_definition' => [
                ['id' => 1, 'task' => 'Create InvalidAgeException', 'command' => 'regex:class\s+InvalidAgeException'],
            ],
        ]);
    }

    private function enroll(): void
    {
        $this->schoolClass->students()->attach($this->student->id, ['status' => 'enrolled']);
    }

    private function titles(User $user): array
    {
        return $user->fresh()->notifications->pluck('data.title')->all();
    }

    public function test_joining_by_code_notifies_student_and_instructor_once()
    {
        $this->actingAs($this->student)->post('/classes/join', ['code' => 'java101']);
        $this->actingAs($this->student)->post('/classes/join', ['code' => 'JAVA101']);

        $this->assertSame(['Joined class: Java Basics'], $this->titles($this->student));
        $this->assertSame(['Student Joined Class: Java Basics'], $this->titles($this->instructor));
    }

    public function test_submitting_from_vscode_notifies_student()
    {
        $this->enroll();
        $session = LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $this->actingAs($this->student)
            ->postJson("/api/v1/sessions/{$session->id}/submit", ['code' => '// todo', 'language' => 'java'])
            ->assertOk();

        $this->assertSame(['Lab submitted: Custom Exceptions'], $this->titles($this->student));
    }

    public function test_completing_from_website_notifies_student()
    {
        $this->enroll();
        $session = LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $this->actingAs($this->student)->post("/sessions/{$session->id}/complete")->assertRedirect();

        $this->assertSame(['Lab submitted: Custom Exceptions'], $this->titles($this->student));
    }

    public function test_grade_override_notifies_every_team_member()
    {
        $this->enroll();
        $teammate = User::create([
            'name' => 'Team Mate',
            'email' => 'mate@example.com',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);
        $this->schoolClass->students()->attach($teammate->id, ['status' => 'enrolled']);

        $group = Group::create(['lab_id' => $this->laboratory->id, 'name' => 'Team A']);
        $group->members()->attach([$this->student->id, $teammate->id]);

        $session = LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'group_id' => $group->id,
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'ended_at' => now(),
            'performance_score' => 50,
        ]);

        $this->actingAs($this->instructor)
            ->postJson("/instructor/sessions/{$session->id}/override-grade", [
                'override_score' => 85,
                'override_reason' => 'Logic was right.',
            ])
            ->assertOk();

        foreach ([$this->student, $teammate] as $member) {
            $notification = $member->fresh()->notifications->first();
            $this->assertSame('Lab reviewed: Custom Exceptions', $notification->data['title']);
            $this->assertStringContainsString('85%', $notification->data['message']);
            $this->assertStringContainsString('Logic was right.', $notification->data['message']);
        }
    }
}
