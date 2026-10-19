<?php

namespace Tests\Feature;

use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A student gets one attempt at a lab unless the instructor allows more. Every started
 * session counts, so abandoning a lab isn't a free retake; resuming an open one is fine.
 */
class LabAttemptLimitTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;

    private User $student;

    private Module $module;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instructor = User::create(['name' => 'Ina Instructor', 'email' => 'ina@example.com', 'password' => bcrypt('x'), 'role' => 'instructor']);
        $this->student = User::create(['name' => 'Sam Student', 'email' => 'sam@example.com', 'password' => bcrypt('x'), 'role' => 'student']);
        $class = SchoolClass::create(['name' => 'Java', 'code' => 'JAVA-1', 'instructor_id' => $this->instructor->id]);
        $class->students()->attach($this->student->id, ['status' => 'enrolled']);
        $this->module = Module::create(['class_id' => $class->id, 'title' => 'Loops', 'content' => 'x']);
    }

    private function lab(array $attributes = []): Laboratory
    {
        return Laboratory::create($attributes + ['title' => 'FizzBuzz', 'description' => 'x', 'time_limit' => 30, 'module_id' => $this->module->id]);
    }

    private function finishedAttempt(Laboratory $lab, string $status = 'completed'): void
    {
        LabSession::create(['lab_id' => $lab->id, 'user_id' => $this->student->id, 'status' => $status, 'started_at' => now()->subHour(), 'ended_at' => now()->subMinutes(30), 'performance_score' => 60]);
    }

    private function start(Laboratory $lab)
    {
        return $this->actingAs($this->student)->withCameraCheck($lab->id)->post(route('laboratories.start', $lab->id));
    }

    public function test_labs_allow_one_attempt_by_default()
    {
        $lab = $this->lab();
        $this->assertSame(1, $lab->fresh()->attemptLimit());

        $this->start($lab)->assertRedirect();
        $this->assertSame(1, LabSession::count());

        LabSession::query()->update(['status' => 'completed', 'ended_at' => now()]);
        $this->start($lab)->assertSessionHas('error');
        $this->assertSame(1, LabSession::count(), 'no second session was created');
    }

    public function test_an_abandoned_session_uses_up_the_attempt()
    {
        $lab = $this->lab();
        $this->finishedAttempt($lab, 'abandoned');

        $this->start($lab)->assertSessionHas('error');
        $this->assertSame(1, LabSession::count());
    }

    public function test_an_open_session_can_always_be_resumed()
    {
        $lab = $this->lab();
        $open = LabSession::create(['lab_id' => $lab->id, 'user_id' => $this->student->id, 'status' => 'in_progress', 'started_at' => now()]);

        $this->start($lab)->assertRedirect()->assertSessionMissing('error');
        $this->assertSame([$open->id], LabSession::pluck('id')->all());
    }

    public function test_instructor_can_allow_retakes()
    {
        $lab = $this->lab(['max_attempts' => 2]);
        $this->finishedAttempt($lab);

        $this->start($lab)->assertSessionMissing('error');
        $this->assertSame(2, LabSession::count());

        LabSession::query()->update(['status' => 'completed', 'ended_at' => now()]);
        $this->start($lab)->assertSessionHas('error');
        $this->assertSame(2, LabSession::count());
    }

    public function test_unlimited_attempts()
    {
        $lab = $this->lab(['max_attempts' => 0]);
        foreach (range(1, 5) as $i) {
            $this->finishedAttempt($lab);
        }

        $this->start($lab)->assertSessionMissing('error');
        $this->assertSame(6, LabSession::count());
    }

    public function test_the_extension_api_enforces_the_limit_too()
    {
        $lab = $this->lab();
        $this->finishedAttempt($lab);

        $this->actingAs($this->student)->postJson("/api/v1/labs/{$lab->id}/start")
            ->assertForbidden()
            ->assertJson(['error' => 'attempts_exhausted']);
    }

    public function test_instructors_are_not_limited_on_their_own_labs()
    {
        $lab = $this->lab();
        LabSession::create(['lab_id' => $lab->id, 'user_id' => $this->instructor->id, 'status' => 'completed', 'started_at' => now()->subHour()]);

        $this->assertTrue($lab->canStartNewAttempt($this->instructor));
    }

    public function test_lab_page_tells_the_student_how_many_attempts_they_have()
    {
        $lab = $this->lab(['max_attempts' => 3]);
        $this->finishedAttempt($lab);

        $this->actingAs($this->student)->get(route('laboratories.show', $lab->id))
            ->assertSee('Retake lab')
            ->assertSee('This will be attempt 2 of 3');

        $single = $this->lab(['title' => 'One shot']);
        $this->finishedAttempt($single);
        $this->actingAs($this->student)->get(route('laboratories.show', $single->id))
            ->assertSee('No attempts left')
            ->assertSee('your only attempt')
            ->assertDontSee('Retake lab');
    }

    public function test_instructor_sets_attempts_on_the_lab_form()
    {
        $this->actingAs($this->instructor)->post(route('laboratories.store'), [
            'title' => 'Bank Account',
            'description' => 'x',
            'time_limit' => 30,
            'module_id' => $this->module->id,
            'max_attempts' => 3,
        ])->assertRedirect();
        $lab = Laboratory::where('title', 'Bank Account')->firstOrFail();
        $this->assertSame(3, $lab->max_attempts);

        $this->actingAs($this->instructor)->get(route('laboratories.edit', $lab->id))
            ->assertSee('Attempts allowed')
            ->assertSee('<option value="3" selected', false);

        $this->actingAs($this->instructor)->post(route('laboratories.store'), [
            'title' => 'Too many', 'description' => 'x', 'time_limit' => 30, 'module_id' => $this->module->id, 'max_attempts' => 50,
        ])->assertSessionHasErrors('max_attempts');
    }
}
