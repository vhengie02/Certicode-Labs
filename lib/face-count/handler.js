// The HTTP side of api/face-count.js, kept apart from the detector so tests can run it with a
// stand-in. Laravel POSTs the camera-check JPEG as the raw body with
// `Authorization: Bearer <FACE_COUNT_SECRET>` and gets back `{"face_count": n, "low_light": b}`.
import { createHash, timingSafeEqual } from 'node:crypto';

/** Larger than any picture the server keeps (WebcamSnapshot::MAX_BYTES is 300 KB). */
export const MAX_BODY_BYTES = 512 * 1024;

const json = (status, body) => new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' },
});

/** Compare secrets in constant time; hashing first makes the lengths equal. */
function sameSecret(given, expected) {
    const a = createHash('sha256').update(given).digest();
    const b = createHash('sha256').update(expected).digest();
    return timingSafeEqual(a, b);
}

/**
 * @param {object} deps
 * @param {() => (string|undefined)} deps.secret The shared secret, read per request from the environment
 * @param {(jpeg: Buffer) => Promise<{faceCount: number, lowLight: boolean}>} deps.count
 * @param {Function} deps.isUnreadable Whether an error from `count` means a bad picture
 */
export function createHandler({ secret, count, isUnreadable = () => false }) {
    return async function handle(request) {
        if (request.method !== 'POST') {
            return json(405, { error: 'method_not_allowed' });
        }

        // No secret configured means nobody may call it, never that everybody may
        const expected = secret();
        if (!expected) {
            return json(503, { error: 'not_configured' });
        }

        const header = request.headers.get('authorization') || '';
        const given = header.startsWith('Bearer ') ? header.slice(7) : '';
        if (!given || !sameSecret(given, expected)) {
            return json(401, { error: 'unauthorized' });
        }

        const declared = Number(request.headers.get('content-length') || 0);
        if (declared > MAX_BODY_BYTES) {
            return json(413, { error: 'too_large' });
        }

        const body = Buffer.from(await request.arrayBuffer());
        if (body.length > MAX_BODY_BYTES) {
            return json(413, { error: 'too_large' });
        }
        if (body.length < 3 || body[0] !== 0xFF || body[1] !== 0xD8 || body[2] !== 0xFF) {
            return json(415, { error: 'not_jpeg' });
        }

        try {
            const { faceCount, lowLight } = await count(body);
            return json(200, { face_count: faceCount, low_light: lowLight });
        } catch (error) {
            if (isUnreadable(error)) {
                return json(422, { error: 'unreadable' });
            }
            console.error('face-count failed:', error);
            return json(500, { error: 'detection_failed' });
        }
    };
}
