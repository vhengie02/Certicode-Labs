// Tests the server face counter (api/face-count.js, lib/face-count/). It must count faces the
// way the lab page does, so the parity tests run the lab page's own code, read out of its Blade
// view, next to the server copy on the same input.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { test } from 'node:test';
import { countFaces, CONFIDENT_FACE_SCORE, MIN_FACE_WIDTH_RATIO } from '../../lib/face-count/count-faces.js';
import { averageLuminance, brightened, enhanced, LOW_LIGHT_LUMINANCE } from '../../lib/face-count/frames.js';
import {
    BOOSTED_SSD_MIN_CONFIDENCE, BRIGHTENED_SSD_MIN_CONFIDENCE, countFacesInJpeg, decodeJpeg,
    ENHANCED_TINY_SCORE_THRESHOLD, SSD_MIN_CONFIDENCE, TINY_INPUT_SIZE, TINY_SCORE_THRESHOLD, UnreadablePicture,
} from '../../lib/face-count/detector.js';
import { createHandler, MAX_BODY_BYTES } from '../../lib/face-count/handler.js';

const require = createRequire(import.meta.url);
const source = readFileSync(new URL('../../resources/views/laboratories/show.blade.php', import.meta.url), 'utf8');
const fixture = (name) => readFileSync(new URL(`../fixtures/camera/${name}.jpg`, import.meta.url));

function method(name, nextMarker) {
    const start = source.indexOf(`        ${name}(`);
    const end = source.indexOf(nextMarker, start);
    assert.ok(start >= 0 && end > start, `${name}() not found in the lab page`);
    return source.slice(start, end).trim().replace(/,$/, '');
}

function setting(name) {
    const match = source.match(new RegExp(`^\\s+${name}: ([\\d.]+),`, 'm'));
    assert.ok(match, `${name} not found in the lab page`);
    return Number(match[1]);
}

// The pre-lab check's detection passes, between its luminance check and its verdict
const presenceCheck = source.slice(source.indexOf('        async runAiPresenceValidation('), source.indexOf('            this.faceCount = detectedFaces;'));

// --- Parity with the lab page --------------------------------------------------------------

test('uses the lab page thresholds for every detection pass', () => {
    assert.equal(MIN_FACE_WIDTH_RATIO, setting('minFaceWidthRatio'));
    assert.equal(CONFIDENT_FACE_SCORE, setting('confidentFaceScore'));
    assert.equal(BOOSTED_SSD_MIN_CONFIDENCE, setting('boostedSsdMinConfidence'));
    assert.equal(BRIGHTENED_SSD_MIN_CONFIDENCE, setting('brightenedSsdMinConfidence'));

    const ssdPasses = [...presenceCheck.matchAll(/SsdMobilenetv1Options\(\{ minConfidence: ([\w.]+) \}\)/g)].map((m) => m[1]);
    assert.deepEqual(ssdPasses, [String(SSD_MIN_CONFIDENCE), 'this.boostedSsdMinConfidence']);

    const tinyPasses = [...presenceCheck.matchAll(/TinyFaceDetectorOptions\(\{ inputSize: (\d+), scoreThreshold: ([\d.]+) \}\)/g)].map((m) => [Number(m[1]), Number(m[2])]);
    assert.deepEqual(tinyPasses, [[TINY_INPUT_SIZE, TINY_SCORE_THRESHOLD], [TINY_INPUT_SIZE, ENHANCED_TINY_SCORE_THRESHOLD]]);

    assert.ok(presenceCheck.includes("enhancedCtx.filter = 'brightness(1.6) contrast(1.4)'"), 'the enhanced pass filter changed');
    assert.ok(presenceCheck.includes(`this.lowLightingDetected = avgLuminance < ${LOW_LIGHT_LUMINANCE};`), 'the low-light threshold changed');
    assert.ok(presenceCheck.includes('this.lowLightingDetected) {\n                detectedFaces = await this.detectFacesBoosted(video);'), 'the dim-room pass changed');
});

