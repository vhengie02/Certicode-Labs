<?php

namespace App\Support;

/**
 * The picture sent with a passing pre-lab camera check. Faces are counted in the browser, so
 * `face_count` is only the browser's claim; the server can't decode pixels (the Vercel PHP
 * runtime has no GD or Imagick), but it can refuse a picture the lab page could not have taken.
 * The lab page draws a webcam frame on a canvas and sends it as a JPEG, which means:
 *
 * - it is a JPEG at least 320x240 (no webcam is smaller), in a webcam's shape;
 * - it has no Exif block (a canvas never writes one; a phone or camera photo does);
 * - it is not blank: a JPEG of an empty frame compresses to almost nothing, and a webcam
 *   frame with a face in it never does.
 *
 * This stops a hand-made request with a placeholder picture. It cannot stop someone sending a
 * real photo of a face, so every pass keeps its picture for the class instructor, and a
 * picture close to blank flags the attempt for a look.
 */
final class CameraCheckFrame
{
    public const MIN_LONG_SIDE = 320;

    public const MIN_SHORT_SIDE = 240;

    /** Widest shape accepted, long side over short side: covers 21:9 webcams, either way up. */
    public const MAX_ASPECT_RATIO = 2.4;

    /**
     * Size of a blank picture from the browser's JPEG encoder at the lab page's quality (0.7):
     * about 620 bytes of headers plus 0.0063 bytes per pixel (measured in Chrome: a flat
     * 320x240 frame is 1.2 KB, 640x480 is 2.6 KB, 1280x720 is 6.2 KB).
     */
    private const BLANK_HEADER_BYTES = 620;

    private const BLANK_BYTES_PER_PIXEL = 0.0065;

    /** Below this many times the size of a blank picture, the picture is refused. */
    public const BLANK_REJECT_FACTOR = 1.3;

    /**
     * Below this many times, the check passes but the attempt is flagged. A real dark room can
     * land here (a smooth, noiseless dim frame), so it is the instructor's call, not a refusal.
     */
    public const BLANK_FLAG_FACTOR = 2.0;

    /** Note on a passing picture that is close to blank. */
    public const NEARLY_BLANK = 'nearly_blank';

    /**
     * @param  list<string>  $notes  Reasons to show the instructor the picture, for a passing one
     */
    private function __construct(
        public readonly ?string $bytes,
        public readonly ?string $problem,
        public readonly array $notes = [],
    ) {}

    /**
     * Inspect the `image_base64` of a camera check. `problem` is `unreadable` when it isn't an
     * image at all, a short reason when it is one the lab page can't have taken, or null.
     */
    public static function inspect(mixed $input): self
    {
        $decoded = WebcamSnapshot::decode($input);
        if (! $decoded) {
            return new self(null, 'unreadable');
        }

        $bytes = $decoded['bytes'];
        if ($decoded['mime'] !== 'image/jpeg') {
            return new self($bytes, 'not_jpeg');
        }

        [$width, $height] = getimagesizefromstring($bytes);
        $long = max($width, $height);
        $short = min($width, $height);
        if ($long < self::MIN_LONG_SIDE || $short < self::MIN_SHORT_SIDE) {
            return new self($bytes, 'too_small');
        }
        if ($long / $short > self::MAX_ASPECT_RATIO) {
            return new self($bytes, 'odd_shape');
        }

        if (self::hasExif($bytes)) {
            return new self($bytes, 'photo_metadata');
        }

        $blankBytes = self::BLANK_HEADER_BYTES + self::BLANK_BYTES_PER_PIXEL * $width * $height;
        if (strlen($bytes) < self::BLANK_REJECT_FACTOR * $blankBytes) {
            return new self($bytes, 'blank');
        }

        return new self($bytes, null, strlen($bytes) < self::BLANK_FLAG_FACTOR * $blankBytes ? [self::NEARLY_BLANK] : []);
    }

    public function passes(): bool
    {
        return $this->problem === null;
    }

    public function isUnreadable(): bool
    {
        return $this->problem === 'unreadable';
    }

    /** The picture as the data URI that snapshots are stored as. */
    public function dataUri(): string
    {
        return 'data:image/jpeg;base64,'.base64_encode((string) $this->bytes);
    }

    /** Fingerprint of the exact bytes, to spot the same picture sent for a second check. */
    public function sha256(): string
    {
        return hash('sha256', (string) $this->bytes);
    }

    /**
     * Whether the JPEG carries an Exif block (an APP1 segment starting `Exif\0\0`). Walks the
     * segments before the image data; anything malformed counts as no Exif, since the picture
     * already decoded as a JPEG.
     */
    private static function hasExif(string $bytes): bool
    {
        $offset = 2; // after the SOI marker
        $length = strlen($bytes);

        while ($offset + 4 <= $length && $bytes[$offset] === "\xFF") {
            $marker = ord($bytes[$offset + 1]);
            if ($marker === 0xDA || $marker === 0xD9) { // start of scan / end of image
                return false;
            }

            $segmentLength = unpack('n', substr($bytes, $offset + 2, 2))[1];
            if ($marker === 0xE1 && substr($bytes, $offset + 4, 6) === "Exif\0\0") {
                return true;
            }

            $offset += 2 + $segmentLength;
        }

        return false;
    }
}
