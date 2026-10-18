<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Regression test for assets that fell through to Laravel on Vercel and returned 404: the
 * face-detection models (so in-lab proctoring silently dropped to the low-light check) and
 * the brand font. Each file must be served from public/ by a vercel.json route.
 */
class VercelStaticRoutesTest extends TestCase
{
    /**
     * The destination Vercel picks for a path: the first route whose src matches.
     */
    private function destinationFor(string $path, array $routes): ?string
    {
        foreach ($routes as $route) {
            $pattern = '#^' . str_replace('#', '\#', $route['src']) . '$#';
            if (preg_match($pattern, $path, $m)) {
                return preg_replace_callback('/\$(\d+)/', fn ($g) => $m[(int) $g[1]] ?? '', $route['dest']);
            }
        }

        return null;
    }

    public function test_face_models_and_fonts_are_served_as_static_files(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = json_decode(file_get_contents($root . '/vercel.json'), true)['routes'];
        $assets = array_merge(glob($root . '/public/models/*'), glob($root . '/public/fonts/*'));
        $this->assertNotEmpty($assets);

        foreach ($assets as $file) {
            $path = '/' . str_replace('\\', '/', substr($file, strlen($root . '/public/')));
            $dest = $this->destinationFor($path, $routes);

            $this->assertNotSame('/api/index.php', $dest, "{$path} would be sent to Laravel and 404");
            $this->assertFileExists($root . $dest, "{$path} is routed to a file that doesn't exist");
        }
    }
}