test('counts faces exactly like the lab page countFaces()', () => {
    const page = new Function(`return { ${method('countFaces', '        async applyProctorResult(')} };`)();
    Object.assign(page, {
        minFaceWidthRatio: setting('minFaceWidthRatio'),
        confidentFaceScore: setting('confidentFaceScore'),
        $refs: { videoEl: { videoWidth: 640 } },
    });

    // A fixed pseudo-random stream, so a failure can be replayed
    let seed = 42;
    const random = () => (seed = (seed * 16807) % 2147483647) / 2147483647;

    const cases = [[], [{}], [{ box: { x: 0, y: 0, width: 0, height: 0 } }]];
    for (let i = 0; i < 2000; i++) {
        cases.push(Array.from({ length: Math.floor(random() * 5) }, () => ({
            box: { x: random() * 600, y: random() * 400, width: 10 + random() * 300, height: 10 + random() * 300 },
            score: random(),
            imageWidth: 640,
        })));
    }

    for (const detections of cases) {
        assert.equal(countFaces(detections, 640), page.countFaces(detections), JSON.stringify(detections));
    }
});

test('brightens a dim picture exactly like the lab page brightenedFrame()', () => {
    const width = 96;
    const height = 72;
    const rgba = new Uint8ClampedArray(width * height * 4);
    let seed = 7;
    for (let i = 0; i < rgba.length; i++) {
        seed = (seed * 16807) % 2147483647;
        rgba[i] = i % 4 === 3 ? 255 : seed % 70; // dark, noisy
    }

    // The lab page draws on a canvas; this stand-in hands it the same pixels
    let drawn = null;
    const document = {
        createElement: () => ({
            getContext: () => ({
                drawImage() {},
                getImageData: () => ({ data: new Uint8ClampedArray(rgba) }),
                putImageData(image) { drawn = image.data; },
            }),
        }),
    };
    const page = new Function('document', `return { ${method('brightenedFrame', '        // How many people a set of detections represents.')} };`)(document);
    page.brightenedFrame({ videoWidth: width, videoHeight: height });

    assert.deepEqual(brightened({ width, height, data: rgba }).data, drawn);
});

test('the enhanced pass brightens, then stretches contrast, clamped like a canvas filter', () => {
    const image = { width: 3, height: 1, data: new Uint8ClampedArray([0, 50, 100, 255, 150, 200, 255, 255, 80, 80, 80, 255]) };
    const out = enhanced(image).data;
    // 50 -> 80 -> (80 - 127.5) * 1.4 + 127.5 = 61; 100 -> 160 -> 173; 150 -> 240 -> 285 (255)
    assert.deepEqual([...out.slice(0, 3)], [0, 61, 173]);
    assert.deepEqual([...out.slice(4, 7)], [255, 255, 255]);
    // Samples every fourth pixel, as the lab page does
    const grey = new Uint8ClampedArray(16 * 4).fill(10);
    assert.equal(Math.round(averageLuminance({ data: grey })), 10);
});

// --- The HTTP handler ----------------------------------------------------------------------

const JPEG = Buffer.from([0xFF, 0xD8, 0xFF, 0xE0, 0, 0]);

function call(options = {}) {
    const { method = 'POST', auth = 'Bearer s3cret', body = JPEG, count, headers = {} } = options;
    const secret = 'secret' in options ? options.secret : 's3cret';
    const calls = [];
    const handle = createHandler({
        secret: () => secret,
        count: count ?? (async (bytes) => { calls.push(bytes); return { faceCount: 1, lowLight: false }; }),
        isUnreadable: (error) => error instanceof UnreadablePicture,
    });
    const request = new Request('https://example.test/api/face-count', {
        method,
        headers: { ...(auth ? { Authorization: auth } : {}), 'Content-Type': 'image/jpeg', ...headers },
        body: method === 'POST' ? body : undefined,
    });
    return handle(request).then(async (response) => ({ status: response.status, body: await response.json(), calls }));
}

