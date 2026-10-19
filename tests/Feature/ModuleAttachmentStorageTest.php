<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\ModuleAttachment;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regression tests for module attachments being written to Vercel's per-instance /tmp disk and
 * lost: files go to the configured attachments disk (Supabase Storage in production), and on
 * a throwaway local disk uploads are refused instead of silently dropped.
 */
class ModuleAttachmentStorageTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;

    private User $student;

    private SchoolClass $class;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        $this->student = User::create(['name' => 'Student', 'email' => 'student@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $this->class = SchoolClass::create(['name' => 'Java 101', 'code' => 'JAVA-101', 'instructor_id' => $this->instructor->id]);
        $this->class->students()->attach($this->student->id, ['status' => 'enrolled']);

        Storage::fake('public');
        Storage::fake('supabase');
    }

    private function useSupabase(): void
    {
        config([
            'filesystems.attachments_disk' => 'supabase',
            'filesystems.disks.supabase.key' => 'test-key',
            'filesystems.disks.supabase.secret' => 'test-secret',
            'filesystems.disks.supabase.region' => 'ap-northeast-2',
            'filesystems.disks.supabase.bucket' => 'attachments',
        ]);
    }

    /** Replace the attachments disk with one whose uploads always fail */
    private function failUploads(): void
    {
        $failing = \Mockery::mock(FilesystemAdapter::class);
        $failing->shouldReceive('putFileAs')->andThrow(new \RuntimeException('storage unreachable'));
        Storage::set('supabase', $failing);
    }

    private function createModuleWithFile(string $name = 'spec.pdf')
    {
        return $this->actingAs($this->instructor)->post("/classes/{$this->class->id}/modules", [
            'title' => 'Loops',
            'content' => '<p>Loops</p>',
            'attachments' => [UploadedFile::fake()->create($name, 100)],
        ]);
    }

    public function test_uploads_go_to_the_configured_cloud_disk_not_the_local_one(): void
    {
        $this->useSupabase();

        $this->createModuleWithFile()->assertRedirect();

        $attachment = ModuleAttachment::firstOrFail();
        Storage::disk('supabase')->assertExists($attachment->file_path);
        Storage::disk('public')->assertMissing($attachment->file_path);
    }

    public function test_cloud_download_redirects_to_a_short_lived_signed_url(): void
    {
        $this->useSupabase();
        $this->createModuleWithFile();
        $attachment = ModuleAttachment::firstOrFail();

        $response = $this->actingAs($this->student)->get(route('attachments.download', $attachment->id));

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringContainsString($attachment->file_path, $location);
        $this->assertStringContainsString('expiration=', $location);
    }

    public function test_local_development_download_still_streams_the_file(): void
    {
        $this->createModuleWithFile('notes.pdf');
        $attachment = ModuleAttachment::firstOrFail();
        Storage::disk('public')->assertExists($attachment->file_path);

        $this->actingAs($this->student)->get(route('attachments.download', $attachment->id))
            ->assertOk()
            ->assertDownload('notes.pdf');
    }

    public function test_uploads_to_a_throwaway_local_disk_are_refused_not_lost(): void
    {
        config(['filesystems.ephemeral_local_storage' => true]);

        $this->createModuleWithFile()->assertSessionHasErrors('attachments');

        $this->assertSame(0, Module::count());
        $this->assertSame(0, ModuleAttachment::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_modules_without_files_still_save_on_a_throwaway_disk(): void
    {
        config(['filesystems.ephemeral_local_storage' => true]);

        $this->actingAs($this->instructor)->post("/classes/{$this->class->id}/modules", [
            'title' => 'Reading only',
            'content' => '<p>No files</p>',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Module::count());
    }

    public function test_cloud_storage_is_used_even_where_local_disks_are_throwaway(): void
    {
        config(['filesystems.ephemeral_local_storage' => true]);
        $this->useSupabase();

        $this->createModuleWithFile()->assertSessionHasNoErrors();

        Storage::disk('supabase')->assertExists(ModuleAttachment::firstOrFail()->file_path);
    }

    public function test_a_cloud_disk_missing_its_settings_refuses_uploads_instead_of_crashing(): void
    {
        $this->useSupabase();
        config(['filesystems.disks.supabase.region' => null]);

        $this->createModuleWithFile()
            ->assertSessionHasErrors(['attachments' => ModuleAttachment::NOT_DURABLE_MESSAGE]);

        $this->assertSame(0, Module::count());
    }

    public function test_a_failed_upload_saves_no_module(): void
    {
        $this->useSupabase();
        $this->failUploads();

        $this->createModuleWithFile()
            ->assertSessionHasErrors(['attachments' => ModuleAttachment::UPLOAD_FAILED_MESSAGE]);

        $this->assertSame(0, Module::count());
        $this->assertSame(0, ModuleAttachment::count());
    }

    public function test_a_failed_upload_on_edit_changes_nothing(): void
    {
        $this->useSupabase();
        $this->createModuleWithFile('keep.pdf');
        $module = Module::firstOrFail();
        $kept = $module->attachments()->firstOrFail();
        $this->failUploads();

        $this->actingAs($this->instructor)->put("/classes/{$this->class->id}/modules/{$module->id}", [
            'title' => 'Renamed',
            'content' => '<p>Loops</p>',
            'remove_attachments' => [$kept->id],
            'attachments' => [UploadedFile::fake()->create('new.pdf', 100)],
        ])->assertSessionHasErrors(['attachments' => ModuleAttachment::UPLOAD_FAILED_MESSAGE]);

        $this->assertSame('Loops', $module->fresh()->title);
        $this->assertNotNull(ModuleAttachment::find($kept->id), 'The removal must not happen when the upload failed');
    }

    public function test_the_upload_error_is_shown_on_the_form(): void
    {
        config(['filesystems.ephemeral_local_storage' => true]);

        $this->actingAs($this->instructor)
            ->from(route('modules.create', $this->class->id))
            ->followingRedirects()
            ->post("/classes/{$this->class->id}/modules", [
                'title' => 'Loops',
                'content' => '<p>Loops</p>',
                'attachments' => [UploadedFile::fake()->create('spec.pdf', 100)],
            ])
            ->assertSee('File uploads are not set up on this server yet', false);
    }

    public function test_removing_an_attachment_and_deleting_a_module_delete_cloud_files(): void
    {
        $this->useSupabase();
        $this->createModuleWithFile('first.pdf');
        $module = Module::firstOrFail();
        $first = $module->attachments()->firstOrFail();

        $this->actingAs($this->instructor)->put("/classes/{$this->class->id}/modules/{$module->id}", [
            'title' => 'Loops',
            'content' => '<p>Loops</p>',
            'remove_attachments' => [$first->id],
            'attachments' => [UploadedFile::fake()->create('second.pdf', 100)],
        ])->assertSessionHasNoErrors();

        Storage::disk('supabase')->assertMissing($first->file_path);
        $second = $module->attachments()->firstOrFail();
        Storage::disk('supabase')->assertExists($second->file_path);

        $this->actingAs($this->instructor)->delete("/classes/{$this->class->id}/modules/{$module->id}")->assertRedirect();

        Storage::disk('supabase')->assertMissing($second->file_path);
    }
}
