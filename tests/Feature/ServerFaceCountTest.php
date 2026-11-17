<?php

namespace Tests\Feature;

use App\Models\Anomaly;
use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\TelemetryLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

/**
 * With FACE_COUNT_ENABLED, a passing camera check's picture goes to the api/face-count.js
 * function, and a face count that differs from the browser's flags the attempt. The function
 * is faked here; tests/js/face-count-server.test.js covers the function itself.
 */
class ServerFaceCountTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://faces.example.test/api/face-count';

    private User $student;

    private Laboratory $laboratory;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config(['services.face_count' => [
            'enabled' => true,
            'url' => self::URL,
            'secret' => 'test-only-secret',
            'timeout' => 5,
        ]]);

        $this->student = User::create(['name' => 'Student', 'email' => 'student@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        $class = SchoolClass::create(['name' => 'Java 101', 'code' => 'JAVA-101', 'instructor_id' => $instructor->id]);
        $class->students()->attach($this->student->id, ['status' => 'enrolled']);
        $module = Module::create(['class_id' => $class->id, 'title' => 'Module', 'content' => 'Content']);
        $this->laboratory = Laboratory::create(['module_id' => $module->id, 'title' => 'Loops', 'description' => 'Write a loop.', 'time_limit' => 45, 'is_group_lab' => false, 'tasks_definition' => []]);
    }

    private function serverSees(int $faces): void
    {
        Http::fake([self::URL => Http::response(['face_count' => $faces, 'low_light' => false])]);
    }

    /** A passing check as the lab page sends it, then the lab start that files it on the attempt. */
    private function passAndStart(): void
    {
        $this->actingAs($this->student)->postJson("/v1/labs/{$this->laboratory->id}/verify-camera", [
            'status' => 'granted',
            'face_count' => 1,
            'image_base64' => $this->cameraFrame(),
        ])->assertOk()->assertJson(['verified' => true]);

        $this->actingAs($this->student)->postJson(route('laboratories.start', $this->laboratory->id))->assertOk();
    }

    private function prelabLog(): TelemetryLog
    {
        return TelemetryLog::where('event_type', TelemetryLog::CAMERA_CHECK_EVENT)->sole();
    }

    public function test_with_the_flag_off_nothing_is_sent_and_the_check_works_as_before(): void
    {
        config(['services.face_count.enabled' => false]);
        Http::fake();

        $this->passAndStart();

        Http::assertNothingSent();
        $this->assertArrayNotHasKey('server_check', $this->prelabLog()->payload);
        $this->assertSame(0, Anomaly::count());
    }

    public function test_the_function_gets_the_picture_with_the_secret_and_an_agreeing_count_is_kept(): void
    {
        $this->serverSees(1);

        $this->passAndStart();

        Http::assertSentCount(1);
        Http::assertSent(fn (HttpRequest $request) => $request->url() === self::URL
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer test-only-secret')
            && $request->hasHeader('Content-Type', 'image/jpeg')
            && $request->body() === file_get_contents(base_path('tests/fixtures/camera/camera-frame.jpg')));

        $payload = $this->prelabLog()->payload;
        $this->assertSame('agreed', $payload['server_check']);
        $this->assertSame(1, $payload['server_face_count']);
        $this->assertSame(0, Anomaly::count());
    }

    public function test_a_server_that_sees_nobody_lets_the_student_in_but_flags_the_attempt(): void
    {
        $this->serverSees(0);

        $this->passAndStart();

        $payload = $this->prelabLog()->payload;
        $this->assertSame('disagreed', $payload['server_check']);
        $this->assertSame(0, $payload['server_face_count']);

        $flag = Anomaly::sole();
        $this->assertSame('camera_check_mismatch', $flag->type);
        $this->assertSame('medium', $flag->severity);
        $this->assertSame(Anomaly::DB_SNAPSHOT, $flag->image_path);
        $this->assertSame($this->cameraFrame(), $flag->metadata['image_base64']);
        $this->assertSame(0, $flag->metadata['server_face_count']);
        $this->assertStringContainsString('found none', $flag->description);

        // A flag, not a failed attempt: nothing counts towards the camera lockout
        $this->assertNull(session("camera_failures_lab_{$this->laboratory->id}"));
    }

    public function test_a_second_person_seen_by_the_server_is_a_high_severity_flag(): void
    {
        $this->serverSees(2);

        $this->passAndStart();

        $flag = Anomaly::sole();
        $this->assertSame('camera_check_mismatch', $flag->type);
        $this->assertSame('high', $flag->severity);
        $this->assertSame(2, $flag->metadata['server_face_count']);
        $this->assertStringContainsString('found 2', $flag->description);
    }

    public function test_a_timeout_lets_the_student_in_unverified_and_logs_it(): void
    {
        Http::fake([self::URL => Http::failedConnection('cURL error 28: Operation timed out')]);
        Log::spy();

        $this->passAndStart();

        $payload = $this->prelabLog()->payload;
        $this->assertSame('unavailable', $payload['server_check']);
        $this->assertNull($payload['server_face_count']);
        $this->assertSame(0, Anomaly::count());
        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'did not answer'))->once();
    }

    /** @return array<string, array{array<string, mixed>|string, int}> */
    public static function unusableAnswers(): array
    {
        return [
            'detector failed' => [['error' => 'detection_failed'], 500],
            'wrong secret' => [['error' => 'unauthorized'], 401],
            'count not a number' => [['face_count' => 'one'], 200],
            'negative count' => [['face_count' => -1], 200],
            'gateway page' => ['<html>gateway timeout</html>', 504],
        ];
    }

    /** @param  array<string, mixed>|string  $body */
    #[DataProvider('unusableAnswers')]
    public function test_an_error_or_a_garbled_answer_leaves_the_check_unverified(array|string $body, int $status): void
    {
        Http::fake([self::URL => Http::response($body, $status)]);

        $this->passAndStart();

        $this->assertSame('unavailable', $this->prelabLog()->payload['server_check']);
        $this->assertSame(0, Anomaly::count());
    }

    #[TestWith(['secret'])]
    #[TestWith(['url'])]
    public function test_turned_on_without_a_secret_or_url_it_sends_nothing(string $missing): void
    {
        config(["services.face_count.{$missing}" => null]);
        Http::fake();

        $this->passAndStart();

        Http::assertNothingSent();
        $this->assertSame('unavailable', $this->prelabLog()->payload['server_check']);
    }

    public function test_a_failed_browser_check_is_not_sent_to_the_server(): void
    {
        Http::fake();

        $this->actingAs($this->student)->postJson("/v1/labs/{$this->laboratory->id}/verify-camera", [
            'status' => 'granted',
            'face_count' => 0,
            'image_base64' => $this->cameraFrame(),
        ])->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_the_check_for_an_open_attempt_is_compared_and_flagged_at_once(): void
    {
        $this->serverSees(0);
        $session = LabSession::create(['lab_id' => $this->laboratory->id, 'user_id' => $this->student->id, 'status' => 'in_progress', 'started_at' => now()]);

        $this->actingAs($this->student)->postJson("/v1/sessions/{$session->id}/verify-camera", [
            'status' => 'granted',
            'face_count' => 1,
            'image_base64' => $this->cameraFrame(),
        ])->assertOk()->assertJson(['verified' => true]);

        $this->assertSame('disagreed', $this->prelabLog()->payload['server_check']);
        $flag = Anomaly::sole();
        $this->assertSame('camera_check_mismatch', $flag->type);
        $this->assertSame($session->id, $flag->lab_session_id);
    }
}
