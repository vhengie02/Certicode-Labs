<?php

namespace Tests\Feature;

use App\Models\Anomaly;
use App\Models\Certificate;
use App\Models\Group;
use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\TelemetryLog;
use App\Models\User;
use App\Notifications\CameraFlagNotification;
use App\Notifications\ClassActivityNotification;
use App\Support\DeviceLabel;
use App\Support\DisplayTime;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'student', array $attributes = []): User
    {
        static $count = 0;
        $count++;

        return User::create($attributes + [
            'name' => "Test User {$count}",
            'first_name' => 'Test',
            'last_name' => "User {$count}",
            'username' => "test-user-{$count}",
            'email' => "user{$count}@certicode.test",
            'password' => bcrypt('password123'),
            'role' => $role,
        ]);
    }

    private function labFor(SchoolClass $class): Laboratory
    {
        $module = Module::create(['class_id' => $class->id, 'title' => 'Module', 'content' => 'Content']);

        return Laboratory::create(['title' => 'Lab', 'description' => 'Lab', 'module_id' => $module->id]);
    }

    private function classFor(User $instructor): SchoolClass
    {
        return SchoolClass::create(['name' => 'Java 101', 'code' => 'JAVA'.$instructor->id, 'instructor_id' => $instructor->id]);
    }

    private function sessionRow(User $user, string $id, string $agent): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '203.0.113.7',
            'user_agent' => $agent,
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);
    }

    public function test_date_and_time_settings_are_saved_and_validated(): void
    {
        $user = $this->user();

        $this->actingAs($user)->put('/settings/date-time', ['timezone' => 'Asia/Manila', 'date_style' => 'dmy'])
            ->assertRedirect('/settings');

        $user->refresh();
        $this->assertSame('Asia/Manila', $user->timezone);
        $this->assertSame('dmy', $user->date_style);

        $this->actingAs($user)->put('/settings/date-time', ['timezone' => 'Mars/Olympus', 'date_style' => 'dmy'])
            ->assertSessionHasErrors('timezone');
        $this->actingAs($user)->put('/settings/date-time', ['timezone' => '', 'date_style' => 'nope'])
            ->assertSessionHasErrors('date_style');

        $this->actingAs($user)->put('/settings/date-time', ['timezone' => '', 'date_style' => ''])->assertRedirect();
        $this->assertNull($user->refresh()->timezone);
    }

    public function test_display_time_uses_the_saved_zone_and_style_without_changing_the_stored_moment(): void
    {
        $at = Carbon::parse('2026-11-04 16:30:00', 'UTC');

        $this->assertSame('Nov 4, 2026, 4:30 PM', DisplayTime::format($at));

        Auth::login($this->user('student', ['timezone' => 'Asia/Manila', 'date_style' => 'dmy']));

        $this->assertSame('5 Nov 2026, 00:30', DisplayTime::format($at));
        $this->assertSame('5 Nov 2026', DisplayTime::format($at, true));
        $this->assertSame('UTC', $at->timezoneName);
    }

    public function test_a_class_end_date_is_read_in_the_instructors_zone(): void
    {
        $instructor = $this->user('instructor', ['timezone' => 'Asia/Manila']);
        $class = $this->classFor($instructor);

        $this->actingAs($instructor)->put(route('classes.update', $class->id), [
            'name' => 'Java 101',
            'scheduled_end_date' => '2026-12-01T08:00',
        ])->assertRedirect();

        $this->assertSame('2026-12-01 00:00:00', $class->refresh()->scheduled_end_date->utc()->format('Y-m-d H:i:s'));
    }

    public function test_the_master_switch_can_turn_email_off(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post('/settings/notifications', ['notify_class' => '1'])->assertRedirect('/settings');

        $user->refresh();
        $this->assertFalse($user->notify_email_channel);
        $this->assertFalse($user->notify_grade);
        $this->assertSame(['database'], (new ClassActivityNotification('T', 'M', '/x', 'class'))->via($user));
        $this->assertSame(['database'], (new ClassActivityNotification('T', 'M', '/x'))->via($user));
    }

    public function test_grade_emails_follow_their_own_toggle(): void
    {
        $user = $this->user('student', ['notify_lab' => false]);
        $user->forceFill(['notify_grade' => true])->save();

        $this->assertSame(['database', 'mail'], (new ClassActivityNotification('T', 'M', '/x', 'grade'))->via($user));
        $this->assertSame(['database'], (new ClassActivityNotification('T', 'M', '/x', 'lab'))->via($user));

        $user->update(['notify_grade' => false]);
        $this->assertSame(['database'], (new ClassActivityNotification('T', 'M', '/x', 'grade'))->via($user));
    }

    public function test_saving_notifications_keeps_toggles_the_role_never_sees(): void
    {
        $student = $this->user();
        $student->forceFill(['notify_integrity' => true])->save();

        $this->actingAs($student)->post('/settings/notifications', ['notify_email_channel' => '1', 'notify_integrity' => '0']);
        $this->assertTrue($student->refresh()->notify_integrity);

        $instructor = $this->user('instructor');
        $this->actingAs($instructor)->post('/settings/notifications', ['notify_email_channel' => '1', 'notify_integrity' => '1']);
        $instructor->refresh();
        $this->assertTrue($instructor->notify_integrity);
        $this->assertTrue($instructor->notify_grade);
    }

    public function test_camera_flag_email_is_opt_in(): void
    {
        $instructor = $this->user('instructor');
        $notification = new CameraFlagNotification('Camera flag', 'Message', '/monitoring');

        $this->assertSame(['database'], $notification->via($instructor->refresh()));

        $instructor->update(['notify_integrity' => true]);
        $this->assertSame(['database', 'mail'], $notification->via($instructor->refresh()));

        $instructor->update(['notify_email_channel' => false]);
        $this->assertSame(['database'], $notification->via($instructor->refresh()));
    }

    public function test_settings_shows_each_role_its_own_toggles(): void
    {
        $this->actingAs($this->user())->get('/settings')
            ->assertOk()
            ->assertSee('notify_grade')
            ->assertDontSee('notify_integrity');

        $this->actingAs($this->user('instructor'))->get('/settings')
            ->assertOk()
            ->assertSee('notify_integrity')
            ->assertDontSee('notify_grade');
    }

    public function test_device_labels(): void
    {
        $chrome = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';
        $edge = $chrome.' Edg/130.0';
        $safari = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1';
        $android = 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/130.0 Mobile Safari/537.36';

        $this->assertSame('Chrome on Windows', DeviceLabel::from($chrome));
        $this->assertSame('Edge on Windows', DeviceLabel::from($edge));
        $this->assertSame('Safari on iPhone', DeviceLabel::from($safari));
        $this->assertSame('Chrome on Android', DeviceLabel::from($android));
        $this->assertSame('Unknown device', DeviceLabel::from(null));
    }

    public function test_sessions_are_listed_and_others_signed_out(): void
    {
        config(['session.driver' => 'database']);
        $user = $this->user();
        $other = $this->user();
        $this->sessionRow($user, 'phone-session', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Safari/604.1');
        $this->sessionRow($other, 'someone-else', 'Chrome/130.0');
        $user->forceFill(['remember_token' => 'old-token'])->save();

        $this->actingAs($user)->get('/settings')
            ->assertOk()
            ->assertSee('Safari on iPhone')
            ->assertDontSee('phone-session');

        $this->actingAs($user)->post('/settings/sessions/sign-out-others')->assertRedirect('/settings');

        $this->assertDatabaseMissing('sessions', ['id' => 'phone-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'someone-else']);
        $this->assertNotSame('old-token', $user->refresh()->remember_token);
    }

    public function test_active_sessions_are_hidden_without_database_sessions(): void
    {
        $this->actingAs($this->user())->get('/settings')
            ->assertOk()
            ->assertDontSee('Active sessions');
    }

    public function test_export_holds_the_users_data_but_no_secrets_or_images(): void
    {
        $user = $this->user('student', ['gmail_verification_code' => 'secret-code']);
        $user->forceFill(['remember_token' => 'secret-token'])->save();
        $lab = $this->labFor($this->classFor($this->user('instructor')));
        $session = LabSession::create(['lab_id' => $lab->id, 'user_id' => $user->id, 'status' => 'completed', 'submitted_code' => 'class Mine {}']);
        $session->forceFill(['extension_token' => 'secret-extension'])->save();
        TelemetryLog::create(['lab_session_id' => $session->id, 'event_type' => 'code_paste', 'payload' => ['text' => 'pasted-here']]);
        Anomaly::create([
            'lab_session_id' => $session->id,
            'type' => 'no_face',
            'severity' => 'high',
            'description' => 'No face',
            'image_path' => Anomaly::DB_SNAPSHOT,
            'metadata' => ['image_base64' => 'huge-image-data', 'reason' => 'kept'],
        ]);

        $response = $this->actingAs($user)->get('/settings/export');
        $response->assertOk();
        $body = $response->streamedContent();
        $data = json_decode($body, true);

        $this->assertSame($user->email, $data['profile']['email']);
        $this->assertSame('class Mine {}', $data['lab_sessions'][0]['submitted_code']);
        $this->assertSame('code_paste', $data['telemetry'][0]['event_type']);
        $this->assertTrue($data['integrity_flags'][0]['has_camera_snapshot']);
        $this->assertSame('kept', $data['integrity_flags'][0]['details']['reason']);

        foreach (['secret-code', 'secret-token', 'secret-extension', 'huge-image-data', '"password"'] as $secret) {
            $this->assertStringNotContainsString($secret, $body);
        }
    }

    public function test_deleting_needs_the_right_email(): void
    {
        $user = $this->user();

        $this->actingAs($user)->delete('/settings/account', ['confirm_email' => 'wrong@certicode.test'])
            ->assertSessionHasErrors('confirm_email');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_an_account_with_nothing_others_rely_on_is_fully_deleted(): void
    {
        $user = $this->user();
        $lab = $this->labFor($this->classFor($this->user('instructor')));
        $session = LabSession::create(['lab_id' => $lab->id, 'user_id' => $user->id, 'status' => 'completed']);

        $this->actingAs($user)->delete('/settings/account', ['confirm_email' => strtoupper($user->email)])
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('lab_sessions', ['id' => $session->id]);
    }

    public function test_a_deleted_account_with_a_remember_token_stays_deleted(): void
    {
        $user = $this->user();
        $user->forceFill(['remember_token' => 'keep-me-signed-in'])->save();

        $this->actingAs($user)->delete('/settings/account', ['confirm_email' => $user->email])
            ->assertRedirect(route('login'));

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_a_certificate_holder_is_scrubbed_and_the_certificate_still_verifies(): void
    {
        $user = $this->user('student', ['gmail' => 'mine@gmail.com', 'github_username' => 'mine', 'gender' => 'other']);
        $name = $user->name;
        $email = $user->email;
        $class = $this->classFor($this->user('instructor'));
        $otherClass = $this->classFor($this->user('instructor'));
        $class->students()->attach($user->id, ['status' => 'enrolled']);
        $otherClass->students()->attach($user->id, ['status' => 'enrolled']);
        $lab = $this->labFor($class);
        $session = LabSession::create(['lab_id' => $lab->id, 'user_id' => $user->id, 'status' => 'completed', 'performance_score' => 90, 'submitted_code' => 'class Mine {}']);
        $log = TelemetryLog::create(['lab_session_id' => $session->id, 'event_type' => 'idle', 'payload' => []]);
        $flag = Anomaly::create(['lab_session_id' => $session->id, 'type' => 'no_face', 'severity' => 'high', 'description' => 'No face']);
        $certificate = Certificate::create(['user_id' => $user->id, 'class_id' => $class->id, 'verification_code' => 'KEEP-ME', 'issued_at' => now()]);

        $this->actingAs($user)->delete('/settings/account', ['confirm_email' => $email])->assertRedirect(route('login'));

        $user->refresh();
        $this->assertGuest();
        $this->assertSame($name, $user->name);
        $this->assertNotSame($email, $user->email);
        $this->assertNull($user->gmail);
        $this->assertNull($user->github_username);
        $this->assertNull($user->gender);
        $this->assertFalse($user->notify_email_channel);

        $this->assertDatabaseMissing('telemetry_logs', ['id' => $log->id]);
        $this->assertDatabaseMissing('anomalies', ['id' => $flag->id]);
        $this->assertNull($session->refresh()->submitted_code);
        $this->assertEquals(90, $session->performance_score);

        $this->assertDatabaseHas('class_student', ['class_id' => $class->id, 'student_id' => $user->id]);
        $this->assertDatabaseMissing('class_student', ['class_id' => $otherClass->id, 'student_id' => $user->id]);

        $this->assertDatabaseHas('certificates', ['id' => $certificate->id]);
        $this->get(route('certificates.verify', 'KEEP-ME'))->assertOk()->assertSee($name);

        $this->post('/login', ['email' => $email, 'password' => 'password123']);
        $this->assertGuest();
    }

    public function test_a_group_lab_survives_its_owner_deleting_their_account(): void
    {
        $user = $this->user();
        $class = $this->classFor($this->user('instructor'));
        $lab = $this->labFor($class);
        $group = Group::create(['name' => 'Pair', 'lab_id' => $lab->id]);
        $session = LabSession::create(['lab_id' => $lab->id, 'user_id' => $user->id, 'group_id' => $group->id, 'status' => 'completed', 'submitted_code' => 'class Ours {}']);
        $flag = Anomaly::create([
            'lab_session_id' => $session->id,
            'type' => 'no_face',
            'severity' => 'high',
            'description' => 'No face',
            'image_path' => Anomaly::DB_SNAPSHOT,
            'metadata' => ['image_base64' => 'face', 'reason' => 'kept'],
        ]);

        $this->actingAs($user)->delete('/settings/account', ['confirm_email' => $user->email])->assertRedirect(route('login'));

        $this->assertSame('class Ours {}', $session->refresh()->submitted_code);
        $flag->refresh();
        $this->assertNull($flag->image_path);
        $this->assertSame(['reason' => 'kept'], $flag->metadata);
    }

    public function test_an_instructor_with_classes_and_the_last_admin_cannot_delete_themselves(): void
    {
        $instructor = $this->user('instructor');
        $this->classFor($instructor);
        $this->actingAs($instructor)->delete('/settings/account', ['confirm_email' => $instructor->email])
            ->assertSessionHasErrors('confirm_email');
        $this->assertDatabaseHas('users', ['id' => $instructor->id, 'email' => $instructor->email]);

        $admin = $this->user('admin');
        $this->actingAs($admin)->delete('/settings/account', ['confirm_email' => $admin->email])
            ->assertSessionHasErrors('confirm_email');
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'email' => $admin->email]);
    }
}
