<?php

namespace Tests\Feature;

use App\Models\Anomaly;
use App\Models\Group;
use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\TelemetryLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TypingEffortAndFileFlagsTest extends TestCase
{
    use RefreshDatabase;

    private const STARTER = "public class Main {\n    // TODO\n}\n";

    private User $student;

    private Laboratory $lab;

    private LabSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::create(['name' => 'Sam Student', 'email' => 'sam@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $this->lab = Laboratory::create([
            'title' => 'Sums',
            'description' => 'x',
            'time_limit' => 30,
            'tasks_definition' => [['id' => 1, 'task' => 'Print the sum', 'command' => '']],
            'starter_files' => [['name' => 'Main.java', 'content' => self::STARTER, 'is_primary' => true]],
        ]);
        $this->session = LabSession::create(['lab_id' => $this->lab->id, 'user_id' => $this->student->id, 'status' => 'in_progress', 'started_at' => now()]);
    }

    private function asExtension(): static
    {
        return $this->withHeaders(['X-Session-Token' => $this->session->issueExtensionToken()]);
    }

    /** About 800 characters of new code, none of it from the starter file. */
    private function solution(): string
    {
        $body = '';
        for ($i = 0; $i < 20; $i++) {
            $body .= "        int value{$i} = compute({$i}) + offset({$i});\n";
        }

        return "public class Main {\n    public static void main(String[] args) {\n{$body}    }\n}\n";
    }

    private function submit(?string $code = null): void
    {
        $this->asExtension()
            ->postJson("/api/v1/sessions/{$this->session->id}/submit", ['code' => $code ?? $this->solution(), 'language' => 'java'])
            ->assertOk();
    }

    private function flags(string $type)
    {
        return Anomaly::where('lab_session_id', $this->session->id)->where('type', $type)->get();
    }

    public function test_submission_with_no_typing_behind_it_is_flagged_high(): void
    {
        $this->submit();

        $flag = $this->flags('low_typing_effort')->sole();
        $this->assertSame('high', $flag->severity);
        $this->assertSame(0, $flag->metadata['typed_chars']);
    }

    public function test_flag_does_not_change_the_grade(): void
    {
        $this->submit();
        $flagged = $this->session->fresh()->performance_score;

        $this->session->refresh()->update(['status' => 'in_progress', 'ended_at' => null, 'keystroke_count' => 5000]);
        Anomaly::where('lab_session_id', $this->session->id)->delete();
        $this->submit();

        $this->assertSame($flagged, $this->session->fresh()->performance_score);
    }

    public function test_some_typing_but_too_little_is_medium(): void
    {
        $this->session->update(['keystroke_count' => 120]);

        $this->submit();

        $this->assertSame('medium', $this->flags('low_typing_effort')->sole()->severity);
    }

    public function test_typed_code_raises_no_flag(): void
    {
        $this->session->update(['keystroke_count' => 900]);

        $this->submit();

        $this->assertCount(0, $this->flags('low_typing_effort'));
    }

    public function test_code_already_flagged_as_pasted_is_not_flagged_again(): void
    {
        TelemetryLog::create(['lab_session_id' => $this->session->id, 'event_type' => 'paste_anomaly', 'payload' => ['pasted_length' => 900]]);

        $this->submit();

        $this->assertCount(0, $this->flags('low_typing_effort'));
    }

    public function test_starter_code_and_small_submissions_raise_no_flag(): void
    {
        $this->submit(self::STARTER."// a short note\n");

        $this->assertCount(0, $this->flags('low_typing_effort'));
    }

    /** A short lab's whole answer: about 200 characters of new code. */
    private function shortSolution(): string
    {
        return <<<'JAVA'
        public class Main {
            public static void main(String[] args) {
                IntStream.rangeClosed(1, 100)
                        .mapToObj(number -> {
                            if (number % 15 == 0) {
                                return "FizzBuzz";
                            }
                            if (number % 3 == 0) {
                                return "Fizz";
                            }
                            if (number % 5 == 0) {
                                return "Buzz";
                            }
                            return Integer.toString(number);
                        })
                        .forEach(System.out::println);
            }
        }
        JAVA;
    }

    public function test_short_solution_with_no_typing_behind_it_is_flagged(): void
    {
        $this->submit($this->shortSolution());

        $this->assertSame('high', $this->flags('low_typing_effort')->sole()->severity);
    }

    public function test_short_solution_that_was_typed_raises_no_flag(): void
    {
        $this->session->update(['keystroke_count' => 250]);

        $this->submit($this->shortSolution());

        $this->assertCount(0, $this->flags('low_typing_effort'));
    }

    public function test_group_labs_are_not_checked(): void
    {
        $group = Group::create(['name' => 'Team', 'lab_id' => $this->lab->id]);
        $this->session->update(['group_id' => $group->id]);

        $this->submit();

        $this->assertCount(0, $this->flags('low_typing_effort'));
    }

    public function test_finishing_from_the_website_runs_the_same_check(): void
    {
        $this->session->update(['submitted_code' => $this->solution()]);

        $this->actingAs($this->student)->post(route('sessions.complete', $this->session->id));

        $this->assertSame('completed', $this->session->fresh()->status);
        $this->assertCount(1, $this->flags('low_typing_effort'));
    }

    public function test_resubmitting_a_reopened_attempt_does_not_flag_twice(): void
    {
        $this->submit();
        $this->session->refresh()->update(['status' => 'in_progress', 'ended_at' => null]);

        $this->submit();

        $this->assertCount(1, $this->flags('low_typing_effort'));
    }

    public function test_keystroke_count_never_goes_down(): void
    {
        $this->session->update(['keystroke_count' => 800]);

        $this->asExtension()->postJson("/api/v1/sessions/{$this->session->id}/telemetry", [
            'event_type' => 'wpm_update',
            'payload' => ['wpm' => 3, 'keystroke_count' => 40],
        ])->assertOk();

        $this->assertSame(800, $this->session->fresh()->keystroke_count);
    }

    public function test_session_read_returns_the_keystroke_count_for_a_reloaded_extension(): void
    {
        $this->session->update(['keystroke_count' => 800]);

        $this->asExtension()->getJson("/api/v1/sessions/{$this->session->id}")
            ->assertOk()
            ->assertJsonPath('keystroke_count', 800);
    }

    public function test_keeping_existing_files_raises_a_flag(): void
    {
        $this->asExtension()->postJson("/api/v1/sessions/{$this->session->id}/telemetry", [
            'event_type' => 'starter_files_kept',
            'payload' => ['files' => ['Main.java'], 'changed_lines' => 24],
        ])->assertOk();

        $flag = $this->flags('starter_files_kept')->sole();
        $this->assertSame('medium', $flag->severity);
        $this->assertStringContainsString('Main.java', $flag->description);
    }

    public function test_external_change_severity_follows_its_size(): void
    {
        foreach ([6, 40] as $lines) {
            $this->asExtension()->postJson("/api/v1/sessions/{$this->session->id}/telemetry", [
                'event_type' => 'external_file_change',
                'payload' => ['file' => 'Main.java', 'changed_lines' => $lines],
            ])->assertOk();
        }

        $this->assertSame(['medium', 'high'], $this->flags('external_file_change')->sortBy('id')->pluck('severity')->all());
    }

    public function test_session_timeline_describes_the_new_file_events(): void
    {
        TelemetryLog::create(['lab_session_id' => $this->session->id, 'event_type' => 'external_file_change', 'payload' => ['file' => 'Main.java', 'changed_lines' => 40]]);
        TelemetryLog::create(['lab_session_id' => $this->session->id, 'event_type' => 'starter_files_kept', 'payload' => ['files' => ['Main.java'], 'changed_lines' => 24]]);
        $admin = User::create(['name' => 'Ada Admin', 'email' => 'ada@example.com', 'password' => bcrypt('password'), 'role' => 'admin']);

        $this->actingAs($admin)->get("/sessions/{$this->session->id}/telemetry-timeline")
            ->assertOk()
            ->assertSee('changed outside the editor')
            ->assertSee('instead of the starter code');
    }

    public function test_large_insert_is_flagged_medium_with_its_code(): void
    {
        $this->asExtension()->postJson("/api/v1/sessions/{$this->session->id}/telemetry", [
            'event_type' => 'large_insert',
            'payload' => ['file' => 'Main.java', 'inserted_length' => 620, 'snippet' => 'int answer = 42;'],
        ])->assertOk();

        $flag = $this->flags('large_insert')->sole();
        $this->assertSame('medium', $flag->severity);
        $this->assertStringContainsString('620 characters', $flag->description);
        $this->assertSame('int answer = 42;', $flag->metadata['snippet']);
    }

    public function test_large_insert_keeps_a_long_snippet_whole(): void
    {
        $snippet = str_repeat('int value = 1;', 500);

        $this->asExtension()->postJson("/api/v1/sessions/{$this->session->id}/telemetry", [
            'event_type' => 'large_insert',
            'payload' => ['file' => 'Main.java', 'inserted_length' => strlen($snippet), 'snippet' => $snippet],
        ])->assertOk();

        $this->assertSame($snippet, $this->flags('large_insert')->sole()->metadata['snippet']);
        $this->assertSame($snippet, TelemetryLog::where('event_type', 'large_insert')->sole()->payload['snippet']);
    }

    public function test_large_insert_after_the_attempt_closed_raises_no_flag(): void
    {
        $this->session->update(['status' => 'completed', 'ended_at' => now()]);

        $this->asExtension()->postJson("/api/v1/sessions/{$this->session->id}/telemetry", [
            'event_type' => 'large_insert',
            'payload' => ['file' => 'Main.java', 'inserted_length' => 620, 'snippet' => 'int answer = 42;'],
        ])->assertOk();

        $this->assertCount(0, $this->flags('large_insert'));
    }

    public function test_session_timeline_shows_a_large_insert_and_its_code(): void
    {
        TelemetryLog::create(['lab_session_id' => $this->session->id, 'event_type' => 'large_insert', 'payload' => ['file' => 'Main.java', 'inserted_length' => 620, 'snippet' => 'int answer = 42;']]);
        $admin = User::create(['name' => 'Ada Admin', 'email' => 'ada@example.com', 'password' => bcrypt('password'), 'role' => 'admin']);

        $this->actingAs($admin)->get("/sessions/{$this->session->id}/telemetry-timeline")
            ->assertOk()
            ->assertSee('without being typed or pasted')
            ->assertSee('int answer = 42;');
    }

    public function test_file_changes_after_the_attempt_closed_raise_no_flag(): void
    {
        $this->session->update(['status' => 'completed', 'ended_at' => now()]);

        $this->asExtension()->postJson("/api/v1/sessions/{$this->session->id}/telemetry", [
            'event_type' => 'external_file_change',
            'payload' => ['file' => 'Main.java', 'changed_lines' => 40],
        ])->assertOk();

        $this->assertCount(0, $this->flags('external_file_change'));
    }
}
