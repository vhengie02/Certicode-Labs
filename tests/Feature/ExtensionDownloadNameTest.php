<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

/**
 * The extension download is saved as certicode-labs-<version>.vsix, so a student can tell
 * which build they have. The version is written in four places that must agree: the
 * extension's package.json, the built .vsix, config/services.php and vercel.json.
 */
class ExtensionDownloadNameTest extends TestCase
{
    use RefreshDatabase;

    private function latestVersion(): string
    {
        return (string) config('services.vscode_extension.latest_version');
    }

    private function expectedFilename(): string
    {
        return 'certicode-labs-'.$this->latestVersion().'.vsix';
    }

    public function test_latest_version_matches_the_extension_package_json(): void
    {
        $package = json_decode(file_get_contents(base_path('certicode-labs-extension/package.json')), true);

        $this->assertSame($package['version'], $this->latestVersion());
    }

    public function test_the_built_vsix_is_the_latest_version(): void
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open(public_path(config('services.vscode_extension.download_path'))) === true);
        $manifest = json_decode($zip->getFromName('extension/package.json'), true);
        $zip->close();

        $this->assertSame($this->latestVersion(), $manifest['version']);
    }

    public function test_min_version_is_not_newer_than_the_download(): void
    {
        $min = (string) config('services.vscode_extension.min_version');

        $this->assertTrue(version_compare($min, $this->latestVersion(), '<='), "min_version {$min} is newer than the download");
    }

    public function test_vercel_serves_the_vsix_under_its_versioned_name(): void
    {
        $routes = json_decode(file_get_contents(base_path('vercel.json')), true)['routes'];
        $path = '/'.config('services.vscode_extension.download_path');

        $named = null;
        $served = null;
        foreach ($routes as $index => $route) {
            if (preg_match('#^'.$route['src'].'$#', $path) !== 1) {
                continue;
            }
            if (isset($route['headers']['Content-Disposition'])) {
                $named ??= $index;
                $this->assertTrue($route['continue'] ?? false, 'the header route must let the file route run after it');
                $this->assertSame('attachment; filename="'.$this->expectedFilename().'"', $route['headers']['Content-Disposition']);
            } elseif (isset($route['dest'])) {
                $served ??= $index;
            }
        }

        $this->assertNotNull($named, 'no vercel.json route names the download');
        $this->assertNotNull($served, 'no vercel.json route serves the download');
        $this->assertLessThan($served, $named, 'the header route must come before the route that serves the file');
    }

    public function test_the_home_page_downloads_under_the_versioned_name(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('download="'.$this->expectedFilename().'"', false);
    }

    public function test_the_getting_started_step_downloads_under_the_versioned_name(): void
    {
        $student = User::create(['name' => 'Student', 'email' => 'new@example.com', 'password' => bcrypt('password'), 'role' => 'student']);

        $this->actingAs($student)
            ->get('/dashboard/sections', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSee('download="'.$this->expectedFilename().'"', false);
    }
}
