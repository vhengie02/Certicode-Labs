<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Regression tests for brute-forcing the Settings Gmail-link code: a verified Gmail makes
 * "Continue with Google" for that address sign in to this account, so the code gets a
 * fixed number of tries, expires, and is never stored or kept in the session in plain text.
 */
class GmailLinkCodeTest extends TestCase
{
    use RefreshDatabase;

    private User $attacker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->attacker = User::create(['name' => 'Attacker', 'email' => 'attacker@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
    }

    private function requestCode(string $gmail = 'victim@gmail.com'): string
    {
        $code = null;
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$code) {
            if (preg_match('/^Gmail connection code .*: (\d{6})$/', $event->message, $m)) {
                $code = $m[1];
            }
        });

        $this->actingAs($this->attacker)->post('/settings/gmail/connect', ['gmail' => $gmail])->assertRedirect('/settings');
        $this->assertNotNull($code);

        return $code;
    }

    private function wrongCode(string $code): string
    {
        return $code === '123456' ? '654321' : '123456';
    }

    public function test_the_code_is_not_stored_or_kept_in_the_session_in_plain_text(): void
    {
        $code = $this->requestCode();

        $this->assertStringNotContainsString($code, (string) $this->attacker->fresh()->gmail_verification_code);
        $this->assertNull(session('gmail_code_debug'));
    }

    public function test_the_code_is_wiped_after_five_wrong_guesses(): void
    {
        $code = $this->requestCode();

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($this->attacker)->post('/settings/gmail/verify', ['code' => $this->wrongCode($code)])
                ->assertSessionHasErrors('code');
        }

        $this->assertNull($this->attacker->fresh()->gmail_verification_code);

        // Even the right code no longer works
        $this->actingAs($this->attacker)->post('/settings/gmail/verify', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertNull($this->attacker->fresh()->gmail_verified_at);
    }

    public function test_the_right_code_still_works_after_a_few_wrong_guesses(): void
    {
        $code = $this->requestCode();

        for ($i = 0; $i < 4; $i++) {
            $this->actingAs($this->attacker)->post('/settings/gmail/verify', ['code' => $this->wrongCode($code)]);
        }

        $this->actingAs($this->attacker)->post('/settings/gmail/verify', ['code' => $code])->assertSessionHas('success');
        $this->assertNotNull($this->attacker->fresh()->gmail_verified_at);
    }

    public function test_the_code_expires_after_ten_minutes(): void
    {
        $code = $this->requestCode();

        $this->travel(11)->minutes();

        $this->actingAs($this->attacker)->post('/settings/gmail/verify', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertNull($this->attacker->fresh()->gmail_verified_at);
    }

    public function test_a_new_code_gets_a_fresh_set_of_attempts(): void
    {
        $first = $this->requestCode();
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($this->attacker)->post('/settings/gmail/verify', ['code' => $this->wrongCode($first)]);
        }

        $second = $this->requestCode();
        $this->actingAs($this->attacker)->post('/settings/gmail/verify', ['code' => $second])->assertSessionHas('success');
        $this->assertNotNull($this->attacker->fresh()->gmail_verified_at);
    }
}
