<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassAccessTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;
    protected User $instructor;
    protected SchoolClass $schoolClass;

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

        $this->schoolClass = SchoolClass::create([
            'name' => 'Fullstack Web Development',
            'code' => 'CLASS-FS101',
            'instructor_id' => $this->instructor->id,
            'description' => 'Learn Laravel and Blade UI.',
        ]);
    }

    /**
     * Test student can join a class via class code.
     */
    public function test_student_can_join_class_via_code(): void
    {
        $response = $this->actingAs($this->student)
            ->post('/classes/join', [
                'code' => 'CLASS-FS101',
            ]);

        $response->assertRedirect('/classes/' . $this->schoolClass->id);
        
        $this->assertDatabaseHas('class_student', [
            'class_id' => $this->schoolClass->id,
            'student_id' => $this->student->id,
            'status' => 'enrolled',
        ]);
    }

    /**
     * Test instructor can invite a student by email.
     */
    public function test_instructor_can_invite_student_by_email(): void
    {
        $response = $this->actingAs($this->instructor)
            ->post("/classes/{$this->schoolClass->id}/invite", [
                'email' => 'student@example.com',
            ]);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('class_student', [
            'class_id' => $this->schoolClass->id,
            'student_id' => $this->student->id,
            'status' => 'invited',
        ]);

        // Student should see the invitation on classes index
        $indexResponse = $this->actingAs($this->student)
            ->get('/classes');

        $indexResponse->assertStatus(200);
        $indexResponse->assertSee('Class Invitations from Teachers');
        $indexResponse->assertSee('Fullstack Web Development');
    }

    /**
     * Test student can accept class invitation.
     */
    public function test_student_can_accept_invitation(): void
    {
        // Setup invitation
        $this->schoolClass->students()->attach($this->student->id, ['status' => 'invited']);

        $response = $this->actingAs($this->student)
            ->post("/classes/{$this->schoolClass->id}/invite-accept");

        $response->assertRedirect('/classes/' . $this->schoolClass->id);

        $this->assertDatabaseHas('class_student', [
            'class_id' => $this->schoolClass->id,
            'student_id' => $this->student->id,
            'status' => 'enrolled',
        ]);
    }
}
