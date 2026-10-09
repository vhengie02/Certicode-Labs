<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function asProduction(): void
    {
        $this->app['env'] = 'production';
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    /**
     * The mock GitHub callback trusted any typed-in email; in production it must not exist,
     * or anyone could sign in as any user.
     */
    public function test_mock_github_sign_in_is_unavailable_in_production()
    {
        $victim = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('secret-pass'), 'role' => 'instructor']);
        $this->asProduction();

        $this->post('/auth/github/callback', ['github_username' => 'attacker', 'github_email' => $victim->email])->assertNotFound();
        $this->assertGuest();
        $this->get('/auth/github')->assertNotFound();
    }

    public function test_github_button_explains_it_is_not_set_up_in_production()
    {
        config(['services.github.client_id' => null, 'services.github.client_secret' => null]);
        $this->asProduction();

        $this->get('/auth/github/redirect')->assertRedirect(route('login'))->assertSessionHasErrors('email');
    }

    public function test_mock_github_still_works_locally_for_development()
    {
        $this->get('/auth/github')->assertOk();
    }

    public function test_google_code_is_discarded_after_five_wrong_guesses()
    {
        $this->withSession(['google_auth_gmail' => 'someone@gmail.com', 'google_auth_code' => '123456']);

        for ($i = 0; $i < 4; $i++) {
            $this->post('/auth/google/callback', ['code' => '000000'])->assertSessionHasErrors('code');
        }
        $this->post('/auth/google/callback', ['code' => '000000'])->assertRedirect(route('auth.google'));

        $this->assertNull(session('google_auth_code'));
        // Even the right code no longer works without requesting a new one
        $this->post('/auth/google/callback', ['code' => '123456'])->assertRedirect(route('auth.google'));
        $this->assertGuest();
    }
}
