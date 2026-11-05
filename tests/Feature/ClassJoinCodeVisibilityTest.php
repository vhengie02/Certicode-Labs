<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A join code appears only on the class page, for staff, masked until the button is held.
 * No other page, search result or export shows it.
 */
class ClassJoinCodeVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // These pages cache by user id in the file store, which real local runs share
        config(['cache.stores.file' => ['driver' => 'array']]);
    }

    private function classWithInstructor(): SchoolClass
    {
        $instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);

        return SchoolClass::create(['name' => 'Intro Course', 'code' => 'JOINME42', 'instructor_id' => $instructor->id]);
    }

    private function student(SchoolClass $class, string $status = 'enrolled'): User
    {
        $student = User::create(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $class->students()->attach([$student->id => ['status' => $status]]);

        return $student;
    }

    public function test_staff_get_the_code_masked_with_a_hold_button(): void
    {
        $class = $this->classWithInstructor();

        $this->actingAs($class->instructor)->get(route('classes.show', $class->id))
            ->assertOk()
            ->assertSee('aria-label="Press and hold to unhide join code"', false)
            ->assertDontSee('Hold to unhide')
            ->assertSee('••••••')
            ->assertSee('<code class="hidden font-mono', false)
            ->assertSee('data-copy="JOINME42"', false);
    }

    public function test_students_never_get_the_code_on_the_class_page(): void
    {
        $class = $this->classWithInstructor();

        $this->actingAs($this->student($class))->get(route('classes.show', $class->id))
            ->assertOk()
            ->assertDontSee('JOINME42')
            ->assertDontSee('Press and hold to unhide');
    }

    public function test_the_class_list_and_dashboard_never_show_the_code(): void
    {
        $class = $this->classWithInstructor();
        $student = $this->student($class);

        foreach ([$class->instructor, $student] as $user) {
            $this->actingAs($user)->get(route('classes.index'))->assertOk()->assertSee('Intro Course')->assertDontSee('JOINME42');
            $this->actingAs($user)->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get(route('dashboard.sections'))
                ->assertOk()->assertDontSee('JOINME42');
        }
    }

    public function test_the_invitation_page_does_not_show_the_code(): void
    {
        $class = $this->classWithInstructor();

        $this->actingAs($this->student($class, 'invited'))->get(route('classes.show', $class->id))
            ->assertOk()
            ->assertSee('Class Invitation Received')
            ->assertDontSee('JOINME42');
    }

    public function test_search_neither_shows_nor_matches_the_code(): void
    {
        $class = $this->classWithInstructor();

        $this->actingAs($class->instructor)->get('/search?q=Intro')->assertOk()->assertSee('Intro Course')->assertDontSee('JOINME42');
        $this->actingAs($class->instructor)->get('/search?q=JOINME')->assertOk()->assertDontSee('Intro Course');
    }

    public function test_the_class_export_leaves_the_code_out(): void
    {
        $class = $this->classWithInstructor();
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('password'), 'role' => 'admin']);

        $csv = $this->actingAs($admin)->get('/admin/reports/export/classes?period=all')->assertOk()->streamedContent();

        $this->assertStringContainsString('Intro Course', $csv);
        $this->assertStringNotContainsString('JOINME42', $csv);
        $this->assertStringNotContainsString('Join code', $csv);
    }
}
