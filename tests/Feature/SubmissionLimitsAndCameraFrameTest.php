<?php

namespace Tests\Feature;

use App\Models\Anomaly;
use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\TelemetryLog;
use App\Models\User;
use App\Services\LlmEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionLimitsAndCameraFrameTest extends TestCase
{
    use RefreshDatabase;

    private const ONE_PIXEL_PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private const TOO_LONG = 100001;

    private User $student;

    private Laboratory $lab;

    private LabSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::create(['name' => 'Sam Student', 'email' => 'sam@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        $class = SchoolClass::create(['name' => 'Java 101', 'code' => 'JAVA-101', 'instructor_id' => $instructor->id]);
        $class->students()->attach($this->student->id, ['status' => 'enrolled']);
        $module = Module::create(['class_id' => $class->id, 'title' => 'Module', 'content' => 'Content']);
        $this->lab = Laboratory::create(['module_id' => $module->id, 'title' => 'Sums', 'description' => 'x', 'time_limit' => 30, 'tasks_definition' => [['id' => 1, 'task' => 'Print the sum', 'command' => '']]]);
        $this->session = LabSession::create(['lab_id' => $this->lab->id, 'user_id' => $this->student->id, 'status' => 'in_progress', 'started_at' => now()]);
    }

    private function asExtension(): static
    {
        return $this->withHeaders(['X-Session-Token' => $this->session->issueExtensionToken()]);
    }

    private function url(string $action): string
    {
        return "/api/v1/sessions/{$this->session->id}/{$action}";
    }

    /** Stand in for the grader and return what it was given. */
    private function captureGraderInput(): \ArrayObject
    {
        $seen = new \ArrayObject;
        $this->app->instance(LlmEvaluationService::class, new class($seen) extends LlmEvaluationService
        {
            public function __construct(private \ArrayObject $seen) {}

            public function evaluate(LabSession $session, string $code, string $language, array $diagnostics = [], array $otherFiles = []): array
            {
                $this->seen['diagnostics'] = $diagnostics;

                return ['tasks' => [], 'correctness_score' => 50];
            }
        });

        return $seen;
    }

    public function test_oversized_submission_is_refused_and_the_attempt_stays_open(): void
    {
        $this->asExtension()->postJson($this->url('submit'), ['code' => str_repeat('x', self::TOO_LONG), 'language' => 'java'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This submission is too large to grade. Remove generated or unrelated code and try again.');

        $this->assertSame('in_progress', $this->session->fresh()->status);
    }

    public function test_oversized_code_is_refused_by_every_endpoint_that_takes_it(): void
    {
        foreach (['check-progress', 'execute'] as $action) {
            $this->asExtension()->postJson($this->url($action), ['code' => str_repeat('x', self::TOO_LONG), 'language' => 'java'])
                ->assertStatus(422);
        }
    }

    public function test_oversized_code_cannot_come_in_through_the_files_list(): void
    {
        $this->asExtension()->postJson($this->url('check-progress'), [
            'files' => [['name' => 'Main.java', 'content' => str_repeat('x', self::TOO_LONG), 'is_primary' => true]],
            'language' => 'java',
        ])->assertStatus(422);
    }

    public function test_too_many_files_are_refused(): void
    {
        $files = array_map(fn ($i) => ['name' => "F{$i}.java", 'content' => 'class F {}'], range(1, 51));

        $this->asExtension()->postJson($this->url('check-progress'), ['files' => $files, 'language' => 'java'])
            ->assertStatus(422);
    }

    public function test_a_submission_at_the_limit_is_graded(): void
    {
        $this->captureGraderInput();

        $this->asExtension()->postJson($this->url('check-progress'), ['code' => str_repeat('x', self::TOO_LONG - 1), 'language' => 'java'])
            ->assertOk();
    }

    public function test_a_flood_of_diagnostics_is_cut_down_without_failing_the_submission(): void
    {
        $seen = $this->captureGraderInput();
        $diagnostics = array_fill(0, 300, ['file' => 'Main.java', 'line' => 1, 'message' => str_repeat('m', 2000), 'source' => 'javac', 'extra' => 'dropped']);

        $this->asExtension()->postJson($this->url('check-progress'), [
            'code' => 'class Main {}',
            'files' => [['name' => 'Main.java', 'content' => 'class Main {}', 'is_primary' => true]],
            'language' => 'java',
            'diagnostics' => $diagnostics,
        ])->assertOk();

        $this->assertCount(50, $seen['diagnostics']);
        $this->assertSame(500, mb_strlen($seen['diagnostics'][0]['message']));
        $this->assertArrayNotHasKey('extra', $seen['diagnostics'][0]);
    }

    public function test_a_diagnostic_message_that_is_not_text_does_not_break_grading(): void
    {
        $seen = $this->captureGraderInput();

        $this->asExtension()->postJson($this->url('check-progress'), [
            'code' => 'class Main {}',
            'language' => 'java',
            'diagnostics' => [['file' => 'Main.java', 'message' => ['nested' => 'value']]],
        ])->assertOk();

        $this->assertSame('', $seen['diagnostics'][0]['message']);
    }

    public function test_session_camera_check_with_no_details_does_not_pass(): void
    {
        $this->asExtension()->postJson($this->url('verify-camera'), [])
            ->assertStatus(422)
            ->assertJsonPath('error', 'no_face_detected');

        $this->assertFalse(TelemetryLog::where('event_type', 'webcam_prelab_verification')->exists());
    }

    public function test_lab_camera_check_with_no_details_does_not_pass(): void
    {
        $this->actingAs($this->student)->postJson("/api/v1/labs/{$this->lab->id}/verify-camera", [])
            ->assertStatus(422)
            ->assertJsonPath('error', 'no_face_detected');
    }

    public function test_camera_check_without_a_frame_does_not_pass(): void
    {
        $this->asExtension()->postJson($this->url('verify-camera'), ['status' => 'granted', 'face_count' => 1])
            ->assertStatus(422)
            ->assertJsonPath('error', 'unreadable_camera_frame');

        $this->assertFalse(TelemetryLog::where('event_type', 'webcam_prelab_verification')->exists());
        $this->assertSame(0, Anomaly::count());
    }

    public function test_camera_check_with_bytes_that_are_not_an_image_does_not_pass(): void
    {
        $this->actingAs($this->student)->postJson("/api/v1/labs/{$this->lab->id}/verify-camera", [
            'status' => 'granted',
            'face_count' => 1,
            'image_base64' => 'data:image/jpeg;base64,'.base64_encode('not an image'),
        ])->assertStatus(422)->assertJsonPath('error', 'unreadable_camera_frame');
    }

    public function test_camera_check_with_a_real_frame_passes(): void
    {
        $this->asExtension()->postJson($this->url('verify-camera'), ['status' => 'granted', 'face_count' => 1, 'image_base64' => self::ONE_PIXEL_PNG])
            ->assertOk()
            ->assertJsonPath('verified', true);

        $this->actingAs($this->student)->postJson("/api/v1/labs/{$this->lab->id}/verify-camera", ['status' => 'granted', 'face_count' => 1, 'image_base64' => self::ONE_PIXEL_PNG])
            ->assertOk()
            ->assertJsonPath('verified', true);
    }
}
