<?php

namespace App\Support;

/**
 * Webcam snapshots sent with camera anomalies. They are kept in the anomaly's
 * metadata (never in public storage) and served only to the class's instructor.
 */
class WebcamSnapshot
{
    public const MAX_BYTES = 300 * 1024;

    private const MIME_BY_TYPE = [
        IMAGETYPE_JPEG => 'image/jpeg',
        IMAGETYPE_PNG => 'image/png',
    ];

    /**
     * Validate a base64 image (with or without a data: prefix) and return it as a
     * data URI, or null when it is missing, too large, or not a real JPEG/PNG.
     */
    public static function normalize(mixed $input): ?string
    {
        $decoded = self::decode($input);

        return $decoded ? 'data:'.$decoded['mime'].';base64,'.base64_encode($decoded['bytes']) : null;
    }

    /**
     * @return array{bytes: string, mime: string}|null
     */
    public static function decode(mixed $input): ?array
    {
        if (! is_string($input) || $input === '') {
            return null;
        }

        if (str_starts_with($input, 'data:')) {
            if (! preg_match('/^data:image\/(jpeg|jpg|png);base64,/i', $input)) {
                return null;
            }
            $input = substr($input, strpos($input, ',') + 1);
        }

        // Base64 is 4/3 the size of the bytes it encodes; reject oversize input before decoding it.
        if (strlen($input) > (int) ceil(self::MAX_BYTES * 4 / 3) + 4) {
            return null;
        }

        $bytes = base64_decode($input, true);
        if ($bytes === false || $bytes === '' || strlen($bytes) > self::MAX_BYTES) {
            return null;
        }

        $info = @getimagesizefromstring($bytes);
        $mime = $info ? (self::MIME_BY_TYPE[$info[2]] ?? null) : null;
        if (! $mime || $info[0] < 1 || $info[1] < 1 || $info[0] > 4096 || $info[1] > 4096) {
            return null;
        }

        return ['bytes' => $bytes, 'mime' => $mime];
    }
}
