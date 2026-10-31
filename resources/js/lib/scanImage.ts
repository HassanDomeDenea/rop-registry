/**
 * The picture processing of the attachment editor: rotating, flattening a photographed
 * page to a rectangle, and scanner-like corrections. Everything runs on canvases in the
 * browser, so the same steps serve the live preview and the full size result.
 */

/** A position inside the rotated picture, from 0 to 1 on both axes. */
export type Point = { x: number; y: number };

/** The corners of the page, in the order top-left, top-right, bottom-right, bottom-left. */
export type Quad = [Point, Point, Point, Point];

export type ColorMode = 'color' | 'gray' | 'bw';

export type Adjustments = {
    /** 0 to 100: how far the paper is evened out and turned white. */
    whiten: number;
    /** -100 to 100. */
    brightness: number;
    /** -100 to 100. */
    contrast: number;
    mode: ColorMode;
};

/** The background is estimated on a grid of this many cells along the longer side. */
const BACKGROUND_CELLS = 64;

/** Luminance at which a pixel turns white in black and white mode. */
const BLACK_AND_WHITE_THRESHOLD = 165;

export function fullQuad(): Quad {
    return [
        { x: 0, y: 0 },
        { x: 1, y: 0 },
        { x: 1, y: 1 },
        { x: 0, y: 1 },
    ];
}

/** The same corners after the picture is turned a quarter clockwise. */
export function turnQuadClockwise(quad: Quad): Quad {
    const turn = ({ x, y }: Point): Point => ({ x: 1 - y, y: x });

    return [turn(quad[3]), turn(quad[0]), turn(quad[1]), turn(quad[2])];
}

/** The same corners after the picture is turned a quarter counterclockwise. */
export function turnQuadCounterclockwise(quad: Quad): Quad {
    const turn = ({ x, y }: Point): Point => ({ x: y, y: 1 - x });

    return [turn(quad[1]), turn(quad[2]), turn(quad[3]), turn(quad[0])];
}

/** Determine whether the corners still enclose a page: no crossing or folded edges. */
export function isConvexQuad(quad: Quad): boolean {
    return quad.every((point, index) => {
        const next = quad[(index + 1) % 4];
        const after = quad[(index + 2) % 4];

        const cross =
            (next.x - point.x) * (after.y - next.y) -
            (next.y - point.y) * (after.x - next.x);

        return cross > 0.0005;
    });
}

export function createCanvas(width: number, height: number): HTMLCanvasElement {
    const canvas = document.createElement('canvas');

    canvas.width = Math.max(1, Math.round(width));
    canvas.height = Math.max(1, Math.round(height));

    return canvas;
}

function contextOf(canvas: HTMLCanvasElement): CanvasRenderingContext2D {
    const context = canvas.getContext('2d', { willReadFrequently: true });

    if (!context) {
        throw new Error('The browser could not provide a drawing surface.');
    }

    return context;
}

/**
 * Draw the picture turned by the given angle. The canvas grows to hold the whole picture,
 * so nothing is cut off; the corners that open up are filled with white.
 */
export function renderFrame(
    source: CanvasImageSource,
    width: number,
    height: number,
    degrees: number,
    maxEdge: number,
): HTMLCanvasElement {
    const radians = (degrees * Math.PI) / 180;
    const cos = Math.abs(Math.round(Math.cos(radians) * 1e6) / 1e6);
    const sin = Math.abs(Math.round(Math.sin(radians) * 1e6) / 1e6);
    const frameWidth = width * cos + height * sin;
    const frameHeight = width * sin + height * cos;
    const scale = Math.min(1, maxEdge / Math.max(frameWidth, frameHeight));

    const canvas = createCanvas(frameWidth * scale, frameHeight * scale);
    const context = contextOf(canvas);

    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.imageSmoothingQuality = 'high';
    context.translate(canvas.width / 2, canvas.height / 2);
    context.rotate(radians);
    context.scale(scale, scale);
    context.drawImage(source, -width / 2, -height / 2, width, height);

    return canvas;
}

