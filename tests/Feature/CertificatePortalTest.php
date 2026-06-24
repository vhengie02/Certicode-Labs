<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\SchoolClass;
use App\Models\Module;
use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Certificate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificatePortalTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;
    protected User $instructor;
    protected SchoolClass $class;
    protected Module $module;
    protected Laboratory $lab;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instructor = User::create([
            'name' => 'Instructor Bob',
            'email' => 'bob@instructor.com',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $this->student = User::create([
            'name' => 'Alice Student',
            'email' => 'alice@student.com',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $this->class = SchoolClass::create([
            'name' => 'Cloud Architecture',
            'code' => 'CLASS-CLOUD1',
            'instructor_id' => $this->instructor->id,
        ]);

        $this->class->students()->attach($this->student->id, ['status' => 'enrolled']);

        $this->module = Module::create([
            'class_id' => $this->class->id,
            'title' => 'Intro to AWS',
            'content' => 'AWS basics',
        ]);

        $this->lab = Laboratory::create([
            'title' => 'VPC Configuration',
            'description' => 'Build a network sandbox',
            'time_limit' => 30,
            'module_id' => $this->module->id,
        ]);
    }

    /**
     * Test a student cannot claim a certificate with incomplete progress.
     */
    public function test_student_cannot_claim_certificate_if_incomplete(): void
    {
        $response = $this->actingAs($this->student)
            ->post("/classes/{$this->class->id}/claim-certificate");

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertEquals(0, Certificate::count());
    }

    /**
     * Test a student can claim a certificate once they complete all labs.
     */
    public function test_student_can_claim_certificate_if_complete(): void
    {
        // Complete the lab session
        LabSession::create([
            'lab_id' => $this->lab->id,
            'user_id' => $this->student->id,
            'status' => 'completed',
            'started_at' => now(),
            'ended_at' => now(),
            'performance_score' => 100.0,
        ]);

        $response = $this->actingAs($this->student)
            ->post("/classes/{$this->class->id}/claim-certificate");

        $this->assertEquals(1, Certificate::count());
        $certificate = Certificate::first();

        $response->assertRedirect("/certificates/{$certificate->id}");
        $response->assertSessionHas('success');
    }

    /**
     * Test student can view their certificate details.
     */
    public function test_student_can_view_own_certificate(): void
    {
        $certificate = Certificate::create([
            'user_id' => $this->student->id,
            'class_id' => $this->class->id,
            'verification_code' => 'CERT-ALICE12345',
            'issued_at' => now(),
        ]);

        $response = $this->actingAs($this->student)
            ->get("/certificates/{$certificate->id}");

        $response->assertStatus(200);
        $response->assertSee('Alice Student');
        $response->assertSee('Cloud Architecture');
        $response->assertSee('CERT-ALICE12345');
    }

    /**
     * Test student cannot view other student's certificate.
     */
    public function test_student_cannot_view_others_certificate(): void
    {
        $otherStudent = User::create([
            'name' => 'Charlie Student',
            'email' => 'charlie@student.com',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $certificate = Certificate::create([
            'user_id' => $otherStudent->id,
            'class_id' => $this->class->id,
            'verification_code' => 'CERT-CHARLIE78',
            'issued_at' => now(),
        ]);

        $response = $this->actingAs($this->student)
            ->get("/certificates/{$certificate->id}");

        $response->assertStatus(403);
    }

    /**
     * Test public certificate verification successfully loads.
     */
    public function test_public_certificate_verification_success(): void
    {
        $certificate = Certificate::create([
            'user_id' => $this->student->id,
            'class_id' => $this->class->id,
            'verification_code' => 'CERT-VERIFIED',
            'issued_at' => now(),
        ]);

        $response = $this->get("/verify-certificate/CERT-VERIFIED");

        $response->assertStatus(200);
        $response->assertSee('Certicode Verification Success');
        $response->assertSee('Alice Student');
        $response->assertSee('Cloud Architecture');
    }

    /**
     * Test public certificate verification fails for invalid code.
     */
    public function test_public_certificate_verification_failure(): void
    {
        $response = $this->get("/verify-certificate/CERT-INVALIDCODE");

        $response->assertStatus(200);
        $response->assertSee('Invalid Certificate Hash');
        $response->assertSee('CERT-INVALIDCODE');
    }
}
