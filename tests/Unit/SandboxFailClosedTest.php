<?php

namespace Tests\Unit;

use App\Services\SandboxExecutionService;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Regression tests for the unsandboxed fallback: without Judge0, student code must not run on
 * the app server outside local development, where it would see the server's environment
 * (database password, API keys).
 */
class SandboxFailClosedTest extends TestCase
{
    private SandboxExecutionService $sandbox;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.judge0.url' => null, 'services.judge0.key' => null, 'services.sandbox.host_execution' => null]);
        $this->sandbox = new SandboxExecutionService();
    }

    public static function hostRunnableCode(): array
    {
        return [
            'php' => ['php', '<?php echo "ran-on-host";'],
            'python' => ['python', 'print("ran-on-host")'],
            'javascript' => ['javascript', 'console.log("ran-on-host")'],
            'bash' => ['bash', 'echo ran-on-host'],
            'java' => ['java', 'public class Main { public static void main(String[] a) { System.out.println("ran-on-host"); } }'],
        ];
    }

    #[DataProvider('hostRunnableCode')]
    public function test_production_without_judge0_does_not_run_code_on_the_host(string $language, string $code): void
    {
        $this->app['env'] = 'production';

        $result = $this->sandbox->execute($code, $language);

        $this->assertSame('unavailable', $result['status']);
        $this->assertSame('', $result['output']);
        $this->assertStringContainsString('not set up', $result['errors']);
    }

    public function test_host_execution_can_be_switched_off_even_in_local_development(): void
    {
        config(['services.sandbox.host_execution' => false]);

        $result = $this->sandbox->execute('<?php echo "ran-on-host";', 'php');

        $this->assertSame('unavailable', $result['status']);
        $this->assertSame('', $result['output']);
    }

    public function test_host_execution_still_works_in_tests_by_default(): void
    {
        $this->assertTrue($this->sandbox->isHostExecutionAllowed());

        $this->app['env'] = 'production';
        $this->assertFalse($this->sandbox->isHostExecutionAllowed());
    }

    public function test_production_with_judge0_still_runs_code_remotely(): void
    {
        $this->app['env'] = 'production';
        config(['services.judge0.url' => 'https://judge0.test']);
        Http::fake(['judge0.test/*' => Http::response(['status' => ['id' => 3, 'description' => 'Accepted'], 'stdout' => base64_encode("remote\n")])]);

        $result = $this->sandbox->execute('print("remote")', 'python');

        $this->assertSame('success', $result['status']);
        $this->assertSame("remote\n", $result['output']);
        Http::assertSentCount(1);
    }
}
