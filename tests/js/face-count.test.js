// Tests the lab page's "multiple faces" logic. The page keeps its script inline in a Blade view,
// so these tests read countFaces() and applyProctorResult() out of that file and run them as is.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

const source = readFileSync(new URL('../../resources/views/laboratories/show.blade.php', import.meta.url), 'utf8');

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

const methods = [
    method('countFaces', '        async applyProctorResult('),
    method('async applyProctorResult', '        // 40x30 luminance values'),
].join(',\n');

function labPage() {
    const page = new Function(`return { ${methods} };`)();
    return Object.assign(page, {
        minFaceWidthRatio: setting('minFaceWidthRatio'),
        confidentFaceScore: setting('confidentFaceScore'),
        multiFaceAlertGapMs: setting('multiFaceAlertGapMs'),
        $refs: { videoEl: { videoWidth: 640 } },
        absenceCount: 0,
        recentMultiFace: [],
        lastMultiFaceAlertAt: 0,
        lastAbsenceAlertAt: 0,
        lowLightingDetected: false,
        recentMovement: [],
        flags: [],
        captureSnapshot: () => 'data:image/jpeg;base64,x',
        async logTelemetry(eventType, payload) {
            this.flags.push({ eventType, faceCount: payload.face_count, second: (Date.now() - START) / 1000 });
        },
    });
}

const face = (x, y, size, score) => ({ box: { x, y, width: size, height: size }, score, imageWidth: 640 });
const student = face(200, 100, 200, 0.95);

test('counts each person sitting at the camera', () => {
    const page = labPage();
    assert.equal(page.countFaces([]), 0);
    assert.equal(page.countFaces([student]), 1);
    assert.equal(page.countFaces([face(80, 120, 180, 0.95), face(360, 120, 170, 0.9)]), 2);
    assert.equal(page.countFaces([face(20, 120, 170, 0.9), face(230, 120, 180, 0.95), face(440, 120, 160, 0.8)]), 3);
});

test('counts faces from the browser FaceDetector, which gives no scores', () => {
    const page = labPage();
    const native = (x) => ({ boundingBox: { x, y: 120, width: 180, height: 180 } });
    assert.equal(page.countFaces([native(80), native(360)]), 2);
});

test('ignores a second box, a background poster and an unsure shadow', () => {
    const page = labPage();
    assert.equal(page.countFaces([student, face(215, 110, 180, 0.7)]), 1, 'second box on the same face');
    assert.equal(page.countFaces([student, face(500, 40, 50, 0.9)]), 1, 'small face far behind');
    assert.equal(page.countFaces([student, face(450, 100, 150, 0.3)]), 1, 'unsure box');
});

test('a large unsure box does not turn the student into a second person', () => {
    const page = labPage();
    assert.equal(page.countFaces([face(300, 60, 300, 0.16), face(60, 150, 170, 0.95)]), 1);
});

test('a large unsure box beside two real people still counts two', () => {
    const page = labPage();
    assert.equal(page.countFaces([face(330, 0, 300, 0.16), face(20, 150, 150, 0.95), face(180, 150, 140, 0.9)]), 2);
});

test('a lone unsure face still counts as the student, for dim rooms', () => {
    const page = labPage();
    assert.equal(page.countFaces([face(300, 60, 300, 0.16)]), 1);
});

// Feeds one face count per check. Checks run 10 seconds apart, give or take `jitterMs`.
const START = 1_700_000_000_000;
async function runChecks(faceCounts, jitterMs = 0) {
    const page = labPage();
    const realNow = Date.now;
    try {
        for (const [i, faces] of faceCounts.entries()) {
            const jitter = jitterMs * (i % 2 === 0 ? -1 : 1);
            Date.now = () => START + i * 10000 + jitter;
            await page.applyProctorResult({}, faces, false, false);
        }
    } finally {
        Date.now = realNow;
    }
    return page;
}

test('a passer-by in one check raises no flag', async () => {
    const page = await runChecks([1, 2, 1, 1, 1]);
    assert.deepEqual(page.flags, []);
    assert.equal(page.proctorStatus, 'normal');
});

test('a second person who stays is flagged on the second check', async () => {
    const page = await runChecks([1, 2, 2]);
    assert.deepEqual(page.flags, [{ eventType: 'webcam_check', faceCount: 2, second: 20 }]);
    assert.equal(page.proctorStatus, 'multiple_faces');
});

test('a second person seen every other check is still flagged', async () => {
    const page = await runChecks([2, 1, 2, 1, 2, 1]);
    assert.equal(page.flags.length, 1);
    assert.equal(page.flags[0].second, 20);
});

test('two sightings more than three checks apart raise no flag', async () => {
    const page = await runChecks([2, 1, 1, 2, 1, 1]);
    assert.deepEqual(page.flags, []);
});

test('a second person who stays is flagged again every 30 seconds', async () => {
    const page = await runChecks([1, 2, 2, 2, 2, 2, 2, 2, 2]);
    assert.deepEqual(page.flags.map((flag) => flag.second), [20, 50, 80]);
});

test('checks that run a little early or late keep the 30 second pace', async () => {
    const page = await runChecks([1, 2, 2, 2, 2, 2, 2, 2, 2], 400);
    assert.equal(page.flags.length, 3);
});
