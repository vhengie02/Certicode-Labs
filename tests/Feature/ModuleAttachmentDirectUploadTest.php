<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\ModuleAttachment;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Vercel refuses request bodies over 4.5 MB, so with cloud storage the module form uploads
 * each file from the browser straight to the bucket through a signed URL and posts only the
 * key. The server must accept only keys it issued to this session for this class, for
 * objects that exist and are within the 20 MB limit.
 */
class ModuleAttachmentDirectUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;

    private User $student;

    private SchoolClass $class;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        $this->student = User::create(['name' => 'Student', 'email' => 'student@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $this->class = SchoolClass::create(['name' => 'Java 101', 'code' => 'JAVA-101', 'instructor_id' => $this->instructor->id]);
        $this->class->students()->attach($this->student->id, ['status' => 'enrolled']);

        Storage::fake('public');
        Storage::fake('supabase');
        config([
            'filesystems.attachments_disk' => 'supabase',
            'filesystems.disks.supabase.key' => 'test-key',
            'filesystems.disks.supabase.secret' => 'test-secret',
            'filesystems.disks.supabase.region' => 'ap-northeast-2',
            'filesystems.disks.supabase.bucket' => 'attachments',
        ]);
    }

    private function requestUploadUrl(?SchoolClass $class = null, string $name = 'Week 1 slides.pdf', int $size = 1024)
    {
        return $this->actingAs($this->instructor)->postJson(
            route('modules.attachment-uploads', ($class ?? $this->class)->id),
            ['file_name' => $name, 'file_size' => $size]
        );
    }

    /** What the browser does: get a signed URL, then PUT the file to it */
    private function uploadDirectly(string $contents = 'slides', string $name = 'Week 1 slides.pdf', ?SchoolClass $class = null): string
    {
        $key = $this->requestUploadUrl($class, $name, strlen($contents))->assertOk()->json('key');
        Storage::disk('supabase')->put($key, $contents);

        return $key;
    }

    private function createModule(array $uploads)
    {
        return $this->actingAs($this->instructor)->post(route('modules.store', $this->class->id), [
            'title' => 'Loops',
            'content' => '<p>Loops</p>',
            'uploaded_attachments' => $uploads,
        ]);
    }

    public function test_instructor_gets_a_signed_upload_url_for_a_new_key_under_module_attachments(): void
    {
        $first = $this->requestUploadUrl()->assertOk();
        $second = $this->requestUploadUrl()->assertOk();

        $this->assertMatchesRegularExpression('#^module_attachments/[A-Za-z0-9]{40}\.pdf$#', $first->json('key'));
        $this->assertNotSame($first->json('key'), $second->json('key'));
        $this->assertStringContainsString($first->json('key'), $first->json('url'));
        $this->assertStringContainsString('expiration=', $first->json('url'));
        $this->assertSame([], $first->json('headers'));
    }

    public function test_only_an_instructor_who_manages_the_class_gets_an_upload_url(): void
    {
        $url = route('modules.attachment-uploads', $this->class->id);
        $body = ['file_name' => 'a.pdf', 'file_size' => 10];
        $otherInstructor = User::create(['name' => 'Other', 'email' => 'other@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);

        $this->postJson($url, $body)->assertUnauthorized();
        $this->actingAs($this->student)->postJson($url, $body)->assertForbidden();
        $this->actingAs($otherInstructor)->postJson($url, $body)->assertForbidden();
    }

    public function test_upload_url_is_refused_for_files_over_20_mb(): void
    {
        $this->requestUploadUrl(size: 20 * 1024 * 1024 + 1)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file_size' => 'Files can be up to 20 MB each.']);
    }

    public function test_local_disk_keeps_the_multipart_upload(): void
    {
        $this->actingAs($this->instructor)->get(route('modules.create', $this->class->id))
            ->assertSee('data-direct-upload-url', false);

        config(['filesystems.attachments_disk' => 'public']);

        $this->requestUploadUrl()->assertNotFound();
        $this->actingAs($this->instructor)->get(route('modules.create', $this->class->id))
            ->assertDontSee('data-direct-upload-url', false);
    }

    public function test_module_is_created_from_uploaded_keys(): void
    {
        $key = $this->uploadDirectly('twenty bytes of data');

        $this->createModule([['key' => $key, 'name' => 'Week 1 slides.pdf']])->assertSessionHasNoErrors();

        $attachment = ModuleAttachment::firstOrFail();
        $this->assertSame($key, $attachment->file_path);
        $this->assertSame('Week 1 slides.pdf', $attachment->file_name);
        $this->assertSame(20, (int) $attachment->file_size, 'The size comes from storage');
        $this->assertSame(Module::firstOrFail()->id, $attachment->module_id);
    }

    public function test_a_key_can_only_be_attached_once(): void
    {
        $key = $this->uploadDirectly();
        $this->createModule([['key' => $key, 'name' => 'a.pdf']])->assertSessionHasNoErrors();

        $this->createModule([['key' => $key, 'name' => 'a.pdf']])
            ->assertSessionHasErrors(['attachments' => ModuleAttachment::UPLOAD_MISSING_MESSAGE]);

        $this->assertSame(1, Module::count());
        $this->assertSame(1, ModuleAttachment::count());
        Storage::disk('supabase')->assertExists($key);
    }

    public function test_editing_a_module_adds_uploaded_keys(): void
    {
        $this->createModule([])->assertSessionHasNoErrors();
        $module = Module::firstOrFail();
        $key = $this->uploadDirectly();

        $this->actingAs($this->instructor)->put(route('modules.update', [$this->class->id, $module->id]), [
            'title' => 'Loops',
            'content' => '<p>Loops</p>',
            'uploaded_attachments' => [['key' => $key, 'name' => 'Week 2.pdf']],
        ])->assertSessionHasNoErrors();

        $this->assertSame($key, $module->attachments()->firstOrFail()->file_path);
    }

    public function test_forged_keys_are_refused_and_the_files_they_name_are_left_alone(): void
    {
        // Another module's attachment, uploaded through the form
        $this->actingAs($this->instructor)->post(route('modules.store', $this->class->id), [
            'title' => 'Existing',
            'content' => '<p>Existing</p>',
            'attachments' => [UploadedFile::fake()->create('existing.pdf', 10)],
        ])->assertSessionHasNoErrors();
        $existing = ModuleAttachment::firstOrFail()->file_path;
        Storage::disk('supabase')->put('secrets/leak.pdf', 'secret');

        $forged = [
            $existing,
            'secrets/leak.pdf',
            'module_attachments/../secrets/leak.pdf',
            'module_attachments/'.str_repeat('a', 40).'.pdf',
        ];
        foreach ($forged as $key) {
            $this->createModule([['key' => $key, 'name' => 'stolen.pdf']])
                ->assertSessionHasErrors(['attachments' => ModuleAttachment::UPLOAD_MISSING_MESSAGE]);
        }

        $this->assertSame(1, Module::count());
        $this->assertSame(1, ModuleAttachment::count());
        Storage::disk('supabase')->assertExists($existing);
        Storage::disk('supabase')->assertExists('secrets/leak.pdf');
    }

    public function test_a_key_issued_but_never_uploaded_is_refused(): void
    {
        $key = $this->requestUploadUrl()->json('key');

        $this->createModule([['key' => $key, 'name' => 'a.pdf']])
            ->assertSessionHasErrors(['attachments' => ModuleAttachment::UPLOAD_MISSING_MESSAGE]);

        $this->assertSame(0, Module::count());
    }

    public function test_a_key_issued_for_another_class_is_refused(): void
    {
        $otherClass = SchoolClass::create(['name' => 'Python 101', 'code' => 'PY-101', 'instructor_id' => $this->instructor->id]);
        $key = $this->uploadDirectly(class: $otherClass);

        $this->createModule([['key' => $key, 'name' => 'a.pdf']])
            ->assertSessionHasErrors(['attachments' => ModuleAttachment::UPLOAD_MISSING_MESSAGE]);

        $this->assertSame(0, Module::count());
    }

    public function test_an_upload_over_20_mb_is_refused_and_removed(): void
    {
        // The browser can claim any size when asking for the URL, then send a bigger file
        $key = $this->requestUploadUrl(name: 'huge.zip', size: 1024)->assertOk()->json('key');
        Storage::disk('supabase')->put($key, str_repeat('x', ModuleAttachment::MAX_SIZE_KB * 1024 + 1));

        $this->createModule([['key' => $key, 'name' => 'huge.zip']])
            ->assertSessionHasErrors('attachments');

        $this->assertSame(0, Module::count());
        Storage::disk('supabase')->assertMissing($key);
    }

    public function test_files_already_uploaded_come_back_when_the_form_bounces(): void
    {
        $key = $this->uploadDirectly();

        $this->actingAs($this->instructor)
            ->from(route('modules.create', $this->class->id))
            ->followingRedirects()
            ->post(route('modules.store', $this->class->id), [
                'title' => 'Loops',
                'content' => '',
                'uploaded_attachments' => [['key' => $key, 'name' => 'Week 1 slides.pdf']],
            ])
            ->assertSee('Week 1 slides.pdf')
            ->assertSee('value="'.$key.'"', false);

        // Still accepted on the next try
        $this->createModule([['key' => $key, 'name' => 'Week 1 slides.pdf']])->assertSessionHasNoErrors();
    }
}
