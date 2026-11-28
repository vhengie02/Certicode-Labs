<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ThemeSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Design Tester',
            'email' => 'designer@certicode.test',
            'password' => bcrypt('password123'),
            'role' => 'student',
        ]);
    }

    /**
     * Test appearance & theme settings section renders with all 3 theme options.
     */
    public function test_settings_page_renders_appearance_and_theme_options(): void
    {
        $response = $this->actingAs($this->user)->get(route('settings.show'));

        $response->assertStatus(200);
        $response->assertSee('Appearance');
        $response->assertSee('System', false);
        $response->assertSee('Dark Mode');
        $response->assertSee('Light Mode');
        $response->assertSee('theme-card-system');
        $response->assertSee('theme-card-dark');
        $response->assertSee('theme-card-light');
        $response->assertSee('theme-status-indicator');
    }

    /**
     * Test header contains quick theme toggle and script contains theme management functions.
     */
    public function test_app_layout_contains_theme_toggle_and_scripts(): void
    {
        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('quick-theme-toggle');
        $response->assertSee('theme-toggle-sun');
        $response->assertSee('theme-toggle-moon');
        $response->assertSee('applyTheme');
        $response->assertSee('toggleQuickTheme');
    }

    /**
     * Tailwind puts utilities in a CSS layer, where `!important` beats the layout's light-mode
     * `!important` rules. A field with a dark `!bg-[...]` would keep it in light mode, under dark
     * text, so such a background has to be limited to dark mode.
     */
    public function test_form_fields_force_a_background_only_in_dark_mode(): void
    {
        $offenders = [];
        foreach (File::allFiles(resource_path('views')) as $view) {
            preg_match_all('/class="[^"]*\bui-input\b[^"]*"/', $view->getContents(), $matches);
            foreach ($matches[0] as $class) {
                if (preg_match('/(?<!dark:)!bg-/', $class)) {
                    $offenders[] = $view->getRelativePathname().': '.$class;
                }
            }
        }

        $this->assertSame([], $offenders);
    }
}
