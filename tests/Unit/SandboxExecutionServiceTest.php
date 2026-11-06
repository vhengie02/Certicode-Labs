<?php

namespace Tests\Unit;

use App\Services\SandboxExecutionService;
use Tests\TestCase;

class SandboxExecutionServiceTest extends TestCase
{
    protected SandboxExecutionService $sandbox;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandbox = new SandboxExecutionService;
    }

    /**
     * Test executing code with unsupported language.
     */
    public function test_unsupported_language_returns_error(): void
    {
        $result = $this->sandbox->execute('print("hello")', 'unsupported_lang');

        $this->assertEquals('error', $result['status']);
        $this->assertStringContainsString('Unsupported language', $result['errors']);
    }

    /**
     * Test executing valid PHP code locally.
     */
    public function test_execute_php_code_successfully(): void
    {
        $code = '<?php echo "Hello, Certicode!";';
        $result = $this->sandbox->execute($code, 'php');

        $this->assertEquals('success', $result['status']);
        $this->assertNull($result['errors']);
        $this->assertStringContainsString('Hello, Certicode!', $result['output']);
        $this->assertGreaterThan(0, $result['execution_time_ms']);
    }

    /**
     * Test executing PHP code with compilation or execution error.
     */
    public function test_execute_php_code_with_errors(): void
    {
        $code = '<?php undefined_function_call();';
        $result = $this->sandbox->execute($code, 'php');

        $this->assertEquals('error', $result['status']);
        $this->assertNotNull($result['errors']);
        $this->assertStringContainsString('Fatal error', $result['errors']);
    }

    /**
     * A program can print any bytes; the result must still fit in a JSON response.
     */
    public function test_program_output_that_is_not_valid_text_is_cleaned(): void
    {
        $code = '<?php fwrite(STDOUT, "ok\xFF\0"); fwrite(STDERR, "bad\xFF\0"); exit(1);';
        $result = $this->sandbox->execute($code, 'php');

        $this->assertEquals('error', $result['status']);
        $this->assertNotFalse(json_encode($result));
        $this->assertStringContainsString('ok', $result['output']);
        $this->assertStringContainsString('bad', $result['errors']);
        $this->assertStringNotContainsString("\0", $result['output'].$result['errors']);
    }

    /**
     * Test PHP code execution timeout limits.
     */
    public function test_execute_php_code_timeout(): void
    {
        // An infinite loop code that executes longer than the 5.0 seconds timeout limit
        $code = '<?php while(true) { usleep(100); }';

        $result = $this->sandbox->execute($code, 'php');

        $this->assertEquals('error', $result['status']);
        $this->assertStringContainsString('Timed Out', $result['errors']);
    }

    /**
     * Test executing valid Python code.
     */
    public function test_execute_python_code_successfully(): void
    {
        $code = 'print("Hello from Python!")';
        $result = $this->sandbox->execute($code, 'python');

        $this->assertEquals('success', $result['status']);
        $this->assertNull($result['errors']);
        $this->assertStringContainsString('Hello from Python!', $result['output']);
        $this->assertGreaterThan(0, $result['execution_time_ms']);
    }

    /**
     * Test executing Python code with syntax/runtime error.
     */
    public function test_execute_python_code_with_errors(): void
    {
        $code = 'print(1 / 0)';
        $result = $this->sandbox->execute($code, 'python');

        $this->assertEquals('error', $result['status']);
        $this->assertNotNull($result['errors']);
        $this->assertStringContainsString('ZeroDivisionError', $result['errors']);
    }

    /**
     * Test Python code execution timeout limits.
     */
    public function test_execute_python_code_timeout(): void
    {
        $code = 'import time; time.sleep(10)';
        $result = $this->sandbox->execute($code, 'python');

        $this->assertEquals('error', $result['status']);
        $this->assertStringContainsString('Timed Out', $result['errors']);
    }

    /**
     * Test executing valid JavaScript / Node.js code.
     */
    public function test_execute_javascript_code_successfully(): void
    {
        $code = 'console.log("Hello from JavaScript!");';
        $result = $this->sandbox->execute($code, 'javascript');

        $this->assertEquals('success', $result['status']);
        $this->assertNull($result['errors']);
        $this->assertStringContainsString('Hello from JavaScript!', $result['output']);
        $this->assertGreaterThan(0, $result['execution_time_ms']);
    }

    /**
     * Test executing JavaScript code with error.
     */
    public function test_execute_javascript_code_with_errors(): void
    {
        $code = 'throw new Error("Custom JS Crash");';
        $result = $this->sandbox->execute($code, 'javascript');

        $this->assertEquals('error', $result['status']);
        $this->assertNotNull($result['errors']);
        $this->assertStringContainsString('Custom JS Crash', $result['errors']);
    }

    /**
     * Test JavaScript execution timeout limits.
     */
    public function test_execute_javascript_code_timeout(): void
    {
        $code = 'while(true) {}';
        $result = $this->sandbox->execute($code, 'javascript');

        $this->assertEquals('error', $result['status']);
        $this->assertStringContainsString('Timed Out', $result['errors']);
    }

    /**
     * Test compiling and executing valid Java code.
     */
    public function test_execute_java_code_successfully(): void
    {
        $code = <<<'JAVA'
public class Main {
    public static void main(String[] args) {
        System.out.println("Hello from Java!");
    }
}
JAVA;
        $result = $this->sandbox->execute($code, 'java');

        $this->assertEquals('success', $result['status']);
        $this->assertNull($result['errors']);
        $this->assertStringContainsString('Hello from Java!', $result['output']);
        $this->assertGreaterThan(0, $result['execution_time_ms']);
    }

    /**
     * A library-style lab has no main(): compiling is all there is to do.
     */
    public function test_java_class_without_main_compiles_and_succeeds(): void
    {
        $code = <<<'JAVA'
public class Strings {
    public static String reverse(String s) {
        return new StringBuilder(s).reverse().toString();
    }
}
JAVA;
        $result = $this->sandbox->execute($code, 'java');

        $this->assertEquals('success', $result['status']);
        $this->assertNull($result['errors']);
        $this->assertSame('', $result['output']);
    }

    /**
     * A broken class without main() is still a compile error.
     */
    public function test_java_class_without_main_still_reports_compile_errors(): void
    {
        $result = $this->sandbox->execute('public class Strings { syntax error }', 'java');

        $this->assertEquals('error', $result['status']);
        $this->assertStringContainsString('Java Compilation Error', $result['errors']);
    }

    /**
     * Test Java compilation failure.
     */
    public function test_execute_java_code_with_compilation_errors(): void
    {
        $code = 'public class Main { syntax error }';
        $result = $this->sandbox->execute($code, 'java');

        $this->assertEquals('error', $result['status']);
        $this->assertNotNull($result['errors']);
        $this->assertStringContainsString('Java Compilation Error', $result['errors']);
    }

    /**
     * Test Java execution timeout limits.
     */
    public function test_execute_java_code_timeout(): void
    {
        $code = <<<'JAVA'
public class Main {
    public static void main(String[] args) throws Exception {
        Thread.sleep(10000);
    }
}
JAVA;
        $result = $this->sandbox->execute($code, 'java');

        $this->assertEquals('error', $result['status']);
        $this->assertStringContainsString('Timed Out', $result['errors']);
    }
}
