// How many people a set of face detections represents. A copy of countFaces() and its settings
// from the lab page (resources/views/laboratories/show.blade.php), so the server counts the
// same way the browser does. tests/js/face-count-server.test.js runs both on the same input
// and fails if they drift apart.

/** Narrower than this share of the frame is background clutter, not a student at their screen. */
export const MIN_FACE_WIDTH_RATIO = 0.08;

/** A face scoring at least this is one the detector is sure about. */
export const CONFIDENT_FACE_SCORE = 0.5;

/**
 * @param {Array<{box?: {x: number, y: number, width: number, height: number}, score?: number, imageWidth?: number}>} detections
 * @param {number} frameWidth Width of the picture the detections came from
 */
export function countFaces(detections, frameWidth) {
    const faces = (detections || []).map((d) => {
        const box = d.box || d.boundingBox || d.detection?.box || {};
        return {
            score: d.score ?? d.classScore ?? d.detection?.score ?? 1,
            x: box.x ?? 0, y: box.y ?? 0, w: box.width ?? 0, h: box.height ?? 0,
            frameWidth: d.imageWidth ?? d.detection?.imageWidth ?? frameWidth,
        };
    }).filter((f) => f.w > 0 && f.h > 0);

    if (faces.length === 0) return (detections || []).length > 0 ? 1 : 0;

    faces.sort((a, b) => b.w * b.h - a.w * a.h);
    // A large unsure box, such as a shadow on the wall, must not push the real student into
    // second place, where they would count as a second person
    const main = faces.find((f) => f.score >= CONFIDENT_FACE_SCORE) ?? faces[0];
    if (main.w < main.frameWidth * MIN_FACE_WIDTH_RATIO) return 0;

    const overlap = (a, b) => {
        const ix = Math.max(0, Math.min(a.x + a.w, b.x + b.w) - Math.max(a.x, b.x));
        const iy = Math.max(0, Math.min(a.y + a.h, b.y + b.h) - Math.max(a.y, b.y));
        return (ix * iy) / Math.min(a.w * a.h, b.w * b.h);
    };

    const others = faces.filter((f) =>
        f !== main
        && f.score >= CONFIDENT_FACE_SCORE
        && f.w * f.h >= main.w * main.h * 0.2
        && overlap(main, f) < 0.3
    );

    return 1 + others.length;
}
