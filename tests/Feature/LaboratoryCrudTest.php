<?php

namespace Tests\Feature;

use App\Models\Laboratory;
use App\Models\User;
use App\Models\SchoolClass;
use App\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaboratoryCrudTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;
    protected User $instructor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::create([
            'name' => 'Jane Student',
            'email' => 'student@example.com',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $this->instructor = User::create([
            'name' => 'Dr. Instructor',
            'email' => 'instructor@example.com',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);
    }

    /**
     * Test students can view classes list.
     */
    public function test_student_can_view_classes_index(): void
    {
        $response = $this->actingAs($this->student)
            ->get('/classes');

        $response->assertStatus(200);
        $response->assertSee('Your Classes');
    }

    /**
     * Test student is blocked from accessing the create laboratory view.
     */
    public function test_student_cannot_access_create_laboratory_page(): void
    {
        $class = SchoolClass::create([
            'name' => 'Web Development 101',
            'code' => 'CLASS-WEB101',
            'instructor_id' => $this->instructor->id,
            'description' => 'Intro to HTML/CSS/JS',
        ]);

        $response = $this->actingAs($this->student)
            ->get("/classes/{$class->id}/laboratories/create");

        $response->assertStatus(403);
    }

    /**
     * Test instructor can access the create laboratory view.
     */
    public function test_instructor_can_access_create_laboratory_page(): void
    {
        $class = SchoolClass::create([
            'name' => 'Web Development 101',
            'code' => 'CLASS-WEB101',
            'instructor_id' => $this->instructor->id,
            'description' => 'Intro to HTML/CSS/JS',
        ]);

        $response = $this->actingAs($this->instructor)
            ->get("/classes/{$class->id}/laboratories/create");

        $response->assertStatus(200);
        $response->assertSee('New Laboratory Specifications');
    }

    /**
     * Test profile edit screen access rules.
     */
    public function test_user_can_access_own_profile_edit_page(): void
    {
        $response = $this->actingAs($this->student)
            ->get("/profiles/{$this->student->id}/edit");

        $response->assertStatus(200);
        $response->assertSee('Account Specifications');
    }

    /**
     * Test user cannot access another student's profile.
     */
    public function test_student_cannot_access_other_users_profile(): void
    {
        $response = $this->actingAs($this->student)
            ->get("/profiles/{$this->instructor->id}/edit");

        $response->assertStatus(403);
    }
}
