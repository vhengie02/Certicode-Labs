<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test successful student registration.
     */
    public function test_can_register_as_student(): void
    {
        $response = $this->post('/register', [
            'name' => 'Jane Student',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'student',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertDatabaseHas('users', [
            'email' => 'jane@example.com',
            'role' => 'student',
        ]);
    }

    /**
     * Test successful instructor registration.
     */
    public function test_can_register_as_instructor(): void
    {
        $response = $this->post('/register', [
            'name' => 'Prof. Instructor',
            'email' => 'instructor@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'instructor',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertDatabaseHas('users', [
            'email' => 'instructor@example.com',
            'role' => 'instructor',
        ]);
    }

    /**
     * Test registration fails with invalid role.
     */
    public function test_cannot_register_with_invalid_role(): void
    {
        $response = $this->post('/register', [
            'name' => 'Malicious User',
            'email' => 'malicious@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin', // Admins must not be self-registered
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', [
            'email' => 'malicious@example.com',
        ]);
    }
}
