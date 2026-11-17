// Counts faces in a camera-check JPEG the way the lab page counts them on the live video:
// the same face-api models (public/models), the same detection passes in the same order, and
// the same countFaces(). Runs TensorFlow.js on its WebAssembly backend: the pure-JS CPU
// backend takes 10+ s per picture, and the native tfjs-node build is far over Vercel's
// function size limit.
import { createRequire } from 'node:module';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { countFaces } from './count-faces.js';
import { averageLuminance, brightened, enhanced, LOW_LIGHT_LUMINANCE } from './frames.js';

const require = createRequire(import.meta.url);

/** The lab page's thresholds, per pass. tests/js/face-count-server.test.js keeps them equal. */
export const SSD_MIN_CONFIDENCE = 0.15;
export const TINY_INPUT_SIZE = 416;
export const TINY_SCORE_THRESHOLD = 0.15;
export const ENHANCED_TINY_SCORE_THRESHOLD = 0.12;
export const BOOSTED_SSD_MIN_CONFIDENCE = 0.2;
export const BRIGHTENED_SSD_MIN_CONFIDENCE = 0.4;

/** Refuse pictures bigger than this before decoding them: the lab page sends 640 px wide. */
export const MAX_MEGAPIXELS = 4;

const MODELS_DIR = join(dirname(fileURLToPath(import.meta.url)), '..', '..', 'public', 'models');

let ready = null;

/** Load TensorFlow.js, its WASM backend and both models once per function instance. */
function load() {
    ready ??= (async () => {
        const tf = require('@tensorflow/tfjs');
        const wasm = require('@tensorflow/tfjs-backend-wasm');
        const faceapi = require('@vladmandic/face-api/dist/face-api.node-wasm.js');

        // The .wasm files sit beside the backend's own script; read them from disk, not a CDN
        wasm.setWasmPaths(dirname(require.resolve('@tensorflow/tfjs-backend-wasm/dist/tfjs-backend-wasm.wasm')) + '/');
        tf.env().set('PROD', true);
        await tf.setBackend('wasm');
        await tf.ready();

        await faceapi.nets.ssdMobilenetv1.loadFromDisk(MODELS_DIR);
        await faceapi.nets.tinyFaceDetector.loadFromDisk(MODELS_DIR);

        return { tf, faceapi };
    })().catch((error) => {
        ready = null; // let the next request try again
        throw error;
    });

    return ready;
}

/** Thrown for bytes that aren't a JPEG this function will decode. */
export class UnreadablePicture extends Error {}

/** Decode a JPEG to RGBA pixels, refusing anything over MAX_MEGAPIXELS. */
export function decodeJpeg(bytes) {
    const jpeg = require('jpeg-js');
    try {
        const { width, height, data } = jpeg.decode(bytes, {
            useTArray: true,
            formatAsRGBA: true,
            maxResolutionInMP: MAX_MEGAPIXELS,
            maxMemoryUsageInMB: 64,
        });
        return { width, height, data };
    } catch (error) {
        throw new UnreadablePicture(error.message);
    }
}

/**
 * @param {Buffer|Uint8Array} bytes A JPEG
 * @returns {Promise<{faceCount: number, lowLight: boolean}>}
 */
export async function countFacesInJpeg(bytes) {
    const image = decodeJpeg(bytes);
    const { tf, faceapi } = await load();

    const detect = async (picture, options) => {
        const tensor = tf.tidy(() => tf.tensor3d(picture.data, [picture.height, picture.width, 4], 'int32')
            .slice([0, 0, 0], [-1, -1, 3]));
        try {
            return await faceapi.detectAllFaces(tensor, options);
        } finally {
            tensor.dispose();
        }
    };
    const ssd = (minConfidence) => new faceapi.SsdMobilenetv1Options({ minConfidence });
    const tiny = (scoreThreshold) => new faceapi.TinyFaceDetectorOptions({ inputSize: TINY_INPUT_SIZE, scoreThreshold });
    const count = (detections) => countFaces(detections, image.width);

    const lowLight = averageLuminance(image) < LOW_LIGHT_LUMINANCE;

    // Same order as the lab page's runAiPresenceValidation(); each pass runs only when the
    // ones before it found nobody. The browser's native FaceDetector pass has no server twin.
    let faceCount = count(await detect(image, ssd(SSD_MIN_CONFIDENCE)));

    if (faceCount === 0) {
        faceCount = count(await detect(image, tiny(TINY_SCORE_THRESHOLD)));
    }

    if (faceCount === 0) {
        const lifted = enhanced(image);
        const enhancedTiny = await detect(lifted, tiny(ENHANCED_TINY_SCORE_THRESHOLD));
        if (enhancedTiny.length > 0) faceCount = count(enhancedTiny);
        if (faceCount === 0) {
            const enhancedSsd = await detect(lifted, ssd(BOOSTED_SSD_MIN_CONFIDENCE));
            if (enhancedSsd.length > 0) faceCount = count(enhancedSsd);
        }
    }

    if (faceCount === 0 && lowLight) {
        const detections = await detect(brightened(image), ssd(BRIGHTENED_SSD_MIN_CONFIDENCE));
        if (detections.length > 0) faceCount = count(detections);
    }

    return { faceCount, lowLight };
}
