// Vercel Node function: counts the faces in a pre-lab camera-check picture, so Laravel can
// compare that with the count the browser reported (App\Support\ServerFaceCount). Laravel
// runs on the PHP runtime, which can't decode images (no GD or Imagick); this runs beside it.
// Callers need FACE_COUNT_SECRET; without it set, every call is refused.
import { countFacesInJpeg, UnreadablePicture } from '../lib/face-count/detector.js';
import { createHandler } from '../lib/face-count/handler.js';

const handle = createHandler({
    secret: () => process.env.FACE_COUNT_SECRET,
    count: countFacesInJpeg,
    isUnreadable: (error) => error instanceof UnreadablePicture,
});

export default {
    fetch: handle,
};
