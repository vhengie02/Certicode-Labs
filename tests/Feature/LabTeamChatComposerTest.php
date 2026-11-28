<?php

namespace Tests\Feature;

use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The team chat on the lab page: a pasted code snippet is sent with Enter or its own button,
 * and Shift+Enter makes a new line in it.
 */
class LabTeamChatComposerTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;

    protected Laboratory $laboratory;

    protected function setUp(): void
    {
        parent::setUp();

        $instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        $this->student = User::create(['name' => 'Alice', 'email' => 'alice@example.com', 'password' => bcrypt('password'), 'role' => 'student']);

        $class = SchoolClass::create(['name' => 'CS 101', 'code' => 'CS101', 'instructor_id' => $instructor->id]);
        $class->students()->attach([$this->student->id => ['status' => 'enrolled']]);
        $module = Module::create(['class_id' => $class->id, 'title' => 'Module 1', 'content' => 'Content', 'order_index' => 0]);
        $this->laboratory = Laboratory::create([
            'module_id' => $module->id,
            'title' => 'Team Lab',
            'description' => 'Description',
            'time_limit' => 45,
            'is_group_lab' => true,
            'tasks_definition' => [['id' => 1, 'task' => 'Task']],
        ]);
        LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'status' => 'in_progress',
            'started_at' => now(),
            'performance_score' => 0.0,
        ]);
    }

    private function snippetBox(): string
    {
        $html = $this->actingAs($this->student)
            ->get(route('laboratories.show', $this->laboratory->id))
            ->assertOk()
            ->getContent();

        $this->assertMatchedPattern('/<template x-if="showSnippetInput">(.*?)<\/template>/s', $html, $match);

        return $match[1];
    }

    private function assertMatchedPattern(string $pattern, string $subject, ?array &$match): void
    {
        $this->assertSame(1, preg_match($pattern, $subject, $match), "No match for {$pattern}");
    }

    public function test_enter_in_the_snippet_box_sends_and_shift_enter_does_not()
    {
        $box = $this->snippetBox();

        $this->assertMatchedPattern('/<textarea id="chat-code-snippet"[^>]*@keydown\.enter="sendOnEnter\(\$event\)"/', $box, $textarea);

        $html = $this->actingAs($this->student)->get(route('laboratories.show', $this->laboratory->id))->getContent();
        $this->assertMatchedPattern('/sendOnEnter\(e\) \{\s*if \(e\.shiftKey \|\| e\.isComposing\) return;\s*e\.preventDefault\(\);\s*this\.sendMessage\(\);/', $html, $handler);
    }

    public function test_the_snippet_box_has_its_own_send_button()
    {
        $box = $this->snippetBox();

        $this->assertMatchedPattern('/<button type="submit" id="chat-snippet-send"[^>]*aria-label="Send snippet"/', $box, $button);
    }

    public function test_a_snippet_sent_without_a_message_still_has_one()
    {
        $html = $this->actingAs($this->student)->get(route('laboratories.show', $this->laboratory->id))->getContent();

        $this->assertStringContainsString("this.newMessage.trim() || (snippet ? 'Code snippet shared:' : '')", $html);
    }
}
