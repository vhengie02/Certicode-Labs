<?php

namespace Tests\Unit;

use App\Models\ModuleAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Downloads against the real S3 driver configured for Supabase Storage (presigning is local
 * signing, no network): a short-lived URL for exactly one object, saved under its own name.
 */
class ModuleAttachmentS3Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'filesystems.attachments_disk' => 'supabase',
            'filesystems.disks.supabase.key' => 'test-key',
            'filesystems.disks.supabase.secret' => 'test-secret',
            'filesystems.disks.supabase.region' => 'ap-northeast-2',
            'filesystems.disks.supabase.bucket' => 'attachments',
            'filesystems.disks.supabase.endpoint' => 'https://abc123.storage.supabase.co/storage/v1/s3',
        ]);
        Storage::forgetDisk('supabase');
    }

    private function signedUrlFor(string $fileName): array
    {
        $attachment = new ModuleAttachment(['file_name' => $fileName, 'file_path' => 'module_attachments/abc.pdf']);

        $response = $attachment->downloadResponse();
        $this->assertInstanceOf(RedirectResponse::class, $response);

        $url = parse_url($response->getTargetUrl());
        parse_str($url['query'] ?? '', $query);

        return [$url, $query];
    }

    public function test_download_is_a_five_minute_presigned_url_for_one_object(): void
    {
        [$url, $query] = $this->signedUrlFor('Week 1 slides.pdf');

        $this->assertSame('abc123.storage.supabase.co', $url['host']);
        $this->assertSame('/storage/v1/s3/attachments/module_attachments/abc.pdf', $url['path']);
        $this->assertSame('300', $query['X-Amz-Expires']);
        $this->assertNotEmpty($query['X-Amz-Signature']);
        $this->assertStringStartsWith('attachment;', $query['response-content-disposition']);
        $this->assertStringContainsString('Week 1 slides.pdf', $query['response-content-disposition']);
    }

    public function test_odd_file_names_still_produce_a_download(): void
    {
        foreach (['quote "Q1".pdf', "caf\u{00e9} ü.pdf", "ctrl\x10\x13.pdf", '100%.pdf', "\x10"] as $name) {
            [, $query] = $this->signedUrlFor($name);
            $this->assertStringStartsWith('attachment;', $query['response-content-disposition'], "Failed for {$name}");
        }
    }

    public function test_direct_upload_is_a_five_minute_presigned_put_a_browser_can_send(): void
    {
        $this->assertTrue(ModuleAttachment::usesDirectUploads());

        $upload = ModuleAttachment::issueDirectUpload(1, 'Week 1 slides.pdf');
        $url = parse_url($upload['url']);
        parse_str($url['query'] ?? '', $query);

        $this->assertMatchesRegularExpression('#^module_attachments/[A-Za-z0-9]{40}\.pdf$#', $upload['key']);
        $this->assertSame('abc123.storage.supabase.co', $url['host']);
        $this->assertSame('/storage/v1/s3/attachments/'.$upload['key'], $url['path']);
        $this->assertContains((int) $query['X-Amz-Expires'], [299, 300]);
        $this->assertNotEmpty($query['X-Amz-Signature']);
        // Only Host is signed and the browser sets it itself; a signed checksum or content
        // header would make every browser PUT fail
        $this->assertSame('host', $query['X-Amz-SignedHeaders']);
        $this->assertSame([], $upload['headers']);
        $this->assertEmpty(preg_grep('/checksum/i', array_keys($query)));
    }

    public function test_cloud_disk_without_region_or_keys_is_not_treated_as_usable(): void
    {
        $this->assertTrue(ModuleAttachment::storageIsDurable());

        foreach (['key', 'secret', 'region', 'bucket'] as $setting) {
            $saved = config("filesystems.disks.supabase.{$setting}");
            config(["filesystems.disks.supabase.{$setting}" => '']);
            $this->assertFalse(ModuleAttachment::storageIsDurable(), "{$setting} missing should make storage unusable");
            config(["filesystems.disks.supabase.{$setting}" => $saved]);
        }
    }
}
