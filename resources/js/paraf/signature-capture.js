import SignaturePad from 'signature_pad';

export const INK_COLORS = { black: '#111827', blue: '#1d3fb8' };
export const SIGNATURE_FONTS = ['Dancing Script', 'Caveat', 'Sacramento', 'Great Vibes'];

/**
 * Crops transparent margins so the signature fills its box when stamped on the PDF.
 */
export function trimCanvas(source, padding = 8) {
    const context = source.getContext('2d', { willReadFrequently: true });
    const { width, height } = source;
    const data = context.getImageData(0, 0, width, height).data;
    let top = height;
    let left = width;
    let right = -1;
    let bottom = -1;

    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            if (data[(y * width + x) * 4 + 3] > 8) {
                if (x < left) left = x;
                if (x > right) right = x;
                if (y < top) top = y;
                if (y > bottom) bottom = y;
            }
        }
    }

    if (right < 0) return null;

    left = Math.max(0, left - padding);
    top = Math.max(0, top - padding);
    right = Math.min(width - 1, right + padding);
    bottom = Math.min(height - 1, bottom + padding);

    const output = document.createElement('canvas');
    output.width = right - left + 1;
    output.height = bottom - top + 1;
    output.getContext('2d').drawImage(source, left, top, output.width, output.height, 0, 0, output.width, output.height);

    return output;
}

function downscale(canvas, maxSide = 1200) {
    const ratio = Math.min(1, maxSide / Math.max(canvas.width, canvas.height));
    if (ratio === 1) return canvas;
    const output = document.createElement('canvas');
    output.width = Math.round(canvas.width * ratio);
    output.height = Math.round(canvas.height * ratio);
    output.getContext('2d').drawImage(canvas, 0, 0, output.width, output.height);
    return output;
}

export function createPad(canvas, color) {
    const pad = new SignaturePad(canvas, {
        minWidth: 0.9,
        maxWidth: 2.8,
        velocityFilterWeight: 0.7,
        throttle: 8,
        penColor: color,
        backgroundColor: 'rgba(0,0,0,0)',
    });
    resizePad(canvas, pad);
    return pad;
}

export function resizePad(canvas, pad) {
    const ratio = Math.max(window.devicePixelRatio || 1, 1);
    const data = pad.toData();
    canvas.width = canvas.offsetWidth * ratio;
    canvas.height = canvas.offsetHeight * ratio;
    canvas.getContext('2d').scale(ratio, ratio);
    pad.clear();
    if (data.length) pad.fromData(data);
}

export function padToPng(canvas, pad) {
    if (pad.isEmpty()) return null;
    const trimmed = trimCanvas(canvas);
    return trimmed ? downscale(trimmed).toDataURL('image/png') : null;
}

export async function typedToPng(text, font, color) {
    const value = text.trim();
    if (!value) return null;

    await document.fonts.load(`96px "${font}"`, value);

    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d');
    context.font = `96px "${font}"`;
    const metrics = context.measureText(value);
    canvas.width = Math.ceil(metrics.width) + 80;
    canvas.height = 200;
    context.font = `96px "${font}"`;
    context.fillStyle = color;
    context.textBaseline = 'middle';
    context.fillText(value, 40, 100);

    const trimmed = trimCanvas(canvas);
    return trimmed ? downscale(trimmed).toDataURL('image/png') : null;
}

/**
 * Photo of a signature on paper → transparent PNG: grayscale, stretch the contrast between
 * paper and ink, then turn the paper (bright pixels) transparent.
 */
export async function photoToPng(file, color) {
    const bitmap = await createImageBitmap(file);
    const ratio = Math.min(1, 1400 / Math.max(bitmap.width, bitmap.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * ratio);
    canvas.height = Math.round(bitmap.height * ratio);
    const context = canvas.getContext('2d', { willReadFrequently: true });
    context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close?.();

    const image = context.getImageData(0, 0, canvas.width, canvas.height);
    const pixels = image.data;
    const histogram = new Uint32Array(256);
    const luminance = new Uint8ClampedArray(pixels.length / 4);

    for (let i = 0, p = 0; i < pixels.length; i += 4, p++) {
        const alpha = pixels[i + 3] / 255;
        const lum = (0.299 * pixels[i] + 0.587 * pixels[i + 1] + 0.114 * pixels[i + 2]) * alpha + 255 * (1 - alpha);
        luminance[p] = lum;
        histogram[luminance[p]]++;
    }

    const percentile = (fraction) => {
        const target = luminance.length * fraction;
        let sum = 0;
        for (let value = 0; value < 256; value++) {
            sum += histogram[value];
            if (sum >= target) return value;
        }
        return 255;
    };

    const ink = percentile(0.02);
    const paper = percentile(0.6);
    const range = Math.max(paper - ink, 24);
    const [r, g, b] = [1, 3, 5].map((offset) => parseInt(color.slice(offset, offset + 2), 16));

    for (let i = 0, p = 0; i < pixels.length; i += 4, p++) {
        const normalized = Math.min(Math.max((luminance[p] - ink) / range, 0), 1);
        const darkness = 1 - normalized;
        pixels[i] = r;
        pixels[i + 1] = g;
        pixels[i + 2] = b;
        pixels[i + 3] = darkness < 0.18 ? 0 : Math.round(Math.min(1, darkness * 1.35) * 255);
    }

    context.putImageData(image, 0, 0);
    const trimmed = trimCanvas(canvas);
    return trimmed ? downscale(trimmed).toDataURL('image/png') : null;
}
