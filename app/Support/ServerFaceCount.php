<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A second count of the faces in a passing camera-check picture, made on the server by the
 * `api/face-count.js` Vercel function (Laravel's PHP runtime can't decode images), to compare
 * with the count the browser reported. Off unless `services.face_count.enabled` is set.
 *
 * It catches a browser that reports one face when the picture shows nobody or two people: a
 * hand-made request, or a lab page edited to always say 1. It can't catch a real photo of the
 * student held up to the camera; that picture has a face in it.
 *
 * A disagreement flags the attempt for the instructor instead of failing the check. The server
 * sees one 640 px JPEG, while the browser looked at the live video (and on some browsers asked
 * the system's own face detector), so an honest student in a dim room can get 1 in the browser
 * and 0 here. Failing them would use up an attempt and can lock the lab for 15 minutes.
 *
 * When the function can't answer (timeout, error, not set up), the check goes ahead as it did
 * before this existed and is recorded as unverified: a lab must not become impossible to start
 * because a second opinion is down, and failing closed would treat an outage more harshly than
 * a detected mismatch.
 */
final class ServerFaceCount
{
    public const AGREED = 'agreed';

    public const DISAGREED = 'disagreed';

    public const UNAVAILABLE = 'unavailable';

    public static function enabled(): bool
    {
        return (bool) config('services.face_count.enabled');
    }

    /**
     * Count the faces in the picture and compare with the browser's count. Null when the
     * feature is off; otherwise what to keep with the check: `server_check` (one of the
     * constants above) and `server_face_count` (null when unavailable).
     *
     * @return array{server_check: string, server_face_count: int|null}|null
     */
    public static function check(CameraCheckFrame $frame, int $browserCount): ?array
    {
        if (! self::enabled()) {
            return null;
        }

        $serverCount = self::count($frame);
        if ($serverCount === null) {
            return ['server_check' => self::UNAVAILABLE, 'server_face_count' => null];
        }

        return [
            'server_check' => $serverCount === $browserCount ? self::AGREED : self::DISAGREED,
            'server_face_count' => $serverCount,
        ];
    }

    /** The function's face count for the picture, or null when it gave no usable answer. */
    private static function count(CameraCheckFrame $frame): ?int
    {
        $url = config('services.face_count.url');
        $secret = config('services.face_count.secret');
        if (! $url || ! $secret) {
            Log::warning('Server face count is on but FACE_COUNT_URL or FACE_COUNT_SECRET is not set; camera check left unverified.');

            return null;
        }

        $timeout = max(1, (float) config('services.face_count.timeout', 10));

        try {
            $response = Http::withToken($secret)
                ->acceptJson()
                ->connectTimeout(min(3, $timeout))
                ->timeout($timeout)
                ->withBody((string) $frame->bytes, 'image/jpeg')
                ->post($url);
        } catch (Throwable $e) {
            Log::warning('Server face count did not answer; camera check left unverified: '.$e->getMessage());

            return null;
        }

        $faceCount = $response->json('face_count');
        if (! $response->successful() || ! is_int($faceCount) || $faceCount < 0) {
            Log::warning('Server face count failed with HTTP '.$response->status().'; camera check left unverified.', [
                'error' => $response->json('error'),
            ]);

            return null;
        }

        return $faceCount;
    }
}
