<?php

namespace Tests\Feature;

use App\Models\Laboratory;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;

    protected User $otherStudent;

    protected User $instructor;

    protected SchoolClass $classA;

    protected SchoolClass $classB;

    protected Module $moduleA;

    protected Laboratory $labA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::create([
            'name' => 'Jane Student',
            'email' => 'student@example.com',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $this->otherStudent = User::create([
            'name' => 'Bob Other',
            'email' => 'bob@example.com',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $this->instructor = User::create([
            'name' => 'Dr. Instructor',
            'email' => 'instructor@example.com',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        // Class A: owned by Instructor, Student is enrolled
        $this->classA = SchoolClass::create([
            'name' => 'Introduction to Laravel',
            'code' => 'CLASS-LARA1',
            'instructor_id' => $this->instructor->id,
            'description' => 'A basic course in PHP MVC structure.',
        ]);
        $this->classA->students()->attach($this->student->id, ['status' => 'enrolled']);

        // Module A inside Class A
        $this->moduleA = Module::create([
            'class_id' => $this->classA->id,
            'title' => 'Blade Views',
            'description' => 'Rendering templates with layout files.',
            'content' => 'Sample content about layout files.',
            'order_index' => 1,
        ]);

        // Lab A inside Module A
        $this->labA = Laboratory::create([
            'title' => 'HTML Elements Challenge',
            'description' => 'Solve basic tag structures.',
            'time_limit' => 30,
            'module_id' => $this->moduleA->id,
            'tasks_definition' => [],
        ]);

        // Class B: Student is NOT enrolled
        $this->classB = SchoolClass::create([
            'name' => 'Python Basics',
            'code' => 'CLASS-PY101',
            'instructor_id' => $this->instructor->id,
            'description' => 'Python course details.',
        ]);
    }

    /**
     * Test guest cannot access search.
     */
    public function test_guest_cannot_access_search(): void
    {
        $response = $this->get('/search?q=Laravel');
        $response->assertRedirect('/login');
    }

    /**
     * Test empty query or query less than 2 chars returns empty list.
     */
    public function test_short_query_returns_empty_results(): void
    {
        $response = $this->actingAs($this->student)->get('/search?q=L');
        $response->assertStatus(200)
            ->assertJson([
                'classes' => [],
                'modules' => [],
                'laboratories' => [],
            ]);
    }

    /**
     * Test student search filters class they are enrolled in.
     */
    public function test_student_finds_enrolled_class_but_not_unenrolled_class(): void
    {
        // Query "Laravel" which matches Class A (enrolled)
        $response1 = $this->actingAs($this->student)->get('/search?q=Laravel');
        $response1->assertStatus(200);
        $this->assertCount(1, $response1->json('classes'));
        $this->assertEquals('Introduction to Laravel', $response1->json('classes.0.label'));

        // Query "Python" which matches Class B (not enrolled)
        $response2 = $this->actingAs($this->student)->get('/search?q=Python');
        $response2->assertStatus(200);
        $this->assertCount(0, $response2->json('classes'));
    }

    /**
     * Test student search finds module and laboratories they have access to.
     */
    public function test_student_finds_accessible_modules_and_laboratories(): void
    {
        $response = $this->actingAs($this->student)->get('/search?q=Blade');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('modules'));
        $this->assertEquals('Blade Views', $response->json('modules.0.label'));

        $responseLab = $this->actingAs($this->student)->get('/search?q=HTML');
        $responseLab->assertStatus(200);
        $this->assertCount(1, $responseLab->json('laboratories'));
        $this->assertEquals('HTML Elements Challenge', $responseLab->json('laboratories.0.label'));
    }

    /**
     * Test instructor finds classes they instruct.
     */
    public function test_instructor_finds_instructed_classes(): void
    {
        $response = $this->actingAs($this->instructor)->get('/search?q=Python');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('classes'));
        $this->assertEquals('Python Basics', $response->json('classes.0.label'));
    }

    /**
     * Test case-insensitive search matching lowercase queries.
     */
    public function test_case_insensitive_search(): void
    {
        $response = $this->actingAs($this->student)->get('/search?q=laravel');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('classes'));

        $responseModule = $this->actingAs($this->student)->get('/search?q=blade');
        $responseModule->assertStatus(200);
        $this->assertCount(1, $responseModule->json('modules'));

        $responseLab = $this->actingAs($this->student)->get('/search?q=html');
        $responseLab->assertStatus(200);
        $this->assertCount(1, $responseLab->json('laboratories'));
    }

    public function test_results_mark_the_matching_text(): void
    {
        $response = $this->actingAs($this->student)->get('/search?q=Laravel');

        $response->assertOk();
        $this->assertSame('Introduction to Laravel', $response->json('classes.0.label'));
        $this->assertSame('Introduction to <mark>Laravel</mark>', $response->json('classes.0.label_html'));

        $modules = $this->actingAs($this->student)->get('/search?q=Blade');
        $this->assertSame('<mark>Blade</mark> Views', $modules->json('modules.0.label_html'));

        $labs = $this->actingAs($this->student)->get('/search?q=HTML');
        $this->assertSame('<mark>HTML</mark> Elements Challenge', $labs->json('laboratories.0.label_html'));
    }

    public function test_marking_ignores_case_and_keeps_the_result_casing(): void
    {
        $this->classForInstructor('Java OOP');

        $response = $this->actingAs($this->instructor)->get('/search?q=jAVA');

        $response->assertOk();
        $this->assertSame('<mark>Java</mark> OOP', $response->json('classes.0.label_html'));
    }

    public function test_every_occurrence_is_marked(): void
    {
        $this->classForInstructor('Java and JavaScript, not java beans');

        $response = $this->actingAs($this->instructor)->get('/search?q=java');

        $this->assertSame(
            '<mark>Java</mark> and <mark>Java</mark>Script, not <mark>java</mark> beans',
            $response->json('classes.0.label_html')
        );
    }

    public function test_regex_characters_in_the_query_match_literally(): void
    {
        $this->classForInstructor('Node.js beats NodeXjs');
        $this->classForInstructor('C++ (Intro) [v2] $ ^ | \\ / *');

        $dot = $this->actingAs($this->instructor)->get('/search?q=e.j');
        $this->assertSame('Nod<mark>e.j</mark>s beats NodeXjs', $dot->json('classes.0.label_html'));

        $plus = $this->actingAs($this->instructor)->get('/search?q='.urlencode('c++ (intro)'));
        $this->assertSame('<mark>C++ (Intro)</mark> [v2] $ ^ | \\ / *', $plus->json('classes.0.label_html'));

        // No backslash in this query: Postgres ILIKE reads it as an escape character
        $anchors = $this->actingAs($this->instructor)->get('/search?q='.urlencode('[v2] $ ^ |'));
        $this->assertSame('C++ (Intro) <mark>[v2] $ ^ |</mark> \\ / *', $anchors->json('classes.0.label_html'));
    }

    public function test_html_in_a_title_stays_escaped(): void
    {
        $this->classForInstructor('<b>Java</b> <script>alert("x")</script> & \'Co\'');

        $response = $this->actingAs($this->instructor)->get('/search?q=java');

        $response->assertOk();
        $this->assertSame(
            '&lt;b&gt;<mark>Java</mark>&lt;/b&gt; &lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; &amp; &#039;Co&#039;',
            $response->json('classes.0.label_html')
        );
        // The plain label is still the raw text: the page prints it with textContent
        $this->assertSame('<b>Java</b> <script>alert("x")</script> & \'Co\'', $response->json('classes.0.label'));
    }

    public function test_html_in_the_query_is_escaped_and_cannot_split_an_entity(): void
    {
        $this->classForInstructor('Tom & Jerry amp <b>bold</b>');

        $amp = $this->actingAs($this->instructor)->get('/search?q=amp');
        $this->assertSame('Tom &amp; Jerry <mark>amp</mark> &lt;b&gt;bold&lt;/b&gt;', $amp->json('classes.0.label_html'));

        $tag = $this->actingAs($this->instructor)->get('/search?q='.urlencode('<b>'));
        $this->assertSame('Tom &amp; Jerry amp <mark>&lt;b&gt;</mark>bold&lt;/b&gt;', $tag->json('classes.0.label_html'));
    }

    public function test_result_that_matched_only_its_description_has_no_mark(): void
    {
        $response = $this->actingAs($this->student)->get('/search?q=MVC');

        $this->assertSame('Introduction to Laravel', $response->json('classes.0.label'));
        $this->assertSame('Introduction to Laravel', $response->json('classes.0.label_html'));
    }

    private function classForInstructor(string $name): SchoolClass
    {
        return SchoolClass::create([
            'name' => $name,
            'code' => 'CLASS-'.strtoupper(substr(md5($name), 0, 6)),
            'instructor_id' => $this->instructor->id,
            'description' => '',
        ]);
    }
}
