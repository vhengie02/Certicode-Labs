<?php

namespace Tests\Feature;

use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Services\SandboxExecutionService;
use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter as RateLimiterFacade;
use Tests\TestCase;

/**
 * Regression tests for unthrottled sign-in, mail and grading endpoints. Every request on
 * Vercel can land on a fresh instance with an empty array cache, so each test starts a
 * "new instance" between requests: the throttle has to hold anyway.
 */
class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Drop what a new serverless instance would not have: the array cache store and the
     * resolved rate limiter (its named limiters are registered again, as on boot).
     */
    private function startFreshInstance(): void
    {
        $this->app['cache']->forgetDriver('array');
        $this->app->forgetInstance(RateLimiter::class);
        RateLimiterFacade::clearResolvedInstances();
        (new AppServiceProvider($this->app))->boot();
    }

    public function test_password_guesses_are_throttled_per_account_across_instances(): void
    {
        User::create(['name' => 'Victim', 'email' => 'victim@example.com', 'password' => bcrypt('right-password'), 'role' => 'student']);
        User::create(['name' => 'Other', 'email' => 'other@example.com', 'password' => bcrypt('right-password'), 'role' => 'student']);

        for ($i = 0; $i < 5; $i++) {
            $this->startFreshInstance();
            $this->post('/login', ['email' => 'victim@example.com', 'password' => "guess-{$i}"])->assertStatus(302);
        }

        $this->startFreshInstance();
        $this->post('/login', ['email' => 'victim@example.com', 'password' => 'right-password'])->assertStatus(429);
        $this->assertGuest();

        // Another account from the same address is unaffected
        $this->startFreshInstance();
        $this->post('/login', ['email' => 'other@example.com', 'password' => 'right-password'])->assertRedirect('/dashboard');
    }

    public function test_google_password_guesses_are_throttled(): void
    {
        User::create(['name' => 'Victim', 'email' => 'victim@gmail.com', 'password' => bcrypt('right-password'), 'role' => 'student']);

        for ($i = 0; $i < 5; $i++) {
            $this->startFreshInstance();
            $this->withSession(['google_auth_gmail' => 'victim@gmail.com'])
                ->post('/auth/google/password', ['password' => "guess-{$i}"])
                ->assertStatus(302);
        }

        $this->startFreshInstance();
        $this->withSession(['google_auth_gmail' => 'victim@gmail.com'])
            ->post('/auth/google/password', ['password' => 'right-password'])
            ->assertStatus(429);
    }

    public function test_reset_link_mail_is_throttled_per_recipient(): void
    {
        User::create(['name' => 'Victim', 'email' => 'victim@example.com', 'password' => bcrypt('password'), 'role' => 'student']);

        for ($i = 0; $i < 5; $i++) {
            $this->startFreshInstance();
            $this->flushSession();
            $this->post('/forgot-password', ['email' => 'victim@example.com'])->assertStatus(302);
        }

        $this->startFreshInstance();
        $this->flushSession();
        $this->post('/forgot-password', ['email' => 'VICTIM@example.com'])->assertStatus(429);
    }

    public function test_google_code_mail_is_throttled_per_recipient_even_without_a_session_cookie(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->startFreshInstance();
            $this->flushSession();
            $this->post('/auth/google/email', ['gmail' => 'victim@gmail.com'])->assertStatus(302);
        }

        $this->startFreshInstance();
        $this->flushSession();
        $this->post('/auth/google/email', ['gmail' => 'victim@gmail.com'])->assertStatus(429);
    }

    public function test_settings_gmail_code_checks_are_throttled(): void
    {
        $user = User::create(['name' => 'Attacker', 'email' => 'attacker@example.com', 'password' => bcrypt('password'), 'role' => 'student']);

        for ($i = 0; $i < 10; $i++) {
            $this->startFreshInstance();
            $this->actingAs($user)->post('/settings/gmail/verify', ['code' => sprintf('%06d', $i)])->assertStatus(302);
        }

        $this->startFreshInstance();
        $this->actingAs($user)->post('/settings/gmail/verify', ['code' => '999999'])->assertStatus(429);
    }

    public function test_registration_is_throttled_per_address(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->post('/register', [])->assertStatus(302);
        }

        $this->startFreshInstance();
        $this->post('/register', [])->assertStatus(429);
    }

    public function test_grading_and_code_runs_are_throttled_per_lab_session(): void
    {
        $student = User::create(['name' => 'Student', 'email' => 'student@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        $class = SchoolClass::create(['name' => 'Java 101', 'code' => 'JAVA-101', 'instructor_id' => $instructor->id]);
        $class->students()->attach($student->id, ['status' => 'enrolled']);
        $module = Module::create(['class_id' => $class->id, 'title' => 'Module', 'content' => 'Content']);
        $lab = Laboratory::create([
            'module_id' => $module->id,
            'title' => 'Loops',
            'description' => 'Write a loop.',
            'time_limit' => 45,
            'is_group_lab' => false,
            'tasks_definition' => [['id' => 1, 'task' => 'Print a greeting']],
        ]);
        $session = LabSession::create([
            'lab_id' => $lab->id,
            'user_id' => $student->id,
            'status' => 'in_progress',
            'started_at' => now(),
            'performance_score' => 0.0,
        ]);

        $this->mock(SandboxExecutionService::class)
            ->shouldReceive('execute')
            ->andReturn(['status' => 'success', 'output' => 'hello', 'execution_time_ms' => 1]);

        $payload = ['code' => 'class Main { }', 'language' => 'java'];

        for ($i = 0; $i < 6; $i++) {
            $this->startFreshInstance();
            $this->actingAs($student)->postJson("/api/v1/sessions/{$session->id}/check-progress", $payload)->assertOk();
        }
        $this->startFreshInstance();
        $this->actingAs($student)->postJson("/api/v1/sessions/{$session->id}/check-progress", $payload)->assertStatus(429);
        $this->actingAs($student)->postJson("/api/v1/sessions/{$session->id}/submit", $payload)->assertStatus(429);

        for ($i = 0; $i < 20; $i++) {
            $this->startFreshInstance();
            $this->actingAs($student)->postJson("/api/v1/sessions/{$session->id}/execute", $payload)->assertOk();
        }
        $this->startFreshInstance();
        $this->actingAs($student)->postJson("/api/v1/sessions/{$session->id}/execute", $payload)->assertStatus(429);
    }
}