test('counts the faces in a JPEG for a caller with the secret', async () => {
    const result = await call();
    assert.equal(result.status, 200);
    assert.deepEqual(result.body, { face_count: 1, low_light: false });
    assert.deepEqual([...result.calls[0]], [...JPEG]);
});

test('refuses everyone while no secret is configured', async () => {
    for (const secret of [undefined, '']) {
        const result = await call({ secret, auth: 'Bearer ' });
        assert.equal(result.status, 503);
        assert.equal(result.calls.length, 0);
    }
});

test('refuses a caller without the right secret', async () => {
    for (const auth of [null, 'Bearer wrong', 's3cret', 'Basic s3cret', 'Bearer s3cret2']) {
        const result = await call({ auth });
        assert.equal(result.status, 401, String(auth));
        assert.equal(result.calls.length, 0);
    }
});

test('accepts only a POST with a JPEG of reasonable size', async () => {
    assert.equal((await call({ method: 'GET' })).status, 405);
    assert.equal((await call({ body: Buffer.from('GIF89a') })).status, 415);
    assert.equal((await call({ body: Buffer.alloc(0) })).status, 415);
    const big = Buffer.concat([JPEG, Buffer.alloc(MAX_BODY_BYTES)]);
    assert.equal((await call({ body: big })).status, 413);
});

test('reports an undecodable picture and a detector failure apart', async () => {
    const unreadable = await call({ count: async () => { throw new UnreadablePicture('bad'); } });
    assert.equal(unreadable.status, 422);

    const original = console.error;
    console.error = () => {};
    try {
        const failed = await call({ count: async () => { throw new Error('wasm crashed'); } });
        assert.equal(failed.status, 500);
        assert.deepEqual(failed.body, { error: 'detection_failed' });
    } finally {
        console.error = original;
    }
});

// --- The detector on real JPEGs ------------------------------------------------------------

test('decodes the lab page picture size and refuses a huge one', () => {
    const frame = decodeJpeg(fixture('camera-frame'));
    assert.equal(frame.data.length, frame.width * frame.height * 4);

    const jpeg = require('jpeg-js');
    const huge = jpeg.encode({ width: 2600, height: 1600, data: Buffer.alloc(2600 * 1600 * 4) }, 10).data;
    assert.throws(() => decodeJpeg(huge), UnreadablePicture);
    assert.throws(() => decodeJpeg(Buffer.from([0xFF, 0xD8, 0xFF, 0x00])), UnreadablePicture);
});

test('finds no face in the faceless test pictures', { timeout: 120_000 }, async () => {
    // camera-frame.jpg is a drawn oval on a wall: it passes the PHP frame checks, but no
    // detector should see a person in it
    for (const name of ['camera-frame', 'blank-camera-frame', 'dark-camera-frame']) {
        const { faceCount } = await countFacesInJpeg(fixture(name));
        assert.equal(faceCount, 0, name);
    }
    const { lowLight } = await countFacesInJpeg(fixture('dark-camera-frame'));
    assert.equal(lowLight, true);
});

// The repository holds no photos of people. To check detection on real faces, point
// FACE_COUNT_SAMPLES at a folder of JPEGs named like `2-faces.jpg` and run `npm test`.
test('counts the faces in sample photos, when a folder of them is given', { timeout: 300_000, skip: !process.env.FACE_COUNT_SAMPLES }, async () => {
    const { readdirSync } = await import('node:fs');
    const { join } = await import('node:path');
    const dir = process.env.FACE_COUNT_SAMPLES;
    for (const name of readdirSync(dir).filter((n) => /^\d+-faces?(-.*)?\.jpe?g$/i.test(n))) {
        const { faceCount } = await countFacesInJpeg(readFileSync(join(dir, name)));
        assert.equal(faceCount, Number(name.split('-')[0]), name);
    }
});
