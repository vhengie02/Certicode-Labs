<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ClassActivityNotification;
use App\Support\InternalUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_links_keep_only_the_path()
    {
        $this->assertSame('/classes/4', InternalUrl::path('https://certicode-labs-2htj.vercel.app/classes/4'));
        $this->assertSame('/classes/4/modules/2?tab=labs#top', InternalUrl::path('http://localhost/classes/4/modules/2?tab=labs#top'));
        $this->assertSame('/admin/instructor-requests', InternalUrl::path('/admin/instructor-requests'));
        $this->assertSame('/', InternalUrl::path('http://127.0.0.1:8000'));
        $this->assertSame('#', InternalUrl::path(null));
        $this->assertSame('#', InternalUrl::path(''));
        $this->assertSame('#', InternalUrl::path('javascript:alert(1)'));
        $this->assertSame('#', InternalUrl::path('//evil.example.com/x'));
    }

    public function test_new_notifications_store_a_path()
    {
        $user = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('x'), 'role' => 'instructor']);

        $user->notify(new ClassActivityNotification('Student joined', 'Alex joined', 'https://certicode-labs-2htj.vercel.app/classes/4', 'info'));

        $this->assertSame('/classes/4', $user->notifications()->first()->data['url']);
    }

    public function test_old_notifications_with_full_urls_open_on_the_current_site()
    {
        $user = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('x'), 'role' => 'instructor']);
        // Stored before the fix, with another host baked in
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => ClassActivityNotification::class,
            'data' => ['title' => 'Student Joined Class', 'message' => 'Alex joined', 'url' => 'https://certicode-labs-2htj.vercel.app/classes/4', 'type' => 'class'],
        ]);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('href="/classes/4"', false)
            ->assertDontSee('certicode-labs-2htj.vercel.app/classes/4');

        $this->actingAs($user)->getJson('/notifications/fetch')
            ->assertOk()
            ->assertJsonPath('notifications.0.url', '/classes/4');
    }
}
