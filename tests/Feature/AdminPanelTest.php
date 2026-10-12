<?php

namespace Tests\Feature;

use App\Models\Competency;
use App\Models\LabSession;
use App\Models\Laboratory;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\StudentCompetency;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $instructor;
    private User $student;
    private SchoolClass $class;
    private Module $module;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $make = fn ($name, $role) => User::create(['name' => $name, 'email' => strtolower(explode(' ', $name)[0]) . '@example.com', 'password' => bcrypt('password'), 'role' => $role]);
        $this->admin = $make('Ada Admin', 'admin');
        $this->instructor = $make('Ivan Instructor', 'instructor');
        $this->student = $make('Sam Student', 'student');

        $this->class = SchoolClass::create(['name' => 'Java 101', 'code' => 'JAVA-101', 'instructor_id' => $this->instructor->id]);
        $this->class->students()->attach($this->student->id, ['status' => 'enrolled']);
        $this->module = Module::create(['class_id' => $this->class->id, 'title' => 'Loops', 'content' => 'x']);
    }

    private function lab(string $title, array $competencyIds = []): Laboratory
    {
        $lab = Laboratory::create(['module_id' => $this->module->id, 'title' => $title, 'description' => 'x', 'time_limit' => 30, 'tasks_definition' => []]);
        $lab->competencies()->attach($competencyIds);

        return $lab;
    }

    public function test_only_admins_reach_the_admin_pages()
    {
        foreach (['/admin/users', '/admin/competencies', '/admin/system', '/admin/reports', '/admin/reports/export/users'] as $uri) {
            $this->actingAs($this->instructor)->get($uri)->assertForbidden();
            $this->actingAs($this->student)->get($uri)->assertForbidden();
        }
        foreach (['/admin/users' => 'Ivan Instructor', '/admin/competencies' => 'Catalogue', '/admin/system' => 'Services', '/admin/reports' => 'Weekly activity'] as $uri => $text) {
            $this->actingAs($this->admin)->get($uri)->assertOk()->assertSee($text);
        }
    }

    public function test_users_can_be_searched_and_filtered()
    {
        $this->actingAs($this->admin)->get('/admin/users?role=instructor')
            ->assertSee('ivan@example.com')->assertDontSee('sam@example.com');
        $this->actingAs($this->admin)->get('/admin/users?q=SAM')
            ->assertSee('sam@example.com')->assertDontSee('ivan@example.com');
        $this->actingAs($this->admin)->get('/students')->assertRedirect(route('admin.users.index', ['role' => 'student']));
    }

    public function test_role_changes_are_guarded()
    {
        $this->actingAs($this->admin)->patch("/admin/users/{$this->student->id}/role", ['role' => 'instructor'])->assertSessionHas('success');
        $this->assertSame('instructor', $this->student->fresh()->role);

        // Not your own role, not the last admin, not an instructor who still teaches
        $this->actingAs($this->admin)->patch("/admin/users/{$this->admin->id}/role", ['role' => 'student'])->assertSessionHas('error');
        $this->actingAs($this->admin)->patch("/admin/users/{$this->instructor->id}/role", ['role' => 'student'])->assertSessionHas('error');
        $this->assertSame('admin', $this->admin->fresh()->role);
        $this->assertSame('instructor', $this->instructor->fresh()->role);

        $other = User::create(['name' => 'Other Admin', 'email' => 'other@example.com', 'password' => 'x', 'role' => 'admin']);
        $this->actingAs($other)->patch("/admin/users/{$this->admin->id}/role", ['role' => 'student'])->assertSessionHas('success');
        $this->actingAs($this->admin->fresh())->get('/admin/users')->assertForbidden();
        $this->actingAs($other)->patch("/admin/users/{$this->admin->id}/role", ['role' => 'admin']);
        // Now "other" is one of two admins; it can't demote itself, and the last remaining admin can't be demoted
        $this->actingAs($other)->patch("/admin/users/{$other->id}/role", ['role' => 'student'])->assertSessionHas('error');
    }

    public function test_deleting_accounts_is_guarded()
    {
        $this->actingAs($this->admin)->delete("/admin/users/{$this->admin->id}")->assertSessionHas('error');
        $this->actingAs($this->admin)->delete("/admin/users/{$this->instructor->id}")->assertSessionHas('error');
        $this->assertNotNull($this->instructor->fresh());
        $this->assertNotNull($this->class->fresh(), 'the class must not cascade-delete');

        // The old profile delete route uses the same rules
        $this->actingAs($this->admin)->delete("/profiles/{$this->instructor->id}")->assertSessionHas('error');

        $this->actingAs($this->admin)->delete("/admin/users/{$this->student->id}")->assertRedirect(route('admin.users.index'));
        $this->assertNull($this->student->fresh());
    }

    public function test_admin_sees_every_class_and_can_reassign_one()
    {
        $other = User::create(['name' => 'Olga Other', 'email' => 'olga@example.com', 'password' => 'x', 'role' => 'instructor']);

        $this->actingAs($this->admin)->get('/classes')->assertSee('Java 101');
        $this->actingAs($other)->get('/classes')->assertDontSee('Java 101');

        $this->actingAs($this->admin)->put("/classes/{$this->class->id}", ['name' => 'Java 101', 'instructor_id' => $other->id])->assertRedirect();
        $this->assertSame($other->id, $this->class->fresh()->instructor_id);
        $this->assertSame(1, $other->notifications()->count());

        // Instructors can't hand classes over
        $this->actingAs($other)->put("/classes/{$this->class->id}", ['name' => 'Java 101', 'instructor_id' => $this->instructor->id]);
        $this->assertSame($other->id, $this->class->fresh()->instructor_id);
    }

    public function test_competencies_can_be_managed()
    {
        $this->actingAs($this->admin)->post('/admin/competencies', ['code' => 'java-oop-01', 'name' => 'Object-oriented Java'])->assertSessionHas('success');
        $competency = Competency::where('code', 'JAVA-OOP-01')->firstOrFail();

        $this->actingAs($this->admin)->post('/admin/competencies', ['code' => 'JAVA-OOP-01', 'name' => 'Duplicate'])->assertSessionHasErrors('code');
        $this->actingAs($this->admin)->put("/admin/competencies/{$competency->id}", ['code' => 'JAVA-OOP-01', 'name' => 'OOP in Java'])->assertSessionHas('success');
        $this->assertSame('OOP in Java', $competency->fresh()->name);

        $this->actingAs($this->admin)->delete("/admin/competencies/{$competency->id}")->assertSessionHas('success');
        $this->assertNull($competency->fresh());
    }

    public function test_labs_are_tagged_from_the_lab_form()
    {
        $competency = Competency::create(['code' => 'LOOPS-01', 'name' => 'Loops']);
        $this->actingAs($this->instructor)->get("/classes/{$this->class->id}/laboratories/create")->assertSee('LOOPS-01');

        $this->actingAs($this->instructor)->post('/laboratories', [
            'title' => 'FizzBuzz', 'description' => 'x', 'time_limit' => 30, 'module_id' => $this->module->id,
            'competencies' => [$competency->id],
        ])->assertRedirect();

        $this->assertSame([$competency->id], Laboratory::where('title', 'FizzBuzz')->firstOrFail()->competencies()->pluck('competencies.id')->all());
    }

    public function test_competency_score_averages_the_best_grade_on_each_tagged_lab()
    {
        $competency = Competency::create(['code' => 'LOOPS-01', 'name' => 'Loops']);
        $labA = $this->lab('Lab A', [$competency->id]);
        $labB = $this->lab('Lab B', [$competency->id]);
        $untaggedLab = $this->lab('Lab C');

        $session = fn ($lab, $score, $status = 'completed') => LabSession::create(['lab_id' => $lab->id, 'user_id' => $this->student->id, 'status' => $status, 'started_at' => now(), 'performance_score' => $score]);
        $session($labA, 40);
        $session($labA, 80);          // best attempt on A counts
        $session($labB, 60);
        $session($labB, 100, 'in_progress'); // unfinished work doesn't count
        $session($untaggedLab, 0);

        StudentCompetency::refreshFor($this->student, $labA);
        $this->assertEquals(70.0, StudentCompetency::where('competency_id', $competency->id)->value('score_achieved'));

        // An instructor override replaces the AI grade
        LabSession::where('lab_id', $labB->id)->where('status', 'completed')->update(['instructor_grade_override' => 90]);
        StudentCompetency::refreshFor($this->student, $labB);
        $this->assertEquals(85.0, StudentCompetency::where('competency_id', $competency->id)->value('score_achieved'));

        // Untagged labs credit nothing
        StudentCompetency::refreshFor($this->student, $untaggedLab);
        $this->assertSame(1, StudentCompetency::count());
    }

    public function test_system_settings_control_sign_ups_defaults_and_the_banner()
    {
        $this->actingAs($this->admin)->put('/admin/system', [
            'registration_open' => '0',
            'instructor_requests_open' => '0',
            'default_pass_mark' => 80,
            'default_lab_minutes' => 45,
            'announcement' => 'Maintenance on Saturday',
        ])->assertSessionHas('success');

        $this->assertFalse(SystemSetting::read('registration_open'));
        $this->assertSame(80, SystemSetting::read('default_pass_mark'));

        // Banner for signed-in users; defaults prefill new classes and labs
        $this->actingAs($this->student)->get('/dashboard')->assertSee('Maintenance on Saturday');
        $this->actingAs($this->instructor)->get('/classes/create')->assertSee('value="80"', false);
        $this->actingAs($this->instructor)->get("/classes/{$this->class->id}/laboratories/create")->assertSee('value="45"', false);

        // Sign-ups closed
        auth()->logout();
        $this->get('/register')->assertOk()->assertSee('Sign-ups are')->assertDontSee('register-form');
        $this->post('/register', ['name' => 'New Person', 'email' => 'new@example.com', 'password' => 'Str0ng!Pass', 'password_confirmation' => 'Str0ng!Pass', 'role' => 'student'])
            ->assertRedirect(route('login'));
        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);

        // Sign-ups open again, instructor requests still closed: the Instructor choice is ignored
        SystemSetting::write(['registration_open' => true]);
        $this->get('/register')->assertDontSee('value="instructor"', false);
        $this->post('/register', ['name' => 'New Person', 'email' => 'new@example.com', 'password' => 'Str0ng!Pass', 'password_confirmation' => 'Str0ng!Pass', 'role' => 'instructor'])
            ->assertRedirect('/dashboard');
        $this->assertNull(User::where('email', 'new@example.com')->value('instructor_requested_at'));
    }

    public function test_reports_render_and_export_safe_csv()
    {
        $lab = $this->lab('FizzBuzz');
        LabSession::create(['lab_id' => $lab->id, 'user_id' => $this->student->id, 'status' => 'completed', 'started_at' => now(), 'performance_score' => 70]);
        $this->student->update(['name' => '=HYPERLINK("http://evil")']);

        $this->actingAs($this->admin)->get('/admin/reports?period=7')
            ->assertOk()->assertSee('Java 101')->assertSee('70%');

        $csv = $this->actingAs($this->admin)->get('/admin/reports/export/users?period=all')->assertOk()->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv, 'formula-like cells are prefixed so spreadsheets show them as text');
        $this->assertStringContainsString('ivan@example.com', $csv);

        $this->actingAs($this->admin)->get('/admin/reports/export/sessions')->assertOk();
        $this->actingAs($this->admin)->get('/admin/reports/export/passwords')->assertNotFound();
    }
}
