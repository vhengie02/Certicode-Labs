<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Every class card shows a decorative pattern with colours for both themes. Without a choice from
 * the instructor, the pattern comes from the class id.
 */
class ClassPatternTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // These pages cache by user id in the file store, which real local runs share
        config(['cache.stores.file' => ['driver' => 'array']]);
    }

    public function test_each_pattern_renders_a_tile_with_the_given_id(): void
    {
        foreach (array_keys(SchoolClass::CARD_PATTERNS) as $pattern) {
            $html = Blade::render('<x-class-pattern :pattern="$pattern" id="tile" />', ['pattern' => $pattern]);

            $this->assertStringContainsString("class-pattern--{$pattern}", $html);
            $this->assertStringContainsString('<pattern id="tile"', $html);
            $this->assertStringContainsString('fill="url(#tile)"', $html);
        }
    }

    public function test_without_a_choice_a_class_keeps_its_pattern_and_neighbours_differ(): void
    {
        $class = fn (int $id) => (new SchoolClass)->forceFill(['id' => $id]);

        $this->assertSame('dots', $class(9)->cardPattern());
        $this->assertSame('dots', $class(9)->cardPattern());
        $this->assertSame('chevrons', $class(10)->cardPattern());
    }

    public function test_an_unknown_saved_pattern_falls_back_to_the_automatic_one(): void
    {
        $class = (new SchoolClass)->forceFill(['id' => 9, 'card_pattern' => 'gone']);

        $this->assertSame('dots', $class->cardPattern());
    }

    public function test_every_pattern_has_light_and_dark_colours(): void
    {
        $css = file_get_contents(resource_path('css/class-patterns.css'));

        foreach (array_keys(SchoolClass::CARD_PATTERNS) as $pattern) {
            $this->assertMatchesRegularExpression("/^\.class-pattern--{$pattern} \{ --cp-paper:/m", $css);
            $this->assertMatchesRegularExpression("/^\.dark \.class-pattern--{$pattern} \{ --cp-paper:/m", $css);
        }
    }

    public function test_the_classes_page_puts_a_hidden_pattern_on_each_card(): void
    {
        $instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        $class = SchoolClass::create(['name' => 'Intro Course', 'code' => 'PATTERN1', 'instructor_id' => $instructor->id]);

        $this->actingAs($instructor)
            ->get(route('classes.index'))
            ->assertOk()
            ->assertSee('class="class-pattern class-pattern--'.$class->cardPattern().'" aria-hidden="true"', false);
    }
}
