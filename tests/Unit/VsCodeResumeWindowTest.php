<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\LabSessionController;
use PHPUnit\Framework\TestCase;
use ReflectionClassConstant;

/**
 * The extension's resume window has to end before the server's reload grace does. Once the grace
 * is over the lab page leaves and stops the camera, so an extension resuming after that carries
 * on with the attempt unwatched. The two values live in different languages and drifted apart
 * once, when the grace dropped to 12 s and the window stayed at 25 s.
 */
class VsCodeResumeWindowTest extends TestCase
{
    /** Time the extension gets after the window ends for its first ping to reach the server. */
    private const PING_MARGIN_SECONDS = 3;

    public function test_the_extension_stops_resuming_before_the_server_grace_ends(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/certicode-labs-extension/src/sidebarProvider.ts');
        $this->assertSame(1, preg_match('/const RESUME_WINDOW_MS = (\d+);/', $source, $match));

        $graceSeconds = (new ReflectionClassConstant(LabSessionController::class, 'VSCODE_RELOAD_GRACE_SECONDS'))->getValue();

        $this->assertLessThanOrEqual(
            $graceSeconds - self::PING_MARGIN_SECONDS,
            (int) $match[1] / 1000,
            'RESUME_WINDOW_MS must end before VSCODE_RELOAD_GRACE_SECONDS, with time left for a ping.'
        );
    }
}
