<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\SchoolClass;
use App\Models\Module;
use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Anomaly;
use App\Models\TelemetryLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Tests\TestCase;
use Mockery;

class InstructorTelemetryAndOAuthTest extends TestCase
{
    use RefreshDatabase;

    protected User $instructor;
    protected User $student;
    protected SchoolClass $schoolClass;
    protected Laboratory $laboratory;
    protected LabSession $labSession;
    protected Anomaly $anomaly;

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
            'name' => 'IT Security Course',
            'code' => 'CLASS-ITSEC1',
            'instructor_id' => $this->instructor->id,
        ]);

        $this->schoolClass->students()->attach($this->student->id, ['status' => 'enrolled']);

        $module = Module::create([
            'class_id' => $this->schoolClass->id,
            'title' => 'Buffer Overflow Lab',
            'content' => 'Syllabus content',
        ]);

        $this->laboratory = Laboratory::create([
            'title' => 'Memory Exploitation Challenge',
            'description' => 'Verify registers memory manipulation.',
            'time_limit' => 60,
            'module_id' => $module->id,
            'tasks_definition' => [
                ['id' => 1, 'task' => 'Overflow the buffer', 'command' => 'run.sh']
            ],
        ]);

        $this->labSession = LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $this->anomaly = Anomaly::create([
            'lab_session_id' => $this->labSession->id,
            'type' => 'excessive_tab_switch',
            'severity' => 'medium',
            'description' => 'Switched tabs repeatedly.',
            'resolved' => false,
        ]);
    }

    /**
     * Test instructor can access class telemetry dashboard.
     */
    public function test_instructor_can_access_telemetry_dashboard(): void
    {
        $response = $this->actingAs($this->instructor)
            ->get("/classes/{$this->schoolClass->id}/telemetry");

        $response->assertStatus(200);
        $response->assertSee('Telemetry & Integrity Dashboard');
        $response->assertSee('excessive_tab_switch');
        $response->assertSee($this->student->name);
    }

    /**
     * Test student is blocked from accessing class telemetry dashboard.
     */
    public function test_student_cannot_access_telemetry_dashboard(): void
    {
        $response = $this->actingAs($this->student)
            ->get("/classes/{$this->schoolClass->id}/telemetry");

        $response->assertStatus(403);
    }

    /**
     * Test instructor can view telemetry log timeline for a student session.
     */
    public function test_instructor_can_view_session_timeline_logs(): void
    {
        TelemetryLog::create([
            'lab_session_id' => $this->labSession->id,
            'event_type' => 'tab_switch',
            'payload' => ['tab_title' => 'Cheat Sheet'],
        ]);

        $response = $this->actingAs($this->instructor)
            ->get("/sessions/{$this->labSession->id}/telemetry-timeline");

        $response->assertStatus(200);
        $response->assertSee('Session Metadata');
        $response->assertSee('Cheat Sheet');
    }

    /**
     * Test instructor can resolve student anomalies.
     */
    public function test_instructor_can_resolve_anomaly(): void
    {
        $response = $this->actingAs($this->instructor)
            ->post("/anomalies/{$this->anomaly->id}/resolve");

        $response->assertStatus(302);
        $this->assertTrue($this->anomaly->fresh()->resolved);
    }

    /**
     * Test Socialite redirects to provider auth page.
     */
    public function test_oauth_redirect_route_graceful_fallback(): void
    {
        // Assert redirect route falls back to mock Google Auth if credentials are empty/not configured
        $response = $this->get('/auth/google/redirect');
        $response->assertRedirect('/auth/google');
    }

    /**
     * Test Socialite Google authentication logs in existing user.
     */
    public function test_socialite_google_authenticates_existing_user(): void
    {
        $linkedUser = User::create([
            'name' => 'OAuth Google User',
            'email' => 'oauth-user@gmail.com',
            'gmail' => 'oauth-user@gmail.com',
            'gmail_verified_at' => now(),
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        config()->set('services.google.client_id', 'client-id-xyz');
        config()->set('services.google.client_secret', 'client-secret-xyz');

        // Mock Socialite User retrieval
        $abstractUser = Mockery::mock('Laravel\Socialite\Two\User');
        $abstractUser->shouldReceive('getEmail')->andReturn('oauth-user@gmail.com');
        $abstractUser->shouldReceive('getName')->andReturn('OAuth Google User');
        $abstractUser->shouldReceive('getNickname')->andReturn(null);

        $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($linkedUser);
    }

    /**
     * Test Socialite GitHub connection links to current authenticated user.
     */
    public function test_socialite_github_links_account_to_logged_in_user(): void
    {
        config()->set('services.github.client_id', 'github-id-xyz');
        config()->set('services.github.client_secret', 'github-secret-xyz');

        // Mock Socialite GitHub user
        $abstractUser = Mockery::mock('Laravel\Socialite\Two\User');
        $abstractUser->shouldReceive('getEmail')->andReturn('student@example.com');
        $abstractUser->shouldReceive('getName')->andReturn('Student Jane');
        $abstractUser->shouldReceive('getNickname')->andReturn('studentjane');

        $provider = Mockery::mock('Laravel\Socialite\Two\GithubProvider');
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('github')->andReturn($provider);

        $response = $this->actingAs($this->student)->get('/auth/github/callback');

        $response->assertRedirect('/settings');
        $this->assertEquals('studentjane', $this->student->fresh()->github_username);
    }

    /**
     * Test Socialite registration creates new account with selected role.
     */
    public function test_socialite_registration_handles_role_selection(): void
    {
        config()->set('services.google.client_id', 'google-id-xyz');
        config()->set('services.google.client_secret', 'google-secret-xyz');

        $abstractUser = Mockery::mock('Laravel\Socialite\Two\User');
        $abstractUser->shouldReceive('getEmail')->andReturn('new-oauth@gmail.com');
        $abstractUser->shouldReceive('getName')->andReturn('New Google User');
        $abstractUser->shouldReceive('getNickname')->andReturn(null);

        $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        // First callback redirects to role selection verification screen
        $response = $this->get('/auth/google/callback');
        $response->assertRedirect('/auth/google/verify?needs_role=1');
        $this->assertEquals('new-oauth@gmail.com', session('google_auth_gmail'));
        $this->assertEquals('OAUTH_VERIFIED', session('google_auth_code'));

        // Completing verification form registration with role
        $response2 = $this->withSession([
            'google_auth_gmail' => 'new-oauth@gmail.com',
            'google_auth_code' => 'OAUTH_VERIFIED',
            'google_auth_name' => 'New Google User',
        ])->post('/auth/google/callback', [
            'code' => 'OAUTH_VERIFIED',
            'role' => 'student',
        ]);

        $response2->assertRedirect('/dashboard');
        $this->assertDatabaseHas('users', [
            'email' => 'new-oauth@gmail.com',
            'gmail' => 'new-oauth@gmail.com',
            'role' => 'student',
        ]);
    }

    /**
     * Test updating profile from settings page.
     */
    public function test_user_can_update_profile_from_settings(): void
    {
        $response = $this->actingAs($this->student)
            ->put('/settings/profile', [
                'first_name' => 'Updated',
                'last_name' => 'Jane',
                'username' => 'updatedjane',
                'gender' => 'female',
                'email' => 'updated-jane@example.com',
            ]);

        $response->assertRedirect('/settings');
        $this->assertDatabaseHas('users', [
            'id' => $this->student->id,
            'first_name' => 'Updated',
            'last_name' => 'Jane',
            'name' => 'Updated Jane',
            'username' => 'updatedjane',
            'gender' => 'female',
            'email' => 'updated-jane@example.com',
        ]);
    }

    /**
     * Test StudentProfileController update redirects back to settings for own profile.
     */
    public function test_student_profile_controller_redirects_own_profile_to_settings(): void
    {
        $response = $this->actingAs($this->student)
            ->put("/profiles/{$this->student->id}", [
                'first_name' => 'Jane',
                'last_name' => 'Redirect',
                'username' => 'janeredirect',
                'gender' => 'female',
                'email' => 'jane-redirect@example.com',
                'github_username' => 'janeredirect',
                'role' => 'student',
            ]);

        $response->assertRedirect('/settings');
    }

    /**
     * Test redirecting to mock GitHub OAuth when services keys are empty.
     */
    public function test_github_oauth_redirects_to_mock_github(): void
    {
        $response = $this->get('/auth/github/redirect');
        $response->assertRedirect('/auth/github');
    }

    /**
     * Test linking account using mock GitHub callback.
     */
    public function test_mock_github_callback_links_logged_in_user(): void
    {
        $response = $this->actingAs($this->student)
            ->post('/auth/github/callback', [
                'github_username' => 'mockoctocat',
            ]);

        $response->assertRedirect('/settings');
        $this->assertDatabaseHas('users', [
            'id' => $this->student->id,
            'github_username' => 'mockoctocat',
        ]);
    }

    /**
     * Test guest login/registration using mock GitHub callback.
     */
    public function test_mock_github_callback_registers_guest_user(): void
    {
        $response = $this->post('/auth/github/callback', [
            'github_username' => 'newgithubber',
            'github_email' => 'newgithubber@example.com',
            'github_name' => 'New GitHubber',
        ]);

        // Should store user details in session and redirect to role verification
        $response->assertRedirect('/auth/google/verify?needs_role=1');
        $this->assertEquals('newgithubber@example.com', session('google_auth_gmail'));
        $this->assertEquals('newgithubber', session('github_auth_username'));
    }

    /**
     * Test updating user password.
     */
    public function test_user_can_update_password_with_correct_current_password(): void
    {
        $response = $this->actingAs($this->student)
            ->put('/settings/password', [
                'current_password' => 'password',
                'password' => 'NewSecurePassword123!',
                'password_confirmation' => 'NewSecurePassword123!',
            ]);

        $response->assertRedirect('/settings');
        $response->assertSessionHasNoErrors();
        
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('NewSecurePassword123!', $this->student->fresh()->password));
    }

    /**
     * Test updating user password with incorrect current password.
     */
    public function test_user_cannot_update_password_with_incorrect_current_password(): void
    {
        $response = $this->actingAs($this->student)
            ->put('/settings/password', [
                'current_password' => 'wrongpassword',
                'password' => 'NewSecurePassword123!',
                'password_confirmation' => 'NewSecurePassword123!',
            ]);

        $response->assertSessionHasErrors(['current_password']);
        $this->assertFalse(\Illuminate\Support\Facades\Hash::check('NewSecurePassword123!', $this->student->fresh()->password));
    }
}
