<?php

namespace Tests\Feature;

use App\Models\Anomaly;
use App\Models\Certificate;
use App\Models\LabSession;
use App\Models\Laboratory;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Instructors may only see and change their own classes; admins may act on any class.
 */
class ClassOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $otherInstructor;
    private User $admin;
    private User $student;
    private SchoolClass $class;
    private Module $module;
    private Laboratory $lab;
    private LabSession $session;
    private Anomaly $anomaly;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $make = fn ($name, $role) => User::create(['name' => $name, 'email' => strtolower(str_replace(' ', '.', $name)) . '@example.com', 'password' => bcrypt('password'), 'role' => $role]);
        $this->owner = $make('Maria Owner', 'instructor');
        $this->otherInstructor = $make('Other Prof', 'instructor');
        $this->admin = $make('Root Admin', 'admin');
        $this->student = $make('Amara Student', 'student');

        $this->class = SchoolClass::create(['name' => 'Java 101', 'code' => 'JAVA-101', 'instructor_id' => $this->owner->id]);
        $this->class->students()->attach($this->student->id, ['status' => 'enrolled']);
        $this->module = Module::create(['class_id' => $this->class->id, 'title' => 'Loops', 'content' => 'Content']);
        $this->lab = Laboratory::create(['module_id' => $this->module->id, 'title' => 'FizzBuzz', 'description' => 'x', 'time_limit' => 30, 'tasks_definition' => [], 'availability_mode' => 'live', 'live_status' => 'not_started', 'live_duration_minutes' => 30]);
        $this->session = LabSession::create(['lab_id' => $this->lab->id, 'user_id' => $this->student->id, 'status' => 'in_progress', 'started_at' => now()]);
        $this->anomaly = Anomaly::create(['lab_session_id' => $this->session->id, 'type' => 'paste_anomaly', 'severity' => 'high', 'description' => 'x', 'resolved' => false]);
    }

    /** Requests another instructor must not be able to make against this class. */
    private function protectedRequests(): array
    {
        $c = $this->class->id;
        $m = $this->module->id;
        $l = $this->lab->id;
        $s = $this->session->id;

        return [
            ['get', "/classes/{$c}"],
            ['get', "/classes/{$c}/telemetry"],
            ['get', "/classes/{$c}/telemetry?lab={$l}"],
            ['get', "/classes/{$c}/edit"],
            ['put', "/classes/{$c}", ['name' => 'Hijacked']],
            ['post', "/classes/{$c}/invite", ['email' => 'x@example.com']],
            ['post', "/classes/{$c}/end"],
            ['get', "/classes/{$c}/modules/{$m}"],
            ['get', "/classes/{$c}/modules/{$m}/edit"],
            ['delete', "/classes/{$c}/modules/{$m}"],
            ['get', "/classes/{$c}/laboratories/create"],
            ['get', "/laboratories/{$l}"],
            ['get', "/laboratories/{$l}/edit"],
            ['delete', "/laboratories/{$l}"],
            ['get', "/laboratories/{$l}/monitoring"],
            ['getJson', "/laboratories/{$l}/monitoring/data"],
            ['getJson', "/laboratories/{$l}/monitoring/plagiarism"],
            ['post', "/laboratories/{$l}/open-live"],
            ['postJson', "/api/v1/labs/{$l}/open-live"],
            ['post', "/instructor/sessions/{$s}/end"],
            ['post', "/instructor/sessions/{$s}/reopen"],
            ['postJson', "/instructor/sessions/{$s}/override-grade", ['override_score' => 100, 'override_reason' => 'x']],
            ['get', "/sessions/{$s}/telemetry-timeline"],
            ['post', "/anomalies/{$this->anomaly->id}/resolve"],
            ['getJson', "/api/v1/sessions/{$s}"],
        ];
    }

    public function test_another_instructor_is_refused_everywhere()
    {
        foreach ($this->protectedRequests() as $request) {
            [$method, $uri] = $request;
            $response = $this->actingAs($this->otherInstructor)->{$method}($uri, $request[2] ?? []);
            $this->assertSame(403, $response->status(), "{$method} {$uri} should be refused, got {$response->status()}");
        }

        // Nothing was changed along the way
        $this->assertSame('Java 101', $this->class->fresh()->name);
        $this->assertNotNull($this->module->fresh());
        $this->assertNotNull($this->lab->fresh());
        $this->assertSame('not_started', $this->lab->fresh()->live_status);
        $this->assertSame('in_progress', $this->session->fresh()->status);
        $this->assertFalse((bool) $this->anomaly->fresh()->resolved);
    }

    public function test_owner_and_admin_can_still_monitor_the_class()
    {
        foreach ([$this->owner, $this->admin] as $user) {
            $this->actingAs($user)->get("/classes/{$this->class->id}/telemetry")->assertOk();
            $this->actingAs($user)->get("/classes/{$this->class->id}/telemetry?lab={$this->lab->id}")->assertOk();
            $this->actingAs($user)->getJson("/laboratories/{$this->lab->id}/monitoring/data")->assertOk();
            $this->actingAs($user)->get("/sessions/{$this->session->id}/telemetry-timeline")->assertOk();
        }

        $this->actingAs($this->owner)->post("/anomalies/{$this->anomaly->id}/resolve")->assertRedirect();
        $this->assertTrue((bool) $this->anomaly->fresh()->resolved);
    }

    public function test_module_ids_from_another_class_cannot_be_smuggled_through_your_own_class()
    {
        $mine = SchoolClass::create(['name' => 'Mine', 'code' => 'MINE-1', 'instructor_id' => $this->otherInstructor->id]);

        $this->actingAs($this->otherInstructor)->get("/classes/{$mine->id}/modules/{$this->module->id}/edit")->assertNotFound();
        $this->actingAs($this->otherInstructor)->put("/classes/{$mine->id}/modules/{$this->module->id}", ['title' => 'Hijacked', 'content' => 'x'])->assertNotFound();
        $this->actingAs($this->otherInstructor)->delete("/classes/{$mine->id}/modules/{$this->module->id}")->assertNotFound();

        $this->assertSame('Loops', $this->module->fresh()->title);
    }

    public function test_labs_cannot_be_created_or_moved_into_another_instructors_module()
    {
        $mine = SchoolClass::create(['name' => 'Mine', 'code' => 'MINE-2', 'instructor_id' => $this->otherInstructor->id]);
        $myModule = Module::create(['class_id' => $mine->id, 'title' => 'Mine', 'content' => 'x']);
        $myLab = Laboratory::create(['module_id' => $myModule->id, 'title' => 'My lab', 'description' => 'x', 'time_limit' => 30, 'tasks_definition' => []]);
        $payload = ['title' => 'Planted', 'description' => 'x', 'time_limit' => 30, 'module_id' => $this->module->id];

        $this->actingAs($this->otherInstructor)->post('/laboratories', $payload)->assertForbidden();
        $this->actingAs($this->otherInstructor)->put("/laboratories/{$myLab->id}", $payload)->assertForbidden();

        $this->assertSame(1, Laboratory::where('module_id', $this->module->id)->count());
        $this->assertSame($myModule->id, $myLab->fresh()->module_id);
    }

    public function test_students_must_be_enrolled_to_open_or_start_a_lab()
    {
        $outsider = User::create(['name' => 'Out Sider', 'email' => 'outsider@example.com', 'password' => bcrypt('password'), 'role' => 'student']);

        $this->actingAs($outsider)->get("/laboratories/{$this->lab->id}")->assertForbidden();
        $this->actingAs($outsider)->post("/laboratories/{$this->lab->id}/start", ['camera_verified' => true])->assertForbidden();
        $this->actingAs($outsider)->postJson("/api/v1/labs/{$this->lab->id}/start")->assertForbidden();
        $this->assertSame(0, LabSession::where('user_id', $outsider->id)->count());

        $this->actingAs($this->student)->get("/laboratories/{$this->lab->id}")->assertOk();
    }

    public function test_certificates_are_visible_to_their_student_the_class_instructor_and_admins_only()
    {
        $cert = Certificate::create(['user_id' => $this->student->id, 'class_id' => $this->class->id, 'verification_code' => 'CERT-ABCDEF123456', 'qr_code_path' => 'x.svg', 'issued_at' => now()]);

        foreach ([$this->student, $this->owner, $this->admin] as $user) {
            $this->actingAs($user)->get("/certificates/{$cert->id}")->assertOk();
        }
        $this->actingAs($this->otherInstructor)->get("/certificates/{$cert->id}")->assertForbidden();
    }

    public function test_live_channels_are_limited_to_the_class_instructor()
    {
        $this->assertTrue($this->session->isAccessibleBy($this->owner));
        $this->assertTrue($this->session->isAccessibleBy($this->admin));
        $this->assertTrue($this->session->isAccessibleBy($this->student));
        $this->assertFalse($this->session->isAccessibleBy($this->otherInstructor));

        $this->assertTrue($this->owner->canManageClass($this->class));
        $this->assertTrue($this->owner->canManageClass($this->class->id));
        $this->assertFalse($this->otherInstructor->canManageClass($this->class));
        $this->assertFalse($this->student->canManageClass($this->class));
        $this->assertFalse($this->owner->canManageClass(null));
    }
}
