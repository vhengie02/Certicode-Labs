<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

class ModuleAttachment extends Model
{
    use HasFactory;

    public const NOT_DURABLE_MESSAGE = 'File uploads are not set up on this server yet, so attachments would be lost. Ask an admin to connect file storage (ATTACHMENTS_DISK).';

    public const UPLOAD_FAILED_MESSAGE = 'The files could not be uploaded right now, so nothing was saved. Please try again.';

    protected $fillable = [
        'module_id',
        'file_name',
        'file_path',
        'file_size',
    ];

    /**
     * Get the module that owns the attachment.
     */
    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * The disk attachment files live on (filesystems.attachments_disk): Supabase Storage in
     * production, the local public disk in development.
     */
    public static function diskName(): string
    {
        return (string) config('filesystems.attachments_disk', 'public');
    }

    public static function disk(): FilesystemAdapter
    {
        return Storage::disk(static::diskName());
    }

    /**
     * False when uploads would be lost or could not work: a throwaway local disk (a Vercel
     * instance's /tmp), or a cloud disk missing its settings. Callers then refuse the upload.
     */
    public static function storageIsDurable(): bool
    {
        $config = config('filesystems.disks.' . static::diskName());
        if (!is_array($config)) {
            return false;
        }

        $driver = $config['driver'] ?? null;
        if ($driver === 'local') {
            return !config('filesystems.ephemeral_local_storage');
        }

        if ($driver === 's3') {
            foreach (['key', 'secret', 'region', 'bucket'] as $setting) {
                if (empty($config[$setting])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Upload files before anything is written to the database, so a failed upload leaves no
     * half-saved module. Returns attachment attributes for createMany(). If one file fails,
     * the ones already uploaded are removed and the exception is rethrown.
     *
     * @param  array<int, UploadedFile>  $files
     */
    public static function uploadAll(array $files): array
    {
        $uploaded = [];

        try {
            foreach ($files as $file) {
                $path = $file->store('module_attachments', static::diskName());
                if ($path === false) {
                    throw new \RuntimeException('Could not store attachment ' . $file->getClientOriginalName());
                }

                $uploaded[] = [
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_size' => $file->getSize(),
                ];
            }
        } catch (\Throwable $e) {
            static::discardUploads($uploaded);
            throw $e;
        }

        return $uploaded;
    }

    /**
     * Remove files uploaded by uploadAll() whose database rows were never saved.
     */
    public static function discardUploads(array $uploaded): void
    {
        foreach ($uploaded as $attributes) {
            static::deletePath($attributes['file_path']);
        }
    }

    public function deleteFile(): void
    {
        static::deletePath($this->file_path);
    }

    /**
     * A file that fails to delete only leaves an orphan in storage, so report it and carry on
     * rather than failing a request whose database changes are already done.
     */
    private static function deletePath(string $path): void
    {
        try {
            static::disk()->delete($path);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * The download response. Cloud disks hand out a short-lived signed URL, so the file never
     * passes through the PHP function (Vercel caps responses at 4.5 MB); local disks stream it.
     */
    public function downloadResponse()
    {
        $disk = static::disk();
        $fileName = str_replace(['/', '\\'], '_', (string) $this->file_name);
        $isLocal = config('filesystems.disks.' . static::diskName() . '.driver') === 'local';

        if (!$isLocal && $disk->providesTemporaryUrls()) {
            // The ASCII fallback must be printable: Str::ascii keeps some control bytes, which
            // would make makeDisposition throw and the download fail
            $fallback = str_replace(['%', '"'], '', preg_replace('/[^\x20-\x7e]/', '', Str::ascii($fileName))) ?: 'attachment';

            return redirect()->away($disk->temporaryUrl($this->file_path, now()->addMinutes(5), [
                'ResponseContentDisposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $fileName, $fallback),
            ]));
        }

        return $disk->download($this->file_path, $fileName);
    }
}
