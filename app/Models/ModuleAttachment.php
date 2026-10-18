<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\HeaderUtils;

class ModuleAttachment extends Model
{
    use HasFactory;

    public const NOT_DURABLE_MESSAGE = 'File uploads are not set up on this server yet, so attachments would be lost. Ask an admin to connect file storage (ATTACHMENTS_DISK).';

    public const UPLOAD_FAILED_MESSAGE = 'The files could not be uploaded right now, so nothing was saved. Please try again.';

    public const UPLOAD_MISSING_MESSAGE = 'An uploaded file could not be found, so nothing was saved. Please choose it again.';

    /** Largest attachment, in kilobytes (the form promises 20 MB) */
    public const MAX_SIZE_KB = 20480;

    public const DIRECTORY = 'module_attachments';

    /** Keys issueDirectUpload() makes: the directory, 40 random characters, an optional extension */
    private const DIRECT_UPLOAD_KEY_PATTERN = '#^module_attachments/[A-Za-z0-9]{40}(\.[a-z0-9]{1,10})?$#D';

    private const ISSUED_UPLOADS_SESSION_KEY = 'module_attachment_uploads';

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
                $path = $file->store(static::DIRECTORY, static::diskName());
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

    /**
     * True when the browser uploads files straight to the cloud disk with a signed PUT URL.
     * Files then never pass through the PHP function, whose request body Vercel caps at
     * 4.5 MB. Local disks (development) keep the multipart upload. Checked by driver: the S3
     * adapter signs upload URLs but its providesTemporaryUploadUrls() says it can't.
     */
    public static function usesDirectUploads(): bool
    {
        return config('filesystems.disks.' . static::diskName() . '.driver') === 's3'
            && static::storageIsDurable();
    }

    /**
     * A signed URL the browser can PUT one new file to for the next 5 minutes. The server
     * picks the random key, and remembers it so the module form can only attach files this
     * session uploaded for this class.
     *
     * @return array{key: string, url: string, headers: array<string, string>}
     */
    public static function issueDirectUpload(int $classId, string $fileName): array
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $key = static::DIRECTORY . '/' . Str::random(40) . (preg_match('/^[a-z0-9]{1,10}$/', $extension) ? ".{$extension}" : '');

        $signed = static::disk()->temporaryUploadUrl($key, now()->addMinutes(5));

        // A key is used within minutes or never, so only the latest few are kept
        $issued = session(self::ISSUED_UPLOADS_SESSION_KEY, []);
        $issued[$key] = $classId;
        session()->put(self::ISSUED_UPLOADS_SESSION_KEY, array_slice($issued, -50, null, true));

        // The browser sets Host itself and refuses scripts that try
        $headers = [];
        foreach ($signed['headers'] ?? [] as $name => $value) {
            if (strcasecmp($name, 'Host') !== 0) {
                $headers[$name] = is_array($value) ? implode(', ', $value) : (string) $value;
            }
        }

        return ['key' => $key, 'url' => $signed['url'], 'headers' => $headers];
    }

    /**
     * Attachment attributes for files the browser uploaded with issueDirectUpload(), checked
     * before anything is written to the database. Each key must have been issued to this
     * session for this class and not be attached yet, and its object must exist and be within
     * the size limit. A forged key is refused without touching the object it names.
     *
     * @param  array<int, array{key: string, name: string}>  $uploads
     *
     * @throws ValidationException
     */
    public static function claimDirectUploads(int $classId, array $uploads): array
    {
        if ($uploads === []) {
            return [];
        }

        $issued = session(self::ISSUED_UPLOADS_SESSION_KEY, []);
        $keys = array_column($uploads, 'key');
        foreach ($keys as $key) {
            if (!preg_match(self::DIRECT_UPLOAD_KEY_PATTERN, $key) || (int) ($issued[$key] ?? 0) !== $classId) {
                throw ValidationException::withMessages(['attachments' => static::UPLOAD_MISSING_MESSAGE]);
            }
        }

        // Two rows sharing one object would lose it when either is removed
        if (static::whereIn('file_path', $keys)->exists()) {
            throw ValidationException::withMessages(['attachments' => static::UPLOAD_MISSING_MESSAGE]);
        }

        $disk = static::disk();
        $attributes = [];
        foreach ($uploads as $upload) {
            if (!$disk->exists($upload['key'])) {
                throw ValidationException::withMessages(['attachments' => static::UPLOAD_MISSING_MESSAGE]);
            }

            $size = $disk->size($upload['key']);
            if ($size > static::MAX_SIZE_KB * 1024) {
                static::deletePath($upload['key']);
                static::forgetDirectUploads([$upload['key']]);
                throw ValidationException::withMessages(['attachments' => "“{$upload['name']}” is larger than 20 MB, so nothing was saved."]);
            }

            $attributes[] = [
                'file_name' => $upload['name'],
                'file_path' => $upload['key'],
                'file_size' => $size,
            ];
        }

        return $attributes;
    }

    /**
     * Stop accepting these keys from the form, once their rows are saved.
     */
    public static function forgetDirectUploads(array $keys): void
    {
        $issued = session(self::ISSUED_UPLOADS_SESSION_KEY, []);
        session()->put(self::ISSUED_UPLOADS_SESSION_KEY, array_diff_key($issued, array_flip($keys)));
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