/** Cut the corners out of the frame and straighten them to a rectangle. */
export function flatten(
    frame: HTMLCanvasElement,
    quad: Quad,
): HTMLCanvasElement {
    const [p0, p1, p2, p3] = quad.map(({ x, y }) => ({
        x: x * frame.width,
        y: y * frame.height,
    }));

    const distance = (a: Point, b: Point) => Math.hypot(a.x - b.x, a.y - b.y);

    const output = createCanvas(
        Math.max(distance(p0, p1), distance(p3, p2)),
        Math.max(distance(p0, p3), distance(p1, p2)),
    );
    const outputContext = contextOf(output);

    const isRectangle =
        Math.abs(p0.y - p1.y) < 0.5 &&
        Math.abs(p3.y - p2.y) < 0.5 &&
        Math.abs(p0.x - p3.x) < 0.5 &&
        Math.abs(p1.x - p2.x) < 0.5;

    if (isRectangle) {
        outputContext.imageSmoothingQuality = 'high';
        outputContext.drawImage(
            frame,
            p0.x,
            p0.y,
            p1.x - p0.x,
            p3.y - p0.y,
            0,
            0,
            output.width,
            output.height,
        );

        return output;
    }

    // The projective transform from the unit square to the four corners (Heckbert, 1989).
    const dx1 = p1.x - p2.x;
    const dx2 = p3.x - p2.x;
    const dy1 = p1.y - p2.y;
    const dy2 = p3.y - p2.y;
    const sx = p0.x - p1.x + p2.x - p3.x;
    const sy = p0.y - p1.y + p2.y - p3.y;
    const denominator = dx1 * dy2 - dx2 * dy1;
    const g = (sx * dy2 - dx2 * sy) / denominator;
    const h = (dx1 * sy - sx * dy1) / denominator;
    const a = p1.x - p0.x + g * p1.x;
    const b = p3.x - p0.x + h * p3.x;
    const d = p1.y - p0.y + g * p1.y;
    const e = p3.y - p0.y + h * p3.y;

    const frameWidth = frame.width;
    const frameHeight = frame.height;
    const source = contextOf(frame).getImageData(
        0,
        0,
        frameWidth,
        frameHeight,
    ).data;
    const image = outputContext.createImageData(output.width, output.height);
    const target = image.data;

    let index = 0;

    for (let row = 0; row < output.height; row++) {
        const v = (row + 0.5) / output.height;

        for (let column = 0; column < output.width; column++) {
            const u = (column + 0.5) / output.width;
            const w = g * u + h * v + 1;
            const x = Math.min(
                frameWidth - 1,
                Math.max(0, (a * u + b * v + p0.x) / w - 0.5),
            );
            const y = Math.min(
                frameHeight - 1,
                Math.max(0, (d * u + e * v + p0.y) / w - 0.5),
            );

            const left = Math.floor(x);
            const top = Math.floor(y);
            const right = Math.min(left + 1, frameWidth - 1);
            const bottom = Math.min(top + 1, frameHeight - 1);
            const fx = x - left;
            const fy = y - top;

            const topLeft = (top * frameWidth + left) * 4;
            const topRight = (top * frameWidth + right) * 4;
            const bottomLeft = (bottom * frameWidth + left) * 4;
            const bottomRight = (bottom * frameWidth + right) * 4;

            for (let channel = 0; channel < 3; channel++) {
                const upper =
                    source[topLeft + channel] * (1 - fx) +
                    source[topRight + channel] * fx;
                const lower =
                    source[bottomLeft + channel] * (1 - fx) +
                    source[bottomRight + channel] * fx;

                target[index + channel] = upper * (1 - fy) + lower * fy;
            }

            target[index + 3] = 255;
            index += 4;
        }
    }

    outputContext.putImageData(image, 0, 0);

    return output;
}

/** Soften a grid of red, green and blue values with a box of the given radius. */
function blurGrid(
    values: Float32Array,
    columns: number,
    rows: number,
    radius: number,
): Float32Array {
    const blurred = new Float32Array(values.length);

    for (let row = 0; row < rows; row++) {
        for (let column = 0; column < columns; column++) {
            const totals = [0, 0, 0];
            let count = 0;

            for (
                let y = Math.max(0, row - radius);
                y <= Math.min(rows - 1, row + radius);
                y++
            ) {
                for (
                    let x = Math.max(0, column - radius);
                    x <= Math.min(columns - 1, column + radius);
                    x++
                ) {
                    const cell = (y * columns + x) * 3;

                    totals[0] += values[cell];
                    totals[1] += values[cell + 1];
                    totals[2] += values[cell + 2];
                    count++;
                }
            }

            const cell = (row * columns + column) * 3;

            blurred[cell] = totals[0] / count;
            blurred[cell + 1] = totals[1] / count;
            blurred[cell + 2] = totals[2] / count;
        }
    }

    return blurred;
}

/**
 * Estimate the colour of the bare paper across the page, shadows included. Each cell keeps
 * its brightest values, which drops the writing, thin as it is against the size of a cell.
 */
function estimateBackground(canvas: HTMLCanvasElement): {
    columns: number;
    rows: number;
    values: Float32Array;
} {
    const longest = Math.max(canvas.width, canvas.height);
    const columns = Math.max(
        1,
        Math.round((canvas.width / longest) * BACKGROUND_CELLS),
    );
    const rows = Math.max(
        1,
        Math.round((canvas.height / longest) * BACKGROUND_CELLS),
    );
    const samplesPerCell = 4;

    const small = createCanvas(columns * samplesPerCell, rows * samplesPerCell);
    const context = contextOf(small);

    context.imageSmoothingQuality = 'high';
    context.drawImage(canvas, 0, 0, small.width, small.height);

    const samples = context.getImageData(0, 0, small.width, small.height).data;
    const peaks = new Float32Array(columns * rows * 3);

    for (let y = 0; y < small.height; y++) {
        for (let x = 0; x < small.width; x++) {
            const sample = (y * small.width + x) * 4;
            const cell =
                (Math.floor(y / samplesPerCell) * columns +
                    Math.floor(x / samplesPerCell)) *
                3;

            for (let channel = 0; channel < 3; channel++) {
                peaks[cell + channel] = Math.max(
                    peaks[cell + channel],
                    samples[sample + channel],
                );
            }
        }
    }

    // Large dark areas, such as a photograph on the page, are not paper in shadow. Holding
    // the estimate above a floor keeps them from being lightened out of recognition.
    for (let channel = 0; channel < 3; channel++) {
        const sorted = Array.from(
            { length: columns * rows },
            (_, cell) => peaks[cell * 3 + channel],
        ).sort((first, second) => first - second);
        const floor = Math.max(1, sorted[Math.floor(sorted.length / 2)] * 0.45);

        for (let cell = 0; cell < columns * rows; cell++) {
            peaks[cell * 3 + channel] = Math.max(
                peaks[cell * 3 + channel],
                floor,
            );
        }
    }

    return {
        columns,
        rows,
        values: blurGrid(blurGrid(peaks, columns, rows, 2), columns, rows, 2),
    };
}

