// Tests the picture the lab page sends with its pre-lab camera check. The server keeps a passing
// one for the instructor and refuses anything under 320x240, so the page sends the camera frame
// as a JPEG at most 640 px wide. gatePicture() is read out of the Blade view and run as is.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

const source = readFileSync(new URL('../../resources/views/laboratories/show.blade.php', import.meta.url), 'utf8');

const start = source.indexOf('        gatePicture(');
const end = source.indexOf('        async runAiPresenceValidation(', start);
assert.ok(start >= 0 && end > start, 'gatePicture() not found in the lab page');
const gatePicture = new Function(`return { ${source.slice(start, end).trim().replace(/,$/, '')} };`)().gatePicture;

function fakeCanvas(width, height) {
    return {
        width,
        height,
        drawn: null,
        getContext() {
            const canvas = this;
            return { drawImage: (from, x, y, w, h) => { canvas.drawn = { from, w, h }; } };
        },
        toDataURL(type, quality) {
            return `${type};q=${quality};${this.width}x${this.height}`;
        },
    };
}

function withDocument(run) {
    const created = [];
    globalThis.document = { createElement: () => { const c = fakeCanvas(0, 0); created.push(c); return c; } };
    try {
        return run(created);
    } finally {
        delete globalThis.document;
    }
}

test('a 640x480 frame is sent as it is, as a JPEG at quality 0.7', () => {
    withDocument((created) => {
        assert.equal(gatePicture(fakeCanvas(640, 480)), 'image/jpeg;q=0.7;640x480');
        assert.equal(created.length, 0);
    });
});

test('a full-HD frame is scaled down to 640 px wide, keeping its shape', () => {
    withDocument((created) => {
        const camera = fakeCanvas(1920, 1080);
        assert.equal(gatePicture(camera), 'image/jpeg;q=0.7;640x360');
        assert.equal(created[0].drawn.from, camera);
    });
});

test('a scaled 4:3 frame stays above the server minimum of 320x240', () => {
    withDocument(() => {
        assert.equal(gatePicture(fakeCanvas(1280, 960)), 'image/jpeg;q=0.7;640x480');
    });
});
