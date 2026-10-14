<?php

namespace Tests\Feature;

use App\Models\Competency;
use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\StudentCompetency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * However a session ends up completed, reopened or regraded, the student's competency
 * scores follow it, and the backfill command repairs scores missed in the past.
 */
class CompetencyCreditTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;
    private User $student;
    private Competency $competency;
    private Laboratory $labA;
    private Laboratory $labB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instructor = User::create(['name' => 'Ina Instructor', 'email' => 'ina@example.com', 'password' => bcrypt('x'), 'role' => 'instructor']);
        $this->student = User::create(['name' => 'Sam Student', 'email' => 'sam@example.com', 'password' => bcrypt('x'), 'role' => 'student']);

        $class = SchoolClass::create(['name' => 'Algorithms', 'code' => 'ALG-1', 'instructor_id' => $this->instructor->id]);
        $class->students()->attach($this->student->id, ['status' => 'enrolled']);
        $module = Module::create(['class_id' => $class->id, 'title' => 'Sorting', 'content' => 'Sorts']);

        $this->competency = Competency::create(['code' => 'SORT-01', 'name' => 'Sorting']);
        $this->labA = Laboratory::create(['title' => 'Lab A', 'description' => 'A', 'time_limit' => 30, 'module_id' => $module->id]);
        $this->labB = Laboratory::create(['title' => 'Lab B', 'description' => 'B', 'time_limit' => 30, 'module_id' => $module->id]);
        $this->labA->competencies()->attach($this->competency->id);
        $this->labB->competencies()->attach($this->competency->id);
    }

    private function inProgress(Laboratory $lab, float $score): LabSession
    {
        return LabSession::create([
            'lab_id' => $lab->id,
            'user_id' => $this->student->id,
            'status' => 'in_progress',
            'started_at' => now()->subMinutes(5),
            'performance_score' => $score,
        ]);
    }

    private function score(): ?float
    {
        $value = StudentCompetency::where('user_id', $this->student->id)->where('competency_id', $this->competency->id)->value('score_achieved');

        return $value === null ? null : (float) $value;
    }

    public function test_website_complete_button_credits_competencies()
    {
        $session = $this->inProgress($this->labA, 80);

        $this->actingAs($this->student)->post("/sessions/{$session->id}/complete")->assertRedirect();

        $this->assertSame(80.0, $this->score());
    }

    public function test_instructor_ending_one_session_credits_competencies()
    {
        $session = $this->inProgress($this->labA, 70);

        $this->actingAs($this->instructor)->post("/instructor/sessions/{$session->id}/end")->assertRedirect();

        $this->assertSame(70.0, $this->score());
    }

    public function test_extension_ending_a_session_credits_competencies()
    {
        $session = $this->inProgress($this->labA, 65);

        $this->withHeaders(['X-Session-Token' => $session->issueExtensionToken()])
            ->postJson("/api/v1/sessions/{$session->id}/end")
            ->assertOk();

        $this->assertSame(65.0, $this->score());
    }

    public function test_extension_polling_an_expired_live_lab_credits_competencies()
    {
        $this->labA->update(['availability_mode' => 'live', 'live_duration_minutes' => 30, 'live_status' => 'closed', 'live_elapsed_seconds' => 1800]);
        $session = $this->inProgress($this->labA, 55);

        $this->withHeaders(['X-Session-Token' => $session->issueExtensionToken()])
            ->getJson("/api/v1/sessions/{$session->id}")
            ->assertOk();

        $this->assertSame('completed', $session->fresh()->status);
        $this->assertSame(55.0, $this->score());
    }

    public function test_closing_a_live_lab_still_credits_competencies()
    {
        $this->labA->update(['availability_mode' => 'live', 'live_duration_minutes' => 30]);
        $this->labA->openLive();
        $this->inProgress($this->labA, 90);

        $this->labA->fresh()->closeLive();

        $this->assertSame(90.0, $this->score());
    }

    public function test_an_instructor_override_moves_the_score()
    {
        $session = $this->inProgress($this->labA, 40);
        $session->update(['status' => 'completed']);
        $this->assertSame(40.0, $this->score());

        $session->update(['instructor_grade_override' => 95]);

        $this->assertSame(95.0, $this->score());
    }

    public function test_reopening_the_only_completed_session_removes_the_score()
    {
        $session = $this->inProgress($this->labA, 80);
        $session->update(['status' => 'completed']);
        $this->assertSame(80.0, $this->score());

        $this->actingAs($this->instructor)->post("/instructor/sessions/{$session->id}/reopen");

        $this->assertSame('in_progress', $session->fresh()->status);
        $this->assertNull($this->score());
    }

    public function test_reopening_one_lab_leaves_the_other_labs_grade()
    {
        $a = $this->inProgress($this->labA, 60);
        $b = $this->inProgress($this->labB, 100);
        $a->update(['status' => 'completed']);
        $b->update(['status' => 'completed']);
        $this->assertSame(80.0, $this->score());

        $b->update(['status' => 'in_progress']);

        $this->assertSame(60.0, $this->score());
    }

    public function test_a_heartbeat_on_a_completed_session_does_not_recalculate()
    {
        $session = $this->inProgress($this->labA, 80);
        $session->update(['status' => 'completed']);
        $row = StudentCompetency::where('user_id', $this->student->id)->firstOrFail();
        $this->travel(5)->minutes();

        \Illuminate\Support\Facades\DB::enableQueryLog();
        $session->update(['last_ping_at' => now()]);
        $queries = collect(\Illuminate\Support\Facades\DB::getQueryLog())->pluck('query')->implode("\n");

        $this->assertStringNotContainsString('laboratory_competency', $queries);
        $this->assertEquals($row->updated_at, $row->fresh()->updated_at);
    }

    public function test_moving_a_completed_session_to_another_student_moves_the_score()
    {
        $other = User::create(['name' => 'Oli Other', 'email' => 'oli@example.com', 'password' => bcrypt('x'), 'role' => 'student']);
        $session = $this->inProgress($this->labA, 75);
        $session->update(['status' => 'completed']);
        $this->assertSame(75.0, $this->score());

        $session->update(['user_id' => $other->id]);

        $this->assertNull($this->score());
        $this->assertEquals(75.0, StudentCompetency::where('user_id', $other->id)->value('score_achieved'));
    }

    public function test_backfill_removes_scores_left_stale_by_reopened_work()
    {
        // A score from before the fix whose only completed session was later reopened
        StudentCompetency::create(['user_id' => $this->student->id, 'competency_id' => $this->competency->id, 'score_achieved' => 88]);
        LabSession::withoutEvents(fn () => $this->inProgress($this->labA, 88));

        $this->artisan('certicode:refresh-competencies')
            ->expectsOutputToContain('1 removed')
            ->assertSuccessful();

        $this->assertNull($this->score());
    }

    public function test_backfill_repairs_scores_missed_in_the_past_and_is_idempotent()
    {
        // History written before the fix: completed sessions that never credited anything
        LabSession::withoutEvents(function () {
            foreach ([[$this->labA, 50], [$this->labB, 70]] as [$lab, $score]) {
                LabSession::create(['lab_id' => $lab->id, 'user_id' => $this->student->id, 'status' => 'completed', 'started_at' => now(), 'performance_score' => $score]);
            }
        });
        $this->assertNull($this->score());

        $this->artisan('certicode:refresh-competencies', ['--dry-run' => true])->assertSuccessful();
        $this->assertNull($this->score(), 'a dry run writes nothing');

        $this->artisan('certicode:refresh-competencies')->assertSuccessful();
        $this->assertSame(60.0, $this->score());

        $this->artisan('certicode:refresh-competencies')
            ->expectsOutputToContain('0 changed')
            ->assertSuccessful();
        $this->assertSame(60.0, $this->score());
    }
}
