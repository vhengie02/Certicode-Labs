<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ClassActivityNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email = 'student@example.com'): User
    {
        return User::create(['name' => 'Student', 'email' => $email, 'password' => bcrypt('x'), 'role' => 'student']);
    }

    private function notification(User $user, bool $read = true): DatabaseNotification
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => ClassActivityNotification::class,
            'data' => ['title' => 'Class update', 'message' => 'Something happened', 'url' => '/classes/4', 'type' => 'class'],
            'read_at' => $read ? now() : null,
        ]);
    }

    public function test_deletes_only_the_selected_read_notifications()
    {
        $user = $this->user();
        $selected = $this->notification($user);
        $kept = $this->notification($user);

        $this->actingAs($user)->postJson('/notifications/delete-read', ['ids' => [$selected->id]])
            ->assertOk()
            ->assertJsonPath('readCount', 1)
            ->assertJsonPath('notifications.0.id', $kept->id);

        $this->assertDatabaseMissing('notifications', ['id' => $selected->id]);
        $this->assertDatabaseHas('notifications', ['id' => $kept->id]);
    }

    public function test_an_unread_notification_is_never_deleted()
    {
        $user = $this->user();
        $unread = $this->notification($user, read: false);

        $this->actingAs($user)->postJson('/notifications/delete-read', ['ids' => [$unread->id]])
            ->assertOk()
            ->assertJsonPath('unreadCount', 1);

        $this->assertDatabaseHas('notifications', ['id' => $unread->id]);
    }

    public function test_select_all_deletes_every_read_notification_and_keeps_unread_ones()
    {
        $user = $this->user();
        // More than the dropdown shows, so "all" has to reach past the visible five
        for ($i = 0; $i < 7; $i++) {
            $this->notification($user);
        }
        $unread = $this->notification($user, read: false);

        $this->actingAs($user)->postJson('/notifications/delete-read', ['all' => true])
            ->assertOk()
            ->assertJsonPath('readCount', 0)
            ->assertJsonPath('unreadCount', 1);

        $this->assertSame([$unread->id], $user->notifications()->pluck('id')->all());
    }

    public function test_cannot_delete_another_users_notifications()
    {
        $other = $this->user('other@example.com');
        $theirs = $this->notification($other);

        $this->actingAs($this->user())->postJson('/notifications/delete-read', ['ids' => [$theirs->id]])->assertOk();
        $this->actingAs($this->user('third@example.com'))->postJson('/notifications/delete-read', ['all' => true])->assertOk();

        $this->assertDatabaseHas('notifications', ['id' => $theirs->id]);
    }

    public function test_an_empty_request_deletes_nothing()
    {
        $user = $this->user();
        $read = $this->notification($user);

        $this->actingAs($user)->postJson('/notifications/delete-read', [])->assertOk();

        $this->assertDatabaseHas('notifications', ['id' => $read->id]);
    }

    public function test_rejects_ids_that_are_not_uuids()
    {
        $this->actingAs($this->user())->postJson('/notifications/delete-read', ['ids' => ['1 or 1=1']])
            ->assertUnprocessable();
    }

    public function test_guests_cannot_delete()
    {
        $this->postJson('/notifications/delete-read', ['all' => true])->assertUnauthorized();
    }

    public function test_deleted_notifications_leave_the_bell_at_once()
    {
        $user = $this->user();
        $read = $this->notification($user);

        // Warm the 15-second feed cache, as the bell's polling would
        $this->actingAs($user)->getJson('/notifications/fetch')->assertJsonPath('readCount', 1);

        $this->actingAs($user)->postJson('/notifications/delete-read', ['ids' => [$read->id]])->assertOk();

        $this->actingAs($user)->getJson('/notifications/fetch')
            ->assertJsonPath('readCount', 0)
            ->assertJsonCount(0, 'notifications');
    }

    public function test_only_read_notifications_get_a_checkbox_in_the_dropdown()
    {
        $user = $this->user();
        $read = $this->notification($user);
        $unread = $this->notification($user, read: false);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('value="'.$read->id.'"', false)
            ->assertDontSee('value="'.$unread->id.'"', false);
    }
}
