<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\SchoolClass;
use App\Models\Module;
use App\Models\Laboratory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ModuleActivityAndNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;
    protected User $instructor;
    protected SchoolClass $class;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instructor = User::create([
            'name' => 'Instructor Joe',
            'email' => 'instructor@joe.com',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $this->student = User::create([
            'name' => 'Student Sam',
            'email' => 'student@sam.com',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $this->class = SchoolClass::create([
            'name' => 'Cybersecurity Basics',
            'code' => 'CLASS-CYB101',
            'instructor_id' => $this->instructor->id,
        ]);
    }

    /**
     * Test inviting student triggers notification.
     */
    public function test_inviting_student_sends_notification(): void
    {
        $response = $this->actingAs($this->instructor)
            ->post("/classes/{$this->class->id}/invite", [
                'email' => $this->student->email,
            ]);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->student->id,
            'type' => 'App\Notifications\ClassActivityNotification',
        ]);
    }

    /**
     * Test uploading a module with attachments increments views and sends notifications.
     */
    public function test_creating_module_notifies_enrolled_students_and_upload_files(): void
    {
        Storage::fake('public');

        // Enroll student
        $this->class->students()->attach($this->student->id, ['status' => 'enrolled']);

        // Fake file
        $file = UploadedFile::fake()->create('lab_spec.pdf', 500);

        $response = $this->actingAs($this->instructor)
            ->post("/classes/{$this->class->id}/modules", [
                'title' => 'Splunk Essentials',
                'description' => 'Intro to Splunk indexing',
                'content' => '<p>Splunk is a SIEM tool.</p>',
                'order_index' => 1,
                'attachments' => [$file],
            ]);

        $response->assertRedirect();
        
        $module = Module::first();
        $this->assertNotNull($module);
        $this->assertEquals('Splunk Essentials', $module->title);
        $this->assertCount(1, $module->attachments);

        // Check student notifications
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->student->id,
            'type' => 'App\Notifications\ClassActivityNotification',
        ]);

        // Check view increments
        $this->assertEquals(0, $module->views_count);

        $this->actingAs($this->student)
            ->get("/classes/{$this->class->id}/modules/{$module->id}");

        $this->assertEquals(1, $module->fresh()->views_count);
    }

    /**
     * Test creating laboratory notifies enrolled students.
     */
    public function test_creating_laboratory_notifies_enrolled_students(): void
    {
        $this->class->students()->attach($this->student->id, ['status' => 'enrolled']);

        $module = Module::create([
            'class_id' => $this->class->id,
            'title' => 'Networking basics',
            'content' => 'Sample content',
        ]);

        $response = $this->actingAs($this->instructor)
            ->post("/laboratories", [
                'title' => 'Packet Tracer Lab',
                'description' => 'Configuring static routes',
                'time_limit' => 60,
                'module_id' => $module->id,
                'tasks' => [
                    ['task' => 'Configure Router A', 'command' => 'enable'],
                ],
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->student->id,
            'type' => 'App\Notifications\ClassActivityNotification',
        ]);
    }

    /**
     * Test page access authorization rules for creating modules.
     */
    public function test_instructor_can_access_create_module_page_and_student_cannot(): void
    {
        $response = $this->actingAs($this->instructor)
            ->get("/classes/{$this->class->id}/modules/create");
        $response->assertStatus(200);

        $response = $this->actingAs($this->student)
            ->get("/classes/{$this->class->id}/modules/create");
        $response->assertStatus(403);
    }
}
