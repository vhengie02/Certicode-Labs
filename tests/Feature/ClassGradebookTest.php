<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\User;
use App\Support\ClassGradebook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;
use ZipArchive;

class ClassGradebookTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;

    private User $alice;

    private User $bob;

    private SchoolClass $class;

    /** @var list<Laboratory> in syllabus order: Loops, Arrays (module 1), Classes (its section) */
    private array $labs = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->instructor = $this->user('Prof', 'instructor');
        $this->alice = $this->user('Alice Able');
        $this->bob = $this->user('Bob Baker');
        $this->class = SchoolClass::create(['name' => 'Java 101', 'code' => 'JAVA-101', 'instructor_id' => $this->instructor->id, 'passing_threshold' => 60, 'lab_pass_score' => 70]);
        $this->class->students()->attach($this->alice->id, ['status' => 'enrolled']);
        $this->class->students()->attach($this->bob->id, ['status' => 'enrolled']);

        $module = Module::create(['class_id' => $this->class->id, 'title' => 'Basics', 'content' => 'x', 'order_index' => 1]);
        $section = Module::create(['class_id' => $this->class->id, 'parent_id' => $module->id, 'title' => 'Objects', 'content' => 'x', 'order_index' => 1]);
        $this->labs[] = $this->lab($module, 'Loops');
        $this->labs[] = $this->lab($module, 'Arrays');
        $this->labs[] = $this->lab($section, 'Classes');
    }

    private function user(string $name, string $role = 'student'): User
    {
        return User::create(['name' => $name, 'email' => strtolower(str_replace(' ', '.', $name)).'@example.com', 'password' => bcrypt('password'), 'role' => $role]);
    }

    private function lab(Module $module, string $title): Laboratory
    {
        return Laboratory::create(['module_id' => $module->id, 'title' => $title, 'description' => 'x', 'time_limit' => 30, 'tasks_definition' => []]);
    }

    private function attempt(User $student, Laboratory $lab, array $attributes = []): LabSession
    {
        return LabSession::create($attributes + [
            'lab_id' => $lab->id,
            'user_id' => $student->id,
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'ended_at' => now(),
        ]);
    }

    private function gradebook(): ClassGradebook
    {
        return ClassGradebook::for($this->class->fresh());
    }

    /** @return array<string, mixed> */
    private function cell(ClassGradebook $gradebook, User $student, Laboratory $lab): array
    {
        $row = collect($gradebook->rows)->first(fn ($row) => $row['student']->id === $student->id);

        return $row['cells'][$lab->id];
    }

    /**
     * Download the export and read every sheet back, as lists of rows of cell values.
     *
     * @return array{0: array<string, list<list<mixed>>>, 1: string}
     */
    private function export(): array
    {
        $response = $this->actingAs($this->instructor)->get(route('classes.gradebook.export', $this->class->id));
        $response->assertOk();
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Java-101-gradebook-', $response->headers->get('Content-Disposition'));

        $path = tempnam(sys_get_temp_dir(), 'gradebook').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        $reader = new Reader;
        $reader->open($path);
        $sheets = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $sheets[$sheet->getName()][] = $row->toArray();
            }
        }
        $reader->close();

        return [$sheets, $path];
    }

    public function test_each_cell_is_the_best_completed_attempt(): void
    {
        $this->attempt($this->alice, $this->labs[0], ['performance_score' => 90]);
        $this->attempt($this->alice, $this->labs[0], ['performance_score' => 40]);
        // A higher-scored attempt that never completed doesn't count
        $this->attempt($this->alice, $this->labs[0], ['performance_score' => 99, 'status' => 'abandoned']);

        $cell = $this->cell($this->gradebook(), $this->alice, $this->labs[0]);

        $this->assertSame(ClassGradebook::GRADED, $cell['state']);
        $this->assertEquals(90, $cell['score']);
        $this->assertFalse($cell['overridden']);
    }

    public function test_an_instructor_regrade_wins_over_the_ai_score_and_is_marked(): void
    {
        $regraded = $this->attempt($this->alice, $this->labs[0], [
            'performance_score' => 50,
            'instructor_grade_override' => 85,
            'instructor_override_reason' => 'Logic correct, naming off',
            'instructor_overridden_at' => now(),
            'overridden_by' => $this->instructor->id,
        ]);
        // Another attempt's AI score is higher than this one's but below the regrade
        $this->attempt($this->alice, $this->labs[0], ['performance_score' => 80]);

        $cell = $this->cell($this->gradebook(), $this->alice, $this->labs[0]);

        $this->assertEquals(85, $cell['score']);
        $this->assertTrue($cell['overridden']);
        $this->assertEquals(50, $cell['ai_score']);
        $this->assertSame($regraded->id, $cell['session_id']);
        $this->assertSame('Prof', $cell['regrade']['by']);
        $this->assertSame('Logic correct, naming off', $cell['regrade']['reason']);
    }

    public function test_a_regrade_down_can_make_another_attempt_the_best(): void
    {
        $this->attempt($this->alice, $this->labs[0], ['performance_score' => 95, 'instructor_grade_override' => 30, 'overridden_by' => $this->instructor->id]);
        $this->attempt($this->alice, $this->labs[0], ['performance_score' => 60]);

        $cell = $this->cell($this->gradebook(), $this->alice, $this->labs[0]);

        $this->assertEquals(60, $cell['score']);
        $this->assertFalse($cell['overridden']);
    }

    public function test_cells_without_a_grade_say_why(): void
    {
        $open = $this->attempt($this->alice, $this->labs[0], ['status' => 'in_progress', 'ended_at' => null]);
        $ended = $this->attempt($this->alice, $this->labs[1], ['status' => 'abandoned']);

        $gradebook = $this->gradebook();

        $this->assertSame(ClassGradebook::IN_PROGRESS, $this->cell($gradebook, $this->alice, $this->labs[0])['state']);
        $this->assertSame($open->id, $this->cell($gradebook, $this->alice, $this->labs[0])['session_id']);
        $this->assertSame(ClassGradebook::TIMED_OUT, $this->cell($gradebook, $this->alice, $this->labs[1])['state']);
        $this->assertSame($ended->id, $this->cell($gradebook, $this->alice, $this->labs[1])['session_id']);
        $this->assertSame(ClassGradebook::NOT_STARTED, $this->cell($gradebook, $this->alice, $this->labs[2])['state']);
        $this->assertNull($this->cell($gradebook, $this->alice, $this->labs[2])['score']);
    }

    public function test_a_provisional_grade_shows_but_does_not_count_as_passed(): void
    {
        $this->attempt($this->alice, $this->labs[0], ['performance_score' => 95, 'ai_grade_summary' => ['needs_review' => true]]);

        $gradebook = $this->gradebook();
        $cell = $this->cell($gradebook, $this->alice, $this->labs[0]);
        $row = $gradebook->rows[0];

        $this->assertSame(ClassGradebook::REVIEW, $cell['state']);
        $this->assertEquals(95, $cell['score']);
        $this->assertSame(0, $row['passed']);
        $this->assertFalse($row['meets']);
    }

    public function test_summary_columns_follow_the_certificate_rules(): void
    {
        // Alice: 2 of 3 labs passed at 70 = 67%, over the 60% threshold
        $this->attempt($this->alice, $this->labs[0], ['performance_score' => 70]);
        $this->attempt($this->alice, $this->labs[1], ['performance_score' => 100]);
        // Bob: 1 of 3 passed = 33%; the 69.9 is below the lab pass score
        $this->attempt($this->bob, $this->labs[0], ['performance_score' => 69.9]);
        $this->attempt($this->bob, $this->labs[2], ['performance_score' => 30, 'instructor_grade_override' => 75, 'overridden_by' => $this->instructor->id]);

        [$alice, $bob] = $this->gradebook()->rows;

        $this->assertSame('Alice Able', $alice['student']->name);
        $this->assertEquals(56.67, $alice['average']);
        $this->assertSame(2, $alice['passed']);
        $this->assertEquals(67, $alice['percent']);
        $this->assertTrue($alice['meets']);

        $this->assertEquals(48.3, $bob['average']);
        $this->assertSame(1, $bob['passed']);
        $this->assertFalse($bob['meets']);
    }

    public function test_team_members_get_their_own_attempt_which_carries_the_team_grade(): void
    {
        $carol = $this->user('Carol Cole');
        $this->class->students()->attach($carol->id, ['status' => 'enrolled']);
        $team = Group::create(['name' => 'Team Rocket', 'lab_id' => $this->labs[0]->id, 'leader_id' => $this->alice->id]);
        $team->members()->attach([$this->alice->id, $this->bob->id, $carol->id]);
        $startedAt = now()->subHour()->startOfSecond();
        // The team's submit gave Alice and Bob the same grade; Carol never opened the lab
        foreach ([$this->alice, $this->bob] as $member) {
            $this->attempt($member, $this->labs[0], ['performance_score' => 88, 'group_id' => $team->id, 'team_started_at' => $startedAt]);
        }

        $gradebook = $this->gradebook();

        foreach ([$this->alice, $this->bob] as $member) {
            $cell = $this->cell($gradebook, $member, $this->labs[0]);
            $this->assertEquals(88, $cell['score']);
            $this->assertSame('Team Rocket', $cell['team']);
        }
        $this->assertSame(ClassGradebook::NOT_STARTED, $this->cell($gradebook, $carol, $this->labs[0])['state']);
    }

    public function test_only_enrolled_students_are_listed_in_name_order_and_labs_in_syllabus_order(): void
    {
        $invited = $this->user('Aaron Invited');
        $this->class->students()->attach($invited->id, ['status' => 'invited']);

        $gradebook = $this->gradebook();

        $this->assertSame(['Alice Able', 'Bob Baker'], collect($gradebook->rows)->map(fn ($row) => $row['student']->name)->all());
        $this->assertSame(['Loops', 'Arrays', 'Classes'], $gradebook->labs->pluck('title')->all());
    }

    public function test_the_page_shows_grades_regrades_and_links_to_each_attempt(): void
    {
        $graded = $this->attempt($this->alice, $this->labs[0], ['performance_score' => 50, 'instructor_grade_override' => 82.5, 'instructor_override_reason' => 'Partial credit', 'overridden_by' => $this->instructor->id]);
        $this->attempt($this->bob, $this->labs[1], ['status' => 'in_progress', 'ended_at' => null]);

        $this->actingAs($this->instructor)->get(route('classes.gradebook', $this->class->id))
            ->assertOk()
            ->assertSeeInOrder(['Loops', 'Arrays', 'Classes', 'Average'])
            ->assertSeeInOrder(['Alice Able', '82.5%', 'regraded'])
            ->assertSee('AI score 50%', false)
            ->assertSee('Partial credit')
            ->assertSee(route('sessions.show', $graded->id), false)
            ->assertSee('In progress')
            ->assertSee(route('classes.telemetry', ['class_id' => $this->class->id, 'lab' => $this->labs[1]->id]), false)
            ->assertSee(route('classes.gradebook.export', $this->class->id), false);
    }

    public function test_the_class_page_links_to_the_gradebook_for_staff_only(): void
    {
        $this->actingAs($this->instructor)->get(route('classes.show', $this->class->id))
            ->assertOk()
            ->assertSee(route('classes.gradebook', $this->class->id), false);

        $this->actingAs($this->alice)->get(route('classes.show', $this->class->id))
            ->assertOk()
            ->assertDontSee(route('classes.gradebook', $this->class->id), false);
    }

    public function test_only_the_class_instructor_and_admins_can_see_or_export_it(): void
    {
        $otherInstructor = $this->user('Other Prof', 'instructor');
        $admin = $this->user('Ada Admin', 'admin');

        $urls = [route('classes.gradebook', $this->class->id), route('classes.gradebook.export', $this->class->id)];
        foreach ($urls as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }

        foreach ($urls as $url) {
            $this->actingAs($this->alice)->get($url)->assertForbidden();
            $this->actingAs($otherInstructor)->get($url)->assertForbidden();
            $this->actingAs($admin)->get($url)->assertOk();
            $this->actingAs($this->instructor)->get($url)->assertOk();
        }

        $this->actingAs($this->instructor)->get(route('classes.gradebook', 999999))->assertNotFound();
    }

    public function test_the_excel_export_holds_the_gradebook_and_its_regrades(): void
    {
        $this->attempt($this->alice, $this->labs[0], ['performance_score' => 50, 'instructor_grade_override' => 85, 'instructor_override_reason' => 'Partial credit', 'overridden_by' => $this->instructor->id, 'instructor_overridden_at' => now()]);
        $this->attempt($this->alice, $this->labs[1], ['performance_score' => 77.25]);
        $this->attempt($this->bob, $this->labs[0], ['performance_score' => 91, 'ai_grade_summary' => ['needs_review' => true]]);
        $this->attempt($this->bob, $this->labs[1], ['status' => 'abandoned']);
        $this->attempt($this->bob, $this->labs[2], ['status' => 'in_progress', 'ended_at' => null]);

        [$sheets, $path] = $this->export();

        $this->assertSame(['Gradebook', 'Regrades'], array_keys($sheets));
        $this->assertSame(
            ['Student', 'Email', 'Loops', 'Arrays', 'Classes', 'Average', 'Labs passed (at 70%)', 'Meets 60% threshold'],
            $sheets['Gradebook'][0],
        );
        $this->assertEquals(['Alice Able', 'alice.able@example.com', 85, 77.25, '', 54.08, '2/3', 'Yes'], $sheets['Gradebook'][1]);
        $this->assertEquals(['Bob Baker', 'bob.baker@example.com', 91, 'Timed out', 'In progress', 30.33, '0/3', 'No'], $sheets['Gradebook'][2]);
        $this->assertCount(3, $sheets['Gradebook']);
        // Grades are numbers Excel can sum, not text
        $this->assertIsNumeric($sheets['Gradebook'][1][2]);
        $this->assertIsNotString($sheets['Gradebook'][1][3]);

        $this->assertSame(['Student', 'Email', 'Lab', 'Grade', 'AI score', 'Regraded by', 'Regraded at', 'Reason'], $sheets['Regrades'][0]);
        $this->assertCount(2, $sheets['Regrades']);
        $this->assertEquals(['Alice Able', 'alice.able@example.com', 'Loops', 85, 50, 'Prof'], array_slice($sheets['Regrades'][1], 0, 6));
        $this->assertSame('Partial credit', $sheets['Regrades'][1][7]);

        // The regraded and provisional cells carry a note saying so
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $comments = $zip->getFromName('xl/comments1.xml');
        $zip->close();
        $this->assertIsString($comments);
        $this->assertStringContainsString('Regraded by Prof (AI score 50%). Partial credit', $comments);
        $this->assertStringContainsString('Provisional grade from automatic checks', $comments);
    }

    public function test_text_starting_with_equals_is_never_written_as_a_formula(): void
    {
        $this->alice->update(['name' => '=HYPERLINK("http://evil.example","x")']);
        $this->labs[0]->update(['title' => '=1+1']);
        $this->attempt($this->alice, $this->labs[0], ['performance_score' => 10, 'instructor_grade_override' => 20, 'instructor_override_reason' => '=cmd|calc', 'overridden_by' => $this->instructor->id]);

        [$sheets, $path] = $this->export();

        $this->assertSame('=HYPERLINK("http://evil.example","x")', $sheets['Gradebook'][1][0]);
        $this->assertSame('=1+1', $sheets['Gradebook'][0][2]);
        $this->assertSame('=cmd|calc', $sheets['Regrades'][1][7]);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        foreach (['xl/worksheets/sheet1.xml', 'xl/worksheets/sheet2.xml'] as $sheet) {
            $this->assertStringNotContainsString('<f>', (string) $zip->getFromName($sheet));
        }
        $zip->close();
    }

    public function test_an_empty_class_exports_a_header_only_sheet(): void
    {
        $this->class->students()->detach();

        [$sheets] = $this->export();

        $this->assertCount(1, $sheets['Gradebook']);
        $this->assertCount(1, $sheets['Regrades']);
    }

    public function test_queries_stay_flat_as_the_class_grows(): void
    {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->gradebook();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $team = Group::create(['name' => 'Team', 'lab_id' => $this->labs[1]->id]);
        $this->attempt($this->alice, $this->labs[0], ['performance_score' => 90, 'group_id' => $team->id, 'instructor_grade_override' => 95, 'overridden_by' => $this->instructor->id]);
        $small = $count();

        for ($i = 0; $i < 8; $i++) {
            $student = $this->user("Student {$i}");
            $this->class->students()->attach($student->id, ['status' => 'enrolled']);
            foreach ($this->labs as $lab) {
                $this->attempt($student, $lab, ['performance_score' => 60 + $i, 'group_id' => $team->id, 'instructor_grade_override' => 70, 'overridden_by' => $this->instructor->id]);
            }
        }

        $this->assertSame($small, $count());
    }
}
