// Picture processing the lab page does on a canvas before it looks for faces, redone on raw
// RGBA pixels: the server has the JPEG, not a canvas. Every function takes and returns
// { width, height, data } with `data` as RGBA bytes, like the canvas ImageData it replaces.

/** Below this average brightness (0-255) the lab page treats the room as dim. */
export const LOW_LIGHT_LUMINANCE = 75;

/**
 * Average brightness of every fourth pixel, as the lab page measures it.
 *
 * @param {{data: Uint8Array|Uint8ClampedArray}} image
 */
export function averageLuminance(image) {
    const data = image.data;
    let total = 0;
    for (let i = 0; i < data.length; i += 16) {
        total += 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
    }
    return total / (data.length / 16);
}

/**
 * The canvas filter `brightness(1.6) contrast(1.4)` the lab page's third detection pass draws
 * through. Each step is clamped to 0-255, as the browser does between filters.
 */
export function enhanced(image) {
    const out = new Uint8ClampedArray(image.data.length);
    const curve = new Uint8ClampedArray(256);
    for (let v = 0; v < 256; v++) {
        const bright = Math.min(255, v * 1.6);
        curve[v] = (bright - 127.5) * 1.4 + 127.5;
    }
    for (let i = 0; i < out.length; i += 4) {
        out[i] = curve[image.data[i]];
        out[i + 1] = curve[image.data[i + 1]];
        out[i + 2] = curve[image.data[i + 2]];
        out[i + 3] = 255;
    }
    return { width: image.width, height: image.height, data: out };
}

/**
 * The lab page's brightenedFrame(): a greyscale copy with its shadows lifted, then its contrast
 * evened out tile by tile (CLAHE), for the last pass in a dim room. Same steps and constants;
 * the picture is used at its own size (the lab page sends at most 640 px wide).
 */
export function brightened(image) {
    const tiles = 8;
    const clipLimit = 3;
    const { width, height } = image;
    const pixels = new Uint8ClampedArray(image.data);

    const lifted = new Uint8Array(256);
    for (let v = 0; v < 256; v++) lifted[v] = Math.round(255 * Math.sqrt(v / 255));

    const lum = new Uint8Array(width * height);
    for (let i = 0; i < lum.length; i++) {
        lum[i] = lifted[Math.round(0.299 * pixels[i * 4] + 0.587 * pixels[i * 4 + 1] + 0.114 * pixels[i * 4 + 2])];
    }

    // One tone curve per tile. The clip limit stops flat areas turning into noise.
    const tileWidth = width / tiles;
    const tileHeight = height / tiles;
    const curves = [];
    for (let ty = 0; ty < tiles; ty++) {
        for (let tx = 0; tx < tiles; tx++) {
            const histogram = new Float64Array(256);
            let count = 0;
            for (let y = Math.floor(ty * tileHeight); y < Math.floor((ty + 1) * tileHeight); y++) {
                for (let x = Math.floor(tx * tileWidth); x < Math.floor((tx + 1) * tileWidth); x++) {
                    histogram[lum[y * width + x]]++;
                    count++;
                }
            }

            const limit = clipLimit * count / 256;
            let excess = 0;
            for (let v = 0; v < 256; v++) {
                if (histogram[v] > limit) {
                    excess += histogram[v] - limit;
                    histogram[v] = limit;
                }
            }

            const curve = new Float64Array(256);
            let total = 0;
            for (let v = 0; v < 256; v++) {
                total += histogram[v] + excess / 256;
                curve[v] = 255 * total / count;
            }
            curves.push(curve);
        }
    }

    // Blend the four nearest tiles' curves so no tile edges show in the picture
    const position = (pixel, tileSize) => Math.min(tiles - 1.001, Math.max(0, pixel / tileSize - 0.5));
    for (let y = 0; y < height; y++) {
        const fy = position(y, tileHeight);
        const top = Math.floor(fy);
        const down = fy - top;
        for (let x = 0; x < width; x++) {
            const fx = position(x, tileWidth);
            const left = Math.floor(fx);
            const across = fx - left;
            const v = lum[y * width + x];
            const upper = curves[top * tiles + left][v] * (1 - across) + curves[top * tiles + left + 1][v] * across;
            const lower = curves[(top + 1) * tiles + left][v] * (1 - across) + curves[(top + 1) * tiles + left + 1][v] * across;
            const out = upper * (1 - down) + lower * down;
            const i = (y * width + x) * 4;
            pixels[i] = pixels[i + 1] = pixels[i + 2] = out;
        }
    }

    return { width, height, data: pixels };
}
