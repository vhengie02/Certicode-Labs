<?php

namespace Tests\Feature;

use App\Models\ModuleAttachment;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Instructors choose a class card's background in the class form: a pattern, or their own picture.
 */
class ClassBackgroundTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private User $instructor;

    protected function setUp(): void
    {
        parent::setUp();

        // These pages cache by user id in the file store, which real local runs share
        config(['cache.stores.file' => ['driver' => 'array'], 'filesystems.attachments_disk' => 'public']);
        Storage::fake('public');

        $this->instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
    }

    private function picture(string $name = 'photo.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(self::PNG));
    }

    private function class(array $attributes = []): SchoolClass
    {
        return SchoolClass::create([...['name' => 'Intro Course', 'code' => 'CLASS-BG1', 'instructor_id' => $this->instructor->id], ...$attributes]);
    }

    private function update(SchoolClass $class, array $fields)
    {
        return $this->actingAs($this->instructor)->put(route('classes.update', $class->id), [...['name' => $class->name], ...$fields]);
    }

    public function test_the_create_and_edit_forms_offer_every_background(): void
    {
        $class = $this->class();

        foreach ([route('classes.create'), route('classes.edit', $class->id)] as $url) {
            $response = $this->actingAs($this->instructor)->get($url)->assertOk()
                ->assertSee('enctype="multipart/form-data"', false)
                ->assertSee('name="card_image"', false)
                ->assertSee('value="auto"', false)
                ->assertSee('value="picture"', false);

            foreach (array_keys(SchoolClass::CARD_PATTERNS) as $pattern) {
                $response->assertSee("value=\"{$pattern}\"", false);
            }
        }
    }

    public function test_creating_a_class_saves_the_chosen_pattern(): void
    {
        $this->actingAs($this->instructor)
            ->post(route('classes.store'), ['name' => 'Waves', 'card_background' => 'scallops'])
            ->assertRedirect(route('classes.index'));

        $class = SchoolClass::where('name', 'Waves')->firstOrFail();
        $this->assertSame('scallops', $class->card_pattern);
        $this->assertSame('scallops', $class->cardPattern());
    }

    public function test_creating_a_class_saves_an_uploaded_picture(): void
    {
        $this->actingAs($this->instructor)
            ->post(route('classes.store'), ['name' => 'Photo', 'card_background' => 'picture', 'card_image' => $this->picture()])
            ->assertRedirect(route('classes.index'));

        $path = SchoolClass::where('name', 'Photo')->value('card_image_path');
        $this->assertStringStartsWith(SchoolClass::CARD_IMAGE_DIRECTORY.'/', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_a_new_picture_replaces_the_old_file(): void
    {
        $class = $this->class();
        $this->update($class, ['card_image' => $this->picture()])->assertRedirect();
        $first = $class->refresh()->card_image_path;

        $this->update($class, ['card_image' => $this->picture('another.png')])->assertRedirect();
        $second = $class->refresh()->card_image_path;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_a_new_picture_wins_over_the_chosen_pattern(): void
    {
        $class = $this->class(['card_pattern' => 'moons']);

        $this->update($class, ['card_background' => 'dots', 'card_image' => $this->picture()])->assertRedirect();

        $this->assertNull($class->refresh()->card_pattern);
        $this->assertNotNull($class->card_image_path);
    }

    public function test_choosing_a_pattern_removes_the_picture(): void
    {
        $class = $this->class();
        $this->update($class, ['card_image' => $this->picture()])->assertRedirect();
        $path = $class->refresh()->card_image_path;

        $this->update($class, ['card_background' => 'moons'])->assertRedirect();

        $this->assertNull($class->refresh()->card_image_path);
        $this->assertSame('moons', $class->card_pattern);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_automatic_clears_the_chosen_pattern(): void
    {
        $class = $this->class(['card_pattern' => 'moons']);

        $this->update($class, ['card_background' => 'auto'])->assertRedirect();

        $this->assertNull($class->refresh()->card_pattern);
    }

    public function test_keeping_your_picture_leaves_it_alone(): void
    {
        $class = $this->class();
        $this->update($class, ['card_image' => $this->picture()])->assertRedirect();
        $path = $class->refresh()->card_image_path;

        $this->update($class, ['card_background' => 'picture'])->assertRedirect();

        $this->assertSame($path, $class->refresh()->card_image_path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_saving_without_the_fields_changes_nothing(): void
    {
        $class = $this->class(['card_pattern' => 'moons']);

        $this->update($class, [])->assertRedirect();

        $this->assertSame('moons', $class->refresh()->card_pattern);
    }

    public function test_your_picture_needs_a_file(): void
    {
        $class = $this->class();

        $this->update($class, ['card_background' => 'picture'])->assertSessionHasErrors('card_image');
    }

    public function test_an_unknown_background_is_refused(): void
    {
        $this->update($this->class(), ['card_background' => 'nope'])->assertSessionHasErrors('card_background');
    }

    public function test_only_jpg_png_and_webp_pictures_are_accepted(): void
    {
        $class = $this->class();

        $this->update($class, ['card_image' => $this->realUpload('photo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')])
            ->assertSessionHasErrors('card_image');
        $this->update($class, ['card_image' => $this->realUpload('photo.png', '<?php echo "not a picture";')])
            ->assertSessionHasErrors('card_image');
        $this->assertNull($class->refresh()->card_image_path);
    }

    public function test_a_real_png_passes_the_content_check(): void
    {
        $class = $this->class();

        $this->update($class, ['card_image' => $this->realUpload('photo.png', base64_decode(self::PNG))])->assertRedirect();

        $this->assertNotNull($class->refresh()->card_image_path);
    }

    /**
     * An upload whose type is read from its content, as in production. Fake uploads report a type
     * from the file name alone, so they can't show that a disguised file gets caught.
     */
    private function realUpload(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }

    public function test_a_picture_over_the_limit_is_refused(): void
    {
        $class = $this->class();
        $large = UploadedFile::fake()->create('photo.png', SchoolClass::CARD_IMAGE_MAX_KB + 1, 'image/png');

        $this->update($class, ['card_image' => $large])->assertSessionHasErrors('card_image');
    }

    public function test_a_picture_is_refused_when_storage_would_lose_it(): void
    {
        config(['filesystems.ephemeral_local_storage' => true]);
        $class = $this->class();

        $this->update($class, ['card_image' => $this->picture()])
            ->assertSessionHasErrors(['card_image' => ModuleAttachment::NOT_DURABLE_MESSAGE]);
    }

    public function test_deleting_a_class_removes_its_picture(): void
    {
        $class = $this->class();
        $this->update($class, ['card_image' => $this->picture()])->assertRedirect();
        $path = $class->refresh()->card_image_path;

        $this->actingAs($this->instructor)->delete(route('classes.destroy', $class->id))->assertRedirect();

        Storage::disk('public')->assertMissing($path);
    }

    public function test_the_card_shows_the_picture_instead_of_a_pattern(): void
    {
        $class = $this->class();
        $this->update($class, ['card_image' => $this->picture()])->assertRedirect();

        $this->actingAs($this->instructor)->get(route('classes.index'))->assertOk()
            ->assertSee('class="class-card-photo"', false)
            ->assertDontSee('class="class-pattern ', false);
    }

    public function test_the_picture_reaches_enrolled_students_and_staff_only(): void
    {
        $class = $this->class();
        $this->update($class, ['card_image' => $this->picture()])->assertRedirect();
        $url = route('classes.card-image', $class->id);

        $enrolled = User::create(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $class->students()->attach([$enrolled->id => ['status' => 'enrolled']]);
        $stranger = User::create(['name' => 'Bo', 'email' => 'bo@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $otherInstructor = User::create(['name' => 'Cy', 'email' => 'cy@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);

        $this->actingAs($this->instructor)->get($url)->assertOk();
        $this->actingAs($enrolled)->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($stranger)->get($url)->assertForbidden();
        $this->actingAs($otherInstructor)->get($url)->assertForbidden();
    }

    public function test_guests_cannot_load_a_picture(): void
    {
        $class = $this->class();

        $this->get(route('classes.card-image', $class->id))->assertRedirect(route('login'));
    }

    public function test_a_class_without_a_picture_has_nothing_to_load(): void
    {
        $class = $this->class();

        $this->actingAs($this->instructor)->get(route('classes.card-image', $class->id))->assertNotFound();
    }

    public function test_another_instructor_cannot_change_the_background(): void
    {
        $class = $this->class(['card_pattern' => 'moons']);
        $other = User::create(['name' => 'Cy', 'email' => 'cy@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);

        $this->actingAs($other)
            ->put(route('classes.update', $class->id), ['name' => 'Taken', 'card_background' => 'dots'])
            ->assertForbidden();

        $this->assertSame('moons', $class->refresh()->card_pattern);
    }
}
