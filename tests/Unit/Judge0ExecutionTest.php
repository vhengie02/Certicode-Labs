<?php

namespace Tests\Unit;

use App\Services\SandboxExecutionService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Judge0ExecutionTest extends TestCase
{
    private SandboxExecutionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.judge0.url' => 'https://judge0.test', 'services.judge0.key' => null, 'services.judge0.rapidapi_host' => null]);
        $this->service = new SandboxExecutionService;
    }

    private function fakeJudge0(array $result): void
    {
        Http::fake(['judge0.test/*' => Http::response($result)]);
    }

    public function test_java_runs_remotely_and_returns_stdout()
    {
        $this->fakeJudge0(['status' => ['id' => 3, 'description' => 'Accepted'], 'stdout' => base64_encode("Hello\n")]);

        $result = $this->service->execute("public class Hello {\n    public static void main(String[] args) { System.out.println(\"Hello\"); }\n}", 'java');

        $this->assertSame('success', $result['status']);
        $this->assertSame("Hello\n", $result['output']);

        Http::assertSent(function (Request $request) {
            $source = base64_decode($request['source_code']);

            return $request['language_id'] === 62
                && str_contains($request->url(), 'base64_encoded=true')
                && str_contains($request->url(), 'wait=true')
                && ! str_contains($source, 'public class Hello')        // public dropped: Judge0 compiles Main.java
                && str_contains($source, 'class Main')
                && str_contains($source, 'Hello.main(args);');         // Main delegates to the student's entry point
        });
    }

    public function test_entry_point_is_the_top_level_class_not_an_inner_class()
    {
        $this->fakeJudge0(['status' => ['id' => 3, 'description' => 'Accepted'], 'stdout' => '']);

        $this->service->execute("public class Outer {\n    static class Helper {}\n    public static void main(String[] args) {}\n}", 'java');

        Http::assertSent(fn (Request $request) => str_contains(base64_decode($request['source_code']), 'Outer.main(args);'));
    }

    public function test_existing_main_class_is_left_alone()
    {
        $this->fakeJudge0(['status' => ['id' => 3, 'description' => 'Accepted'], 'stdout' => '']);

        $this->service->execute("public class Main {\n    public static void main(String[] args) {}\n}", 'java');

        Http::assertSent(fn (Request $request) => substr_count(base64_decode($request['source_code']), 'class Main') === 1);
    }

    public function test_compilation_error_is_reported()
    {
        $this->fakeJudge0(['status' => ['id' => 6, 'description' => 'Compilation Error'], 'compile_output' => base64_encode("Main.java:2: error: ';' expected")]);

        $result = $this->service->execute('class Broken { int x = 5 }', 'java');

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString("Java Compilation Error:\nMain.java:2: error: ';' expected", $result['errors']);
    }

    public function test_rapidapi_headers_are_sent_when_configured()
    {
        config(['services.judge0.key' => 'secret-key', 'services.judge0.rapidapi_host' => 'judge0-ce.p.rapidapi.com']);
        $this->fakeJudge0(['status' => ['id' => 3, 'description' => 'Accepted'], 'stdout' => '']);

        $this->service->execute('print(1)', 'python');

        Http::assertSent(fn (Request $request) => $request->hasHeader('X-RapidAPI-Key', 'secret-key')
            && $request->hasHeader('X-RapidAPI-Host', 'judge0-ce.p.rapidapi.com')
            && $request['language_id'] === 71);
    }

    public function test_stdin_is_sent_only_when_given()
    {
        $this->fakeJudge0(['status' => ['id' => 3, 'description' => 'Accepted'], 'stdout' => '']);

        $this->service->execute('print(input())', 'python', null, "Ana\n");
        $this->service->execute('print(1)', 'python');

        $sent = Http::recorded()->map(fn ($pair) => $pair[0]);
        $this->assertSame("Ana\n", base64_decode($sent[0]['stdin']));
        $this->assertFalse(isset($sent[1]['stdin']));
    }

    public function test_exit_code_timing_memory_and_stderr_are_reported()
    {
        $this->fakeJudge0([
            'status' => ['id' => 3, 'description' => 'Accepted'],
            'stdout' => base64_encode("ok\n"),
            'stderr' => base64_encode("note\n"),
            'exit_code' => 0,
            'time' => '0.25',
            'memory' => 4096,
        ]);

        $result = $this->service->execute('print("ok")', 'python');

        $this->assertSame('success', $result['status']);
        $this->assertNull($result['errors']);
        $this->assertSame("note\n", $result['stderr']);
        $this->assertSame(0, $result['exit_code']);
        $this->assertEquals(250, $result['cpu_time_ms']);
        $this->assertSame(4096, $result['memory_kb']);
        $this->assertFalse($result['compile_error']);
    }

    public function test_compile_errors_are_flagged()
    {
        $this->fakeJudge0(['status' => ['id' => 6, 'description' => 'Compilation Error'], 'compile_output' => base64_encode('error')]);

        $this->assertTrue($this->service->execute('class Broken {', 'java')['compile_error']);
    }

    public function test_only_java_files_are_joined_into_one_source()
    {
        $files = [
            ['name' => 'main.py', 'content' => 'import helper', 'is_primary' => true],
            ['name' => 'helper.py', 'content' => 'x = 1', 'is_primary' => false],
        ];

        $this->assertSame(['source' => 'import helper', 'files' => ['main.py']], $this->service->combineSourceFiles($files, 'python'));
        $this->assertSame(['source' => 'class A {}', 'files' => ['A.java']], $this->service->combineSourceFiles([['name' => 'A.java', 'content' => 'class A {}', 'is_primary' => true]], 'java'));
    }

    public function test_unreachable_service_is_reported_as_unavailable()
    {
        Http::fake(['judge0.test/*' => Http::response('Bad Gateway', 502)]);

        $result = $this->service->execute('print(1)', 'python');

        $this->assertSame('unavailable', $result['status']);
    }
}
