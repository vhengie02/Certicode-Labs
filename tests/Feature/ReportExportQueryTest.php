<?php

namespace Tests\Feature;

use App\Models\Anomaly;
use App\Models\Certificate;
use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * CSV exports load each row's relations in batches: the query count must not grow with
 * the number of rows, or an all-time export runs past Vercel's time limit.
 */
class ReportExportQueryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create(['name' => 'Ada Admin', 'email' => 'ada@example.com', 'password' => bcrypt('password'), 'role' => 'admin']);
        $instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        $class = SchoolClass::create(['name' => 'CS 101', 'code' => 'CS101', 'instructor_id' => $instructor->id]);
        $module = Module::create(['class_id' => $class->id, 'title' => 'Module 1', 'content' => 'Content', 'order_index' => 0]);
        $lab = Laboratory::create(['module_id' => $module->id, 'title' => 'Lab', 'description' => 'D', 'time_limit' => 0, 'tasks_definition' => []]);

        foreach (range(1, 12) as $i) {
            $student = User::create(['name' => "Student {$i}", 'email' => "s{$i}@example.com", 'password' => 'x', 'role' => 'student']);
            $session = LabSession::create(['lab_id' => $lab->id, 'user_id' => $student->id, 'status' => 'completed', 'started_at' => now(), 'ended_at' => now()]);
            Anomaly::create(['lab_session_id' => $session->id, 'type' => 'paste_anomaly', 'severity' => 'medium', 'description' => 'Paste']);
            Certificate::create(['user_id' => $student->id, 'class_id' => $class->id, 'verification_code' => "CERT-{$i}", 'issued_at' => now()]);
        }
    }

    private function queriesFor(string $type): int
    {
        $this->actingAs($this->admin);
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });

        $csv = $this->get("/admin/reports/export/{$type}?period=all")->assertOk()->streamedContent();
        $this->assertStringContainsString('Student 12', $csv);

        return $count;
    }

    public function test_sessions_export_does_not_query_per_row(): void
    {
        $this->assertLessThan(12, $this->queriesFor('sessions'));
    }

    public function test_flags_export_does_not_query_per_row(): void
    {
        $this->assertLessThan(12, $this->queriesFor('flags'));
    }

    public function test_certificates_export_does_not_query_per_row(): void
    {
        $this->assertLessThan(12, $this->queriesFor('certificates'));
    }
}
