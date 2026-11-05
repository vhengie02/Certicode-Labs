<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToastNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const PENDING_NOTICE = 'Your instructor access is waiting for an admin to approve it.';

    public function test_flash_message_renders_as_a_toast_in_the_app_layout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['success' => 'Your certificate has been successfully issued!'])
            ->get(route('settings.show'))
            ->assertOk()
            ->assertSee('class="toast toast-success"', false)
            ->assertSee('Your certificate has been successfully issued!')
            ->assertDontSee('app-flash-success', false);
    }

    public function test_pending_instructor_notice_pops_up_once_per_session(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'instructor_requested_at' => now(),
        ]);

        $this->actingAs($user)->get(route('settings.show'))->assertSee(self::PENDING_NOTICE);
        $this->actingAs($user)->get(route('settings.show'))->assertDontSee(self::PENDING_NOTICE);
    }

    public function test_login_errors_render_as_a_toast(): void
    {
        $this->from(route('login'))
            ->followingRedirects()
            ->post(route('login.store'), ['email' => 'not-an-email', 'password' => 'secret'])
            ->assertSee('class="toast toast-error"', false)
            ->assertSee("We couldn't sign you in.")
            ->assertSee('Enrolled in a course?')
            ->assertDontSee('cc-notice-error', false);
    }

    public function test_auth_pages_show_flashed_status_as_a_toast(): void
    {
        $this->withSession(['status' => 'Your password has been reset successfully.'])
            ->get(route('login'))
            ->assertSee('class="toast toast-success"', false)
            ->assertSee('Your password has been reset successfully.');
    }
}
