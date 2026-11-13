<?php

namespace Tests\Feature;

use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalConsentTest extends TestCase
{
    use RefreshDatabase;

    private const CAMERA_FRAME = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private User $student;

    private Laboratory $laboratory;

    protected function setUp(): void
    {
        parent::setUp();

        config(['legal.enforce' => true]);

        $this->student = User::create(['name' => 'Student', 'email' => 'student@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        $class = SchoolClass::create(['name' => 'Java 101', 'code' => 'JAVA-101', 'instructor_id' => $instructor->id]);
        $class->students()->attach($this->student->id, ['status' => 'enrolled']);
        $module = Module::create(['class_id' => $class->id, 'title' => 'Module', 'content' => 'Content']);
        $this->laboratory = Laboratory::create(['module_id' => $module->id, 'title' => 'Loops', 'description' => 'Write a loop.', 'time_limit' => 45, 'is_group_lab' => false, 'tasks_definition' => []]);
    }

    private function cameraCheck()
    {
        return $this->actingAs($this->student)->postJson("/v1/labs/{$this->laboratory->id}/verify-camera", [
            'status' => 'granted',
            'face_count' => 1,
            'image_base64' => self::CAMERA_FRAME,
        ]);
    }

    public function test_legal_pages_are_public_and_name_the_operator(): void
    {
        foreach (['legal.privacy', 'legal.terms'] as $page) {
            $this->get(route($page))->assertOk()->assertSee(config('legal.operator_name'));
        }

        $this->get(route('legal.cookies'))->assertOk()->assertSee(config('session.cookie'));
    }

    public function test_privacy_policy_shows_the_configured_retention_periods(): void
    {
        config(['app.snapshot_retention_days' => 12, 'app.telemetry_retention_days' => 3, 'app.anomaly_retention_days' => 45]);

        $this->get(route('legal.privacy'))->assertSee('12 days')->assertSee('3 days')->assertSee('45 days');
    }

    public function test_public_pages_link_to_the_policies(): void
    {
        foreach (['/', '/login', '/register'] as $page) {
            $this->get($page)->assertSee(route('legal.privacy'))->assertSee(route('legal.terms'));
        }
    }

    public function test_sign_up_requires_agreeing_to_the_terms(): void
    {
        $this->post('/register', [
            'name' => 'Jane', 'email' => 'jane@example.com', 'password' => 'Password123', 'password_confirmation' => 'Password123', 'role' => 'student',
        ])->assertSessionHasErrors('terms');

        $this->assertDatabaseMissing('users', ['email' => 'jane@example.com']);
    }

    public function test_sign_up_records_the_accepted_version(): void
    {
        $this->post('/register', [
            'name' => 'Jane', 'email' => 'jane@example.com', 'password' => 'Password123', 'password_confirmation' => 'Password123', 'role' => 'student', 'terms' => '1',
        ])->assertRedirect('/dashboard');

        $user = User::where('email', 'jane@example.com')->first();
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertSame(config('legal.terms_version'), $user->terms_version);
    }

    public function test_existing_user_must_accept_before_using_the_app(): void
    {
        $this->actingAs($this->student)->get('/dashboard')->assertRedirect(route('legal.accept.show'));

        $this->post(route('legal.accept.store'))->assertSessionHasErrors('terms');
        $this->post(route('legal.accept.store'), ['terms' => '1'])->assertRedirect('/dashboard');

        $this->assertTrue($this->student->fresh()->hasAcceptedTerms());
        $this->get('/dashboard')->assertOk();
    }

    public function test_a_new_terms_version_asks_again(): void
    {
        $this->student->acceptTerms();
        config(['legal.terms_version' => '2099-01-01']);

        $this->actingAs($this->student)->get('/dashboard')->assertRedirect(route('legal.accept.show'));
    }

    public function test_someone_who_will_not_accept_can_still_leave_with_their_data(): void
    {
        $this->actingAs($this->student)->get(route('settings.show'))->assertOk();
        $this->get(route('settings.export'))->assertOk();
        $this->delete(route('settings.account.destroy'), ['password' => 'password', 'confirm_email' => 'student@example.com'])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('users', ['email' => 'student@example.com']);
    }

    public function test_open_pages_keep_working_when_terms_change(): void
    {
        $this->student->acceptTerms();
        config(['legal.terms_version' => '2099-01-01']);

        $this->actingAs($this->student)->getJson(route('notifications.fetch'))->assertOk();
    }

    public function test_camera_check_is_refused_without_monitoring_consent(): void
    {
        $this->cameraCheck()->assertForbidden()->assertJsonPath('error', 'monitoring_consent_required');
    }

    public function test_session_camera_check_is_refused_without_monitoring_consent(): void
    {
        $session = LabSession::create(['lab_id' => $this->laboratory->id, 'user_id' => $this->student->id, 'status' => 'in_progress', 'started_at' => now()]);

        $this->actingAs($this->student)->postJson("/v1/sessions/{$session->id}/verify-camera", [
            'status' => 'granted', 'face_count' => 1, 'image_base64' => self::CAMERA_FRAME,
        ])->assertForbidden()->assertJsonPath('error', 'monitoring_consent_required');
    }

    public function test_camera_check_runs_once_the_student_agrees(): void
    {
        $this->student->acceptTerms();

        $this->actingAs($this->student)->postJson(route('legal.monitoring.store'))->assertOk();

        $this->assertTrue($this->student->fresh()->hasMonitoringConsent());
        $this->cameraCheck()->assertOk()->assertJsonPath('verified', true);
    }

    public function test_lab_page_asks_for_consent_until_given(): void
    {
        $this->student->acceptTerms();
        $page = route('laboratories.show', $this->laboratory->id);

        $this->actingAs($this->student)->get($page)->assertSee('monitoringConsent: false', false);

        $this->student->grantMonitoringConsent();

        $this->get($page)->assertSee('monitoringConsent: true', false);
    }

    public function test_student_can_withdraw_monitoring_consent(): void
    {
        $this->student->acceptTerms();
        $this->student->grantMonitoringConsent();

        $this->actingAs($this->student)->delete(route('legal.monitoring.destroy'))->assertSessionHas('success');

        $this->assertFalse($this->student->fresh()->hasMonitoringConsent());
        $this->cameraCheck()->assertForbidden();
    }

    public function test_consent_cannot_be_withdrawn_during_a_lab(): void
    {
        $this->student->acceptTerms();
        $this->student->grantMonitoringConsent();
        LabSession::create(['lab_id' => $this->laboratory->id, 'user_id' => $this->student->id, 'status' => 'in_progress', 'started_at' => now()]);

        $this->actingAs($this->student)->delete(route('legal.monitoring.destroy'))->assertSessionHas('error');

        $this->assertTrue($this->student->fresh()->hasMonitoringConsent());
    }

    public function test_data_export_includes_consent_records(): void
    {
        $this->student->acceptTerms();
        $this->student->grantMonitoringConsent();

        $export = $this->actingAs($this->student)->get(route('settings.export'))->streamedContent();

        $this->assertStringContainsString('"monitoring_consent_version":"'.config('legal.monitoring_version').'"', $export);
    }
}
