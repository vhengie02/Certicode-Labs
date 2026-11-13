<?php

namespace Tests\Feature;

use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\LlmEvaluationService;
use Database\Seeders\EasyLabsAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The easy-labs practice account: one student, three one-line Java labs (for, if/else,
 * switch), unlimited attempts on each.
 */
class EasyLabsAccountSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // .env points at production; a seeder test must never run anywhere but the test DB
        $this->assertSame('sqlite', DB::connection()->getDriverName());
    }

    private function labs()
    {
        return Laboratory::query()
            ->whereHas('module', fn ($q) => $q->where('class_id', SchoolClass::where('code', EasyLabsAccountSeeder::CLASS_CODE)->value('id')))
            ->orderBy('id')
            ->get();
    }

    public function test_it_seeds_a_student_enrolled_in_three_unlimited_labs(): void
    {
        $this->seed(EasyLabsAccountSeeder::class);

        $student = User::where('email', EasyLabsAccountSeeder::STUDENT_EMAIL)->firstOrFail();
        $this->assertSame('student', $student->role);
        $this->assertTrue(Hash::check('password', $student->password));

        $class = SchoolClass::where('code', EasyLabsAccountSeeder::CLASS_CODE)->firstOrFail();
        $this->assertSame('enrolled', $class->students()->whereKey($student->id)->first()->pivot->status);

        $labs = $this->labs();
        $this->assertSame(
            ['For Loop: Count to 5', 'If / Else: Positive or Not', 'Switch: Name the Day'],
            $labs->pluck('title')->all()
        );

        foreach ($labs as $lab) {
            $this->assertNull($lab->attemptLimit(), "{$lab->title} allows unlimited attempts");
            $this->assertSame('open', $lab->availability_mode);
            $this->assertFalse($lab->is_group_lab);
            $this->assertCount(1, $lab->tasks_definition);
        }
    }

    public function test_the_student_can_start_a_lab_again_and_again(): void
    {
        $this->seed(EasyLabsAccountSeeder::class);
        $student = User::where('email', EasyLabsAccountSeeder::STUDENT_EMAIL)->firstOrFail();

        foreach ($this->labs() as $lab) {
            foreach (range(1, 3) as $attempt) {
                $this->actingAs($student)->withCameraCheck($lab->id)
                    ->post(route('laboratories.start', $lab->id))
                    ->assertSessionMissing('error');

                LabSession::where('lab_id', $lab->id)->update(['status' => 'completed', 'ended_at' => now()]);
            }

            $this->assertSame(3, $lab->attemptsUsedBy($student));
            $this->assertNull($lab->attemptsLeftFor($student));
        }
    }

    public function test_the_starter_code_fails_and_the_one_line_answer_passes(): void
    {
        $this->seed(EasyLabsAccountSeeder::class);
        $student = User::where('email', EasyLabsAccountSeeder::STUDENT_EMAIL)->firstOrFail();
        $grader = app(LlmEvaluationService::class);

        foreach ($this->labs() as $lab) {
            $session = LabSession::create(['lab_id' => $lab->id, 'user_id' => $student->id, 'status' => 'in_progress', 'started_at' => now()]);
            $starter = $lab->getStarterFilesList()[0]['content'];

            // The TODO comment names the answer; comments must not count as writing it
            $untouched = $grader->evaluate($session, $starter, 'java');
            $this->assertFalse($untouched['tasks'][0]['completed'], "{$lab->title}: untouched starter must not pass");

            $solved = $grader->evaluate($session, $lab->reference_solution, 'java');
            $this->assertTrue($solved['tasks'][0]['completed'], "{$lab->title}: reference solution must pass");

            // The answer is the starter with one line changed
            $changed = array_diff(explode("\n", $lab->reference_solution), explode("\n", $starter));
            $this->assertCount(1, $changed, "{$lab->title}: answer differs from starter by one line");
        }
    }

    public function test_rerunning_is_idempotent_and_keeps_a_changed_password(): void
    {
        $this->seed(EasyLabsAccountSeeder::class);
        User::where('email', EasyLabsAccountSeeder::STUDENT_EMAIL)->update(['password' => Hash::make('changed-later')]);

        $this->seed(EasyLabsAccountSeeder::class);

        $this->assertSame(1, User::where('email', EasyLabsAccountSeeder::STUDENT_EMAIL)->count());
        $this->assertSame(1, SchoolClass::where('code', EasyLabsAccountSeeder::CLASS_CODE)->count());
        $this->assertCount(3, $this->labs());
        $this->assertTrue(Hash::check('changed-later', User::where('email', EasyLabsAccountSeeder::STUDENT_EMAIL)->value('password')));
    }

    private function setPasswordEnv(?string $value): void
    {
        if ($value === null) {
            putenv('EASY_LABS_PASSWORD');
            unset($_ENV['EASY_LABS_PASSWORD'], $_SERVER['EASY_LABS_PASSWORD']);

            return;
        }

        putenv("EASY_LABS_PASSWORD={$value}");
        $_ENV['EASY_LABS_PASSWORD'] = $_SERVER['EASY_LABS_PASSWORD'] = $value;
    }

    protected function tearDown(): void
    {
        $this->setPasswordEnv(null);

        parent::tearDown();
    }

    /** The seeder as it behaves on the hosted Postgres database. */
    private function hostedSeeder(): EasyLabsAccountSeeder
    {
        return (new class extends EasyLabsAccountSeeder
        {
            protected function usesLocalDatabase(): bool
            {
                return false;
            }
        })->setContainer($this->app);
    }

    public function test_the_password_comes_from_the_environment_when_set(): void
    {
        $this->setPasswordEnv('a-long-chosen-password');

        $this->seed(EasyLabsAccountSeeder::class);

        $this->assertTrue(Hash::check('a-long-chosen-password', User::where('email', EasyLabsAccountSeeder::STUDENT_EMAIL)->value('password')));
    }

    public function test_a_hosted_database_needs_a_long_password_before_anything_is_written(): void
    {
        foreach ([null, 'short'] as $password) {
            $this->setPasswordEnv($password);

            try {
                $this->hostedSeeder()->__invoke();
                $this->fail('seeded a hosted database without a long EASY_LABS_PASSWORD');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('needs EASY_LABS_PASSWORD', $e->getMessage());
            }
        }

        $this->assertSame(0, User::count());
        $this->assertSame(0, SchoolClass::count());
    }

    public function test_a_hosted_database_never_gets_a_known_password(): void
    {
        $this->setPasswordEnv('a-long-chosen-password');

        $this->hostedSeeder()->__invoke();

        $student = User::where('email', EasyLabsAccountSeeder::STUDENT_EMAIL)->value('password');
        $instructor = User::where('email', EasyLabsAccountSeeder::INSTRUCTOR_EMAIL)->value('password');
        $this->assertTrue(Hash::check('a-long-chosen-password', $student));
        $this->assertFalse(Hash::check('password', $instructor), 'a new instructor gets a random password');
    }

    public function test_it_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        try {
            (new EasyLabsAccountSeeder)->setContainer($this->app)->__invoke();
            $this->fail('EasyLabsAccountSeeder ran in production');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('never runs in production', $e->getMessage());
        }

        $this->assertSame(0, User::count());
    }
}
