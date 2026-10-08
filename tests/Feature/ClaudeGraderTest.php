<?php

namespace Tests\Feature;

use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\LlmEvaluationService;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Client\ClientInterface;
use Tests\TestCase;

/**
 * Exercises the real Anthropic SDK request against a mocked HTTP transport (no network, no cost).
 */
class ClaudeGraderTest extends TestCase
{
    use RefreshDatabase;

    private const CODE = <<<'JAVA'
class InvalidAgeException extends Exception {
    public InvalidAgeException(String message) { super(message); }
}
class Student {
    private String name;
    private int age;
}
JAVA;

    private LabSession $session;
    private array $history = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.anthropic.key' => 'sk-ant-test', 'services.anthropic.grader_model' => 'claude-opus-5-5', 'services.anthropic.grader_effort' => 'medium']);

        $instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        $student = User::create(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $class = SchoolClass::create(['name' => 'CS 101', 'code' => 'CS101', 'instructor_id' => $instructor->id]);
        $module = Module::create(['class_id' => $class->id, 'title' => 'Module 1', 'content' => 'Content', 'order_index' => 0]);
        $lab = Laboratory::create([
            'module_id' => $module->id,
            'title' => 'Exceptions',
            'description' => 'Custom exceptions',
            'time_limit' => 45,
            'tasks_definition' => [['id' => 1, 'task' => 'Define InvalidAgeException'], ['id' => 2, 'task' => 'Encapsulate Student']],
        ]);
        $this->session = LabSession::create(['lab_id' => $lab->id, 'user_id' => $student->id, 'status' => 'in_progress'])->load('laboratory');
    }

    /** A grader whose HTTP transport returns the given Messages API responses and records requests. */
    private function grader(array $responses): LlmEvaluationService
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));
        $transport = new GuzzleClient(['handler' => $stack]);

        return new class($transport) extends LlmEvaluationService {
            public function __construct(private ClientInterface $transport) {}
            protected function graderTransport(): ClientInterface { return $this->transport; }
        };
    }

    private function apiMessage(array $grade, string $stopReason = 'end_turn'): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'id' => 'msg_test',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-opus-5-5',
            'content' => [['type' => 'text', 'text' => json_encode($grade)]],
            'stop_reason' => $stopReason,
            'stop_sequence' => null,
            'usage' => ['input_tokens' => 1200, 'output_tokens' => 300],
        ]));
    }

    private function sampleGrade(): array
    {
        return [
            'tasks' => [
                ['id' => 1, 'completed' => true, 'feedback' => 'Exception defined correctly.'],
                ['id' => 2, 'completed' => false, 'feedback' => 'Missing getters.'],
            ],
            'correctness_score' => 64,
            'competencies' => [
                ['name' => 'exception_handling', 'passed' => true, 'reason' => 'Custom exception extends Exception.'],
                ['name' => 'encapsulation', 'passed' => false, 'reason' => 'No accessors.'],
            ],
            'test_cases_passed' => 3,
            'test_cases_total' => 5,
            'code_quality_notes' => 'Clear names.',
            'summary' => 'Half the tasks are done.',
        ];
    }

    public function test_request_uses_claude_with_structured_output_and_fallbacks()
    {
        $this->grader([$this->apiMessage($this->sampleGrade())])->evaluate($this->session, self::CODE, 'java');

        $this->assertCount(1, $this->history);
        $request = $this->history[0]['request'];
        $body = json_decode((string) $request->getBody(), true);

        $this->assertSame('https://api.anthropic.com/v1/messages?beta=true', (string) $request->getUri());
        $this->assertSame('sk-ant-test', $request->getHeaderLine('x-api-key'));
        $this->assertStringContainsString('server-side-fallback-2026-07-01', $request->getHeaderLine('anthropic-beta'));
        $this->assertSame('claude-opus-5-5', $body['model']);
        $this->assertSame('default', $body['fallbacks']);
        $this->assertSame('medium', $body['output_config']['effort']);
        $this->assertSame('json_schema', $body['output_config']['format']['type']);
        $this->assertFalse($body['output_config']['format']['schema']['additionalProperties']);
        $this->assertArrayNotHasKey('thinking', $body); // adaptive by default; disabling is a 400 on this model
        $this->assertArrayNotHasKey('temperature', $body);
        $this->assertStringContainsString('InvalidAgeException', $body['messages'][0]['content']);
    }

    public function test_grade_is_mapped_to_the_evaluation_shape()
    {
        $result = $this->grader([$this->apiMessage($this->sampleGrade())])->evaluate($this->session, self::CODE, 'java');

        $this->assertSame(64, $result['correctness_score']);
        $this->assertSame([1 => true, 2 => false], array_column($result['tasks'], 'completed', 'id'));
        $this->assertSame(['passed' => false, 'reason' => 'No accessors.'], $result['competencies']['encapsulation']);
        $this->assertSame('Half the tasks are done.', $result['overall_feedback']);
        $this->assertSame('Clear names.', $result['code_quality_feedback']);
        $this->assertSame(3, $result['ai_grade_summary']['test_cases_passed']);
    }

    public function test_out_of_range_score_is_clamped()
    {
        $grade = $this->sampleGrade();
        $grade['correctness_score'] = 140;

        $result = $this->grader([$this->apiMessage($grade)])->evaluate($this->session, self::CODE, 'java');

        $this->assertSame(100, $result['correctness_score']);
    }

    public function test_refusal_falls_back_to_the_rule_based_grader()
    {
        $result = $this->grader([$this->apiMessage([], 'refusal')])->evaluate($this->session, self::CODE, 'java');

        $this->assertCount(1, $this->history);
        $this->assertArrayHasKey('tasks', $result);
        $this->assertCount(2, $result['tasks']); // the rule-based grader still grades every task
    }

    public function test_api_error_falls_back_to_the_rule_based_grader()
    {
        $error = new Response(529, ['Content-Type' => 'application/json'], json_encode(['type' => 'error', 'error' => ['type' => 'overloaded_error', 'message' => 'Overloaded']]));

        $result = $this->grader([$error, $error])->evaluate($this->session, self::CODE, 'java');

        $this->assertArrayHasKey('tasks', $result);
        $this->assertArrayHasKey('correctness_score', $result);
    }

    public function test_without_a_key_no_request_is_made()
    {
        config(['services.anthropic.key' => null]);

        $result = $this->grader([])->evaluate($this->session, self::CODE, 'java');

        $this->assertCount(0, $this->history);
        $this->assertArrayHasKey('tasks', $result);
    }
}
