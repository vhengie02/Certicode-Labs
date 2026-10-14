<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Competency;
use App\Models\LabSession;
use App\Models\Laboratory;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\StudentCompetency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateRecordTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private Certificate $certificate;

    protected function setUp(): void
    {
        parent::setUp();
        $instructor = User::create(['name' => 'Maria Santos', 'email' => 'maria@example.com', 'password' => 'x', 'role' => 'instructor']);
        $this->student = User::create(['name' => 'Amara Okafor', 'email' => 'amara@example.com', 'password' => 'x', 'role' => 'student']);
        $class = SchoolClass::create(['name' => 'Java Fundamentals', 'code' => 'JAVA-1', 'instructor_id' => $instructor->id, 'passing_threshold' => 50, 'description' => 'Variables, loops and objects.']);
        $class->students()->attach($this->student->id, ['status' => 'enrolled']);

        $loops = Module::create(['class_id' => $class->id, 'title' => 'Loops', 'content' => 'x']);
        $objects = Module::create(['class_id' => $class->id, 'title' => 'Objects', 'content' => 'x', 'order_index' => 1]);
        $competency = Competency::create(['code' => 'JAVA-LOOPS-01', 'name' => 'Loops and control flow']);

        $fizz = Laboratory::create(['module_id' => $loops->id, 'title' => 'FizzBuzz', 'description' => 'x', 'time_limit' => 30, 'tasks_definition' => []]);
        $fizz->competencies()->attach($competency->id);
        Laboratory::create(['module_id' => $objects->id, 'title' => 'Bank Account', 'description' => 'x', 'time_limit' => 30, 'tasks_definition' => []]);

        $start = now()->subHours(3);
        LabSession::create(['lab_id' => $fizz->id, 'user_id' => $this->student->id, 'status' => 'completed', 'started_at' => $start, 'ended_at' => $start->copy()->addMinutes(40), 'performance_score' => 60]);
        LabSession::create(['lab_id' => $fizz->id, 'user_id' => $this->student->id, 'status' => 'completed', 'started_at' => $start->copy()->addHour(), 'ended_at' => $start->copy()->addMinutes(95), 'performance_score' => 70, 'instructor_grade_override' => 92]);
        StudentCompetency::refreshFor($this->student, $fizz);

        $this->certificate = Certificate::create(['user_id' => $this->student->id, 'class_id' => $class->id, 'verification_code' => 'CERT-ABC123DEF456', 'issued_at' => now()]);
    }

    public function test_record_reflects_the_students_graded_work()
    {
        $record = $this->certificate->record();

        $this->assertSame(2, $record['labs_total']);
        $this->assertSame(1, $record['labs_completed']);
        $this->assertSame(50, $record['completion']);
        $this->assertEquals(92.0, $record['average_grade'], 'best attempt counts, with the instructor override');
        $this->assertSame(75, $record['minutes']);
        $this->assertSame(['Loops', 'Objects'], array_column($record['modules'], 'title'));
        $this->assertSame(2, $record['modules'][0]['labs'][0]['result']['attempts']);
        $this->assertTrue($record['modules'][0]['labs'][0]['result']['overridden']);
        $this->assertNull($record['modules'][1]['labs'][0]['result']);
        $this->assertSame('JAVA-LOOPS-01', $record['competencies'][0]['code']);
        $this->assertEquals(92.0, $record['competencies'][0]['score']);
    }

    public function test_qr_code_is_a_real_svg_that_points_at_the_verification_page()
    {
        $svg = $this->certificate->qrSvg();

        $this->assertStringStartsWith('<svg', $svg);
        // The modules are drawn as one long path; a real code for this URL has hundreds of segments
        $this->assertMatchesRegularExpression('/<path[^>]+d="[^"]{1500,}"/', $svg);
        $this->assertStringEndsWith('/verify-certificate/CERT-ABC123DEF456', $this->certificate->verificationUrl());
    }

    public function test_public_page_shows_the_proof()
    {
        $this->get('/verify-certificate/cert-abc123def456')
            ->assertOk()
            ->assertSee('This certificate is genuine')
            ->assertSee('Amara Okafor')
            ->assertSee('Java Fundamentals')
            ->assertSee('Variables, loops and objects.')
            ->assertSee('FizzBuzz')
            ->assertSee('92%')
            ->assertSee('set by instructor')
            ->assertSee('Not taken')
            ->assertSee('Loops and control flow')
            ->assertSee('noindex', false)
            ->assertSee('<svg', false)
            ->assertDontSee('amara@example.com');
    }

    public function test_owner_sees_the_certificate_with_actions()
    {
        $this->actingAs($this->student)->get("/certificates/{$this->certificate->id}")
            ->assertOk()
            ->assertSee('Download PDF')
            ->assertSee('Copy verification link')
            ->assertSee($this->certificate->verificationUrl())
            ->assertSee('Certificate of Completion');
    }
}