/** The brightness and contrast curve, as a table from input value to output value. */
function toneCurve(brightness: number, contrast: number): Uint8ClampedArray {
    const amount = contrast * 1.6;
    const factor = (259 * (amount + 255)) / (255 * (259 - amount));
    const offset = brightness * 1.275;
    const curve = new Uint8ClampedArray(256);

    for (let value = 0; value < 256; value++) {
        curve[value] = factor * (value - 128) + 128 + offset;
    }

    return curve;
}

/** Apply the corrections to the canvas in place. */
export function adjust(
    canvas: HTMLCanvasElement,
    { whiten, brightness, contrast, mode }: Adjustments,
): void {
    if (
        whiten === 0 &&
        brightness === 0 &&
        contrast === 0 &&
        mode === 'color'
    ) {
        return;
    }

    const { width, height } = canvas;
    const context = contextOf(canvas);
    const image = context.getImageData(0, 0, width, height);
    const data = image.data;
    const curve = toneCurve(brightness, contrast);
    const strength = whiten / 100;

    // Paper that is nearly white after evening out is carried the rest of the way.
    const gain = 255 * (1 + 0.12 * strength);
    const background = strength > 0 ? estimateBackground(canvas) : null;

    const leftCell = new Int32Array(width);
    const rightCell = new Int32Array(width);
    const rightShare = new Float32Array(width);

    if (background) {
        for (let x = 0; x < width; x++) {
            const position = Math.min(
                background.columns - 1,
                Math.max(0, ((x + 0.5) / width) * background.columns - 0.5),
            );

            leftCell[x] = Math.floor(position);
            rightCell[x] = Math.min(leftCell[x] + 1, background.columns - 1);
            rightShare[x] = position - leftCell[x];
        }
    }

    let index = 0;

    for (let y = 0; y < height; y++) {
        let topRow = 0;
        let bottomRow = 0;
        let bottomShare = 0;

        if (background) {
            const position = Math.min(
                background.rows - 1,
                Math.max(0, ((y + 0.5) / height) * background.rows - 0.5),
            );

            topRow = Math.floor(position);
            bottomRow = Math.min(topRow + 1, background.rows - 1);
            bottomShare = position - topRow;
        }

        for (let x = 0; x < width; x++) {
            let red = data[index];
            let green = data[index + 1];
            let blue = data[index + 2];

            if (background) {
                const { columns, values } = background;
                const a = (topRow * columns + leftCell[x]) * 3;
                const b = (topRow * columns + rightCell[x]) * 3;
                const c = (bottomRow * columns + leftCell[x]) * 3;
                const d = (bottomRow * columns + rightCell[x]) * 3;
                const across = rightShare[x];

                const paper = (channel: number) =>
                    (values[a + channel] * (1 - across) +
                        values[b + channel] * across) *
                        (1 - bottomShare) +
                    (values[c + channel] * (1 - across) +
                        values[d + channel] * across) *
                        bottomShare;

                red += ((red * gain) / paper(0) - red) * strength;
                green += ((green * gain) / paper(1) - green) * strength;
                blue += ((blue * gain) / paper(2) - blue) * strength;
            }

            red = curve[red > 255 ? 255 : red | 0];
            green = curve[green > 255 ? 255 : green | 0];
            blue = curve[blue > 255 ? 255 : blue | 0];

            if (mode !== 'color') {
                const luminance = 0.299 * red + 0.587 * green + 0.114 * blue;
                const shade =
                    mode === 'gray'
                        ? luminance
                        : luminance >= BLACK_AND_WHITE_THRESHOLD
                          ? 255
                          : 0;

                red = green = blue = shade;
            }

            data[index] = red;
            data[index + 1] = green;
            data[index + 2] = blue;
            index += 4;
        }
    }

    context.putImageData(image, 0, 0);
}

export function canvasToBlob(
    canvas: HTMLCanvasElement,
    type: string,
    quality: number,
): Promise<Blob> {
    return new Promise((resolve, reject) => {
        canvas.toBlob(
            (blob) =>
                blob
                    ? resolve(blob)
                    : reject(new Error('The picture could not be encoded.')),
            type,
            quality,
        );
    });
}
