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

    private function openAttempt(User $student): LabSession
    {
        return LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $student->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    private function anotherStudent(string $email = 'other@example.com'): User
    {
        return User::create([
            'name' => 'Student Sam',
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);
    }

    private function submitFromWebsite(User $student): void
    {
        $session = $this->openAttempt($student);
        $this->actingAs($student)->post("/sessions/{$session->id}/complete")->assertRedirect();
    }

    private function startExpiredLiveLab(): void
    {
        $this->laboratory->update([
            'availability_mode' => 'live',
            'live_duration_minutes' => 30,
            'live_status' => 'active',
            'live_started_at' => now()->subHour(),
        ]);
    }

    public function test_submitting_from_vscode_notifies_the_instructor_with_a_link_to_the_code()
    {
        $this->enroll();
        $session = $this->openAttempt($this->student);

        $this->actingAs($this->student)
            ->postJson("/api/v1/sessions/{$session->id}/submit", ['code' => '// todo', 'language' => 'java'])
            ->assertOk();

        $notification = $this->instructor->fresh()->notifications->sole();
        $this->assertSame('New submission: Custom Exceptions', $notification->data['title']);
        $this->assertStringContainsString('Student Jane', $notification->data['message']);
        $this->assertSame("/sessions/{$session->id}#submitted-files", $notification->data['url']);
    }

    public function test_a_class_submitting_together_notifies_the_instructor_once()
    {
        $this->submitFromWebsite($this->student);
        $this->travel(5)->minutes();
        $this->submitFromWebsite($this->anotherStudent());

        $this->assertSame(['New submission: Custom Exceptions'], $this->titles($this->instructor));
    }

    public function test_the_first_of_two_submissions_in_the_same_second_notifies_the_instructor()
    {
        $this->freezeSecond();
        $first = $this->openAttempt($this->student);
        $second = $this->openAttempt($this->anotherStudent());

        // Both are saved before either checks, as when two requests overlap
        $first->update(['status' => 'completed', 'ended_at' => now()]);
        $second->update(['status' => 'completed', 'ended_at' => now()]);
        $second->notifyInstructorOfSubmission();
        $first->notifyInstructorOfSubmission();

        $this->assertCount(1, $this->titles($this->instructor));
        $this->assertStringContainsString('Student Jane', $this->instructor->fresh()->notifications->sole()->data['message']);
    }

    public function test_a_submission_after_a_quiet_spell_notifies_the_instructor_again()
    {
        $this->submitFromWebsite($this->student);
        $this->travel(16)->minutes();
        $this->submitFromWebsite($this->anotherStudent());

        $this->assertCount(2, $this->titles($this->instructor));
    }

    public function test_a_submission_in_another_lab_does_not_quiet_this_one()
    {
        $this->submitFromWebsite($this->student);

        $this->laboratory = Laboratory::create([
            'title' => 'Second Lab',
            'description' => 'Another one.',
            'time_limit' => 60,
            'module_id' => $this->laboratory->module_id,
            'tasks_definition' => [],
        ]);
        $this->submitFromWebsite($this->anotherStudent());

        $this->assertEqualsCanonicalizing(
            ['New submission: Custom Exceptions', 'New submission: Second Lab'],
            $this->titles($this->instructor)
        );
    }

    public function test_an_instructor_finishing_an_attempt_gets_no_submission_notification()
    {
        $session = $this->openAttempt($this->student);

        $this->actingAs($this->instructor)->post("/sessions/{$session->id}/complete")->assertRedirect();

        $this->assertSame([], $this->titles($this->instructor));
    }

    public function test_a_live_timer_running_out_tells_the_instructor_how_many_were_submitted()
    {
        $this->startExpiredLiveLab();
        $this->openAttempt($this->student);
        $this->openAttempt($this->anotherStudent());

        $this->assertTrue($this->laboratory->fresh()->checkAndAutoCloseLive());

        $notification = $this->instructor->fresh()->notifications->sole();
        $this->assertSame('Live lab ended: Custom Exceptions', $notification->data['title']);
        $this->assertStringContainsString('2 open attempts were submitted', $notification->data['message']);
    }

    public function test_a_live_timer_running_out_with_no_open_attempts_sends_nothing()
    {
        $this->startExpiredLiveLab();

        $this->assertTrue($this->laboratory->fresh()->checkAndAutoCloseLive());

        $this->assertSame([], $this->titles($this->instructor));
    }

    public function test_ending_a_live_lab_by_hand_sends_the_instructor_nothing()
    {
        $this->startExpiredLiveLab();
        $this->openAttempt($this->student);

        $this->laboratory->fresh()->closeLive();

        $this->assertSame([], $this->titles($this->instructor));
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
            'ended_at' => now()->startOfSecond(),
            'performance_score' => 50,
        ]);
        // The team's submit graded the teammate's attempt too; one who never opened the lab
        // has no grade to be told about
        LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $teammate->id,
            'group_id' => $group->id,
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'ended_at' => $session->ended_at,
            'performance_score' => 50,
        ]);
        $absent = User::create(['name' => 'Absent Teammate', 'email' => 'absent@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $group->members()->attach($absent->id);

        $this->actingAs($this->instructor)
            ->postJson("/instructor/sessions/{$session->id}/override-grade", [
                'override_score' => 85,
                'override_reason' => 'Logic was right.',
            ])
            ->assertOk();

        $this->assertCount(0, $absent->fresh()->notifications);

        foreach ([$this->student, $teammate] as $member) {
            $notification = $member->fresh()->notifications->first();
            $this->assertSame('Lab reviewed: Custom Exceptions', $notification->data['title']);
            $this->assertStringContainsString('85%', $notification->data['message']);
            $this->assertStringContainsString('Logic was right.', $notification->data['message']);
        }
    }
}
