<?php

namespace Tests\Feature;

use App\Models\Anomaly;
use App\Models\LabSession;
use App\Models\Laboratory;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrityFlagsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $otherInstructor;
    private SchoolClass $class;
    private LabSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $make = fn ($name, $role) => User::create(['name' => $name, 'email' => strtolower(str_replace(' ', '.', $name)) . '@example.com', 'password' => bcrypt('password'), 'role' => $role]);
        $this->owner = $make('Owner Instructor', 'instructor');
        $this->otherInstructor = $make('Other Instructor', 'instructor');
        $student = $make('Sam Student', 'student');

        $this->class = SchoolClass::create(['name' => 'Java 101', 'code' => 'JAVA-101', 'instructor_id' => $this->owner->id]);
        $module = Module::create(['class_id' => $this->class->id, 'title' => 'Loops', 'content' => 'Content']);
        $lab = Laboratory::create(['module_id' => $module->id, 'title' => 'FizzBuzz', 'description' => 'x', 'time_limit' => 30, 'tasks_definition' => []]);
        $this->session = LabSession::create(['lab_id' => $lab->id, 'user_id' => $student->id, 'status' => 'in_progress', 'started_at' => now()]);
    }

    private function flag(array $attributes = []): Anomaly
    {
        return Anomaly::create($attributes + ['lab_session_id' => $this->session->id, 'type' => 'paste_anomaly', 'severity' => 'high', 'description' => 'x', 'resolved' => false]);
    }

    private function foreignFlag(): Anomaly
    {
        $class = SchoolClass::create(['name' => 'Other', 'code' => 'OTHER-1', 'instructor_id' => $this->otherInstructor->id]);
        $module = Module::create(['class_id' => $class->id, 'title' => 'M', 'content' => 'x']);
        $lab = Laboratory::create(['module_id' => $module->id, 'title' => 'L', 'description' => 'x', 'time_limit' => 30, 'tasks_definition' => []]);
        $session = LabSession::create(['lab_id' => $lab->id, 'user_id' => $this->otherInstructor->id, 'status' => 'in_progress', 'started_at' => now()]);

        return Anomaly::create(['lab_session_id' => $session->id, 'type' => 'paste_anomaly', 'severity' => 'high', 'description' => 'x', 'resolved' => false]);
    }

    public function test_bulk_resolve_marks_selected_flags_in_the_class_only()
    {
        [$a, $b, $untouched] = [$this->flag(), $this->flag(), $this->flag()];
        $foreign = $this->foreignFlag();

        $this->actingAs($this->owner)
            ->post("/classes/{$this->class->id}/anomalies/resolve", ['ids' => [$a->id, $b->id, $foreign->id]])
            ->assertRedirect()
            ->assertSessionHas('success', '2 flags marked as resolved.');

        $this->assertTrue($a->fresh()->resolved);
        $this->assertTrue($b->fresh()->resolved);
        $this->assertFalse($untouched->fresh()->resolved);
        $this->assertFalse($foreign->fresh()->resolved);
    }

    public function test_bulk_resolve_is_refused_for_another_instructors_class()
    {
        $flag = $this->flag();

        $this->actingAs($this->otherInstructor)
            ->post("/classes/{$this->class->id}/anomalies/resolve", ['ids' => [$flag->id]])
            ->assertForbidden();

        $this->assertFalse($flag->fresh()->resolved);
    }

    public function test_flags_table_sorts_by_the_chosen_column()
    {
        $this->flag(['severity' => 'low', 'description' => 'low-flag']);
        $this->flag(['severity' => 'high', 'description' => 'high-flag']);
        $this->flag(['severity' => 'medium', 'description' => 'medium-flag']);

        $this->actingAs($this->owner)
            ->get("/classes/{$this->class->id}/telemetry?sort=severity&dir=desc")
            ->assertOk()
            ->assertSeeInOrder(['high-flag', 'medium-flag', 'low-flag']);

        $this->actingAs($this->owner)
            ->get("/classes/{$this->class->id}/telemetry?sort=severity&dir=asc")
            ->assertSeeInOrder(['low-flag', 'medium-flag', 'high-flag']);
    }

    public function test_prune_deletes_only_old_resolved_flags()
    {
        config()->set('app.anomaly_retention_days', 90);

        $oldResolved = $this->flag(['resolved' => true]);
        $oldOpen = $this->flag();
        $recentResolved = $this->flag(['resolved' => true]);
        Anomaly::whereKey([$oldResolved->id, $oldOpen->id])->update(['created_at' => now()->subDays(91)]);

        $this->artisan('model:prune', ['--model' => [Anomaly::class]])->assertSuccessful();

        $this->assertNull($oldResolved->fresh());
        $this->assertNotNull($oldOpen->fresh());
        $this->assertNotNull($recentResolved->fresh());
    }
}
