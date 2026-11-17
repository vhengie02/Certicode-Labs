<?php

namespace Tests\Unit;

use App\Support\VercelRequest;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * Regression test for unstyled pages on Vercel. Every request runs api/index.php, and a request
 * whose path starts with /api (the VS Code extension's calls, the cron tick) made Laravel take
 * "/api" as the app's base path. URLs built during it kept that prefix: the instructor's
 * new-submission notification opened /api/sessions/{id}, whose stylesheet then pointed at
 * /api/build/... and 404'd.
 */
class VercelRequestTest extends TestCase
{
    /**
     * The server variables the PHP runtime hands api/index.php for a request to $uri.
     */
    private function vercelServer(string $uri): array
    {
        return [
            'REQUEST_URI' => $uri,
            'SCRIPT_FILENAME' => '/var/task/user/api/index.php',
            'SCRIPT_NAME' => '/api/index.php',
            'PHP_SELF' => '/api/index.php',
            'HTTP_HOST' => 'certicode.test',
            'HTTPS' => 'on',
            'REQUEST_METHOD' => 'GET',
        ];
    }

    private function request(array $server): Request
    {
        return new Request([], [], [], [], [], $server);
    }

    public function test_without_the_fix_an_api_request_gets_an_api_base_path(): void
    {
        $request = $this->request($this->vercelServer('/api/sessions/16'));

        $this->assertSame('/api', $request->getBaseUrl());
        $this->assertSame('https://certicode.test/api', $request->root());
    }

    public function test_an_api_request_keeps_its_route_but_loses_the_base_path(): void
    {
        $request = $this->request(VercelRequest::stripFunctionPrefix($this->vercelServer('/api/v1/sessions/16/submit?x=1')));

        $this->assertSame('', $request->getBaseUrl());
        $this->assertSame('v1/sessions/16/submit', $request->path());
        $this->assertSame('/v1/sessions/16/submit?x=1', $request->getRequestUri());
        $this->assertSame('https://certicode.test', $request->root());
    }

    public function test_an_old_api_link_opens_the_page_at_the_site_root(): void
    {
        $request = $this->request(VercelRequest::stripFunctionPrefix($this->vercelServer('/api/sessions/16')));

        $this->assertSame('sessions/16', $request->path());
        $this->assertSame('https://certicode.test', $request->root());
    }

    public function test_other_paths_are_unchanged(): void
    {
        foreach (['/' => '/', '/login' => 'login', '/apiary' => 'apiary', '/sessions/16?tab=1' => 'sessions/16'] as $uri => $path) {
            $request = $this->request(VercelRequest::stripFunctionPrefix($this->vercelServer($uri)));

            $this->assertSame('', $request->getBaseUrl(), $uri);
            $this->assertSame($path, $request->path(), $uri);
        }
    }

    public function test_bare_api_prefix_maps_to_the_root(): void
    {
        $this->assertSame('/', VercelRequest::stripFunctionPrefix($this->vercelServer('/api'))['REQUEST_URI']);
        $this->assertSame('/?x=1', VercelRequest::stripFunctionPrefix($this->vercelServer('/api?x=1'))['REQUEST_URI']);
    }

    public function test_the_vercel_bridge_applies_it_before_capturing_the_request(): void
    {
        $bridge = file_get_contents(dirname(__DIR__, 2).'/api/index.php');

        $strip = strpos($bridge, 'VercelRequest::stripFunctionPrefix($_SERVER)');
        $capture = strpos($bridge, 'Request::capture()');
        $this->assertNotFalse($strip);
        $this->assertNotFalse($capture);
        $this->assertLessThan($capture, $strip);
    }
}
