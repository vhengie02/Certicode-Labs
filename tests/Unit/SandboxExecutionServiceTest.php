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
        $this->sandbox = new SandboxExecutionService();
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
}
