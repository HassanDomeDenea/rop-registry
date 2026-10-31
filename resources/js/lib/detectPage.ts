import type { Point, Quad } from '@/lib/scanImage';
import { createCanvas, isConvexQuad } from '@/lib/scanImage';

/**
 * Finds the sheet of paper in a photograph, for the attachment editor. The photos are
 * taken on a busy desk, so a corner may be hidden under another document or the page may
 * run out of the picture. That is why this looks for the straight edges of the page and
 * intersects them, instead of expecting four visible corners.
 *
 * OpenCV is about 11 MB, so it is only fetched the first time someone asks for detection.
 */

type Line = { rho: number; theta: number };

type Candidate = {
    corners: [Point, Point, Point, Point];
    support: number;
    area: number;
};

/** Detection works on a copy of this size; a page's outline needs no more detail. */
const WORKING_EDGE = 500;

/** Saturation above this is not paper, however bright it is. */
const PALE_SATURATION = 80;

/** A picture this full of paper is already a scan with no background to remove. */
const ALREADY_A_SCAN = 0.92;

const TOO_LITTLE_PAPER = 0.1;

const MINIMUM_AREA = 0.2;

/** The lines kept for each of the two directions of the page's sides. */
const LINES_PER_DIRECTION = 14;

const FINALISTS = 300;

/** How far outside the picture a hidden corner may fall, as a share of its size. */
const CORNER_OVERHANG = 0.25;

/**
 * An edge along the border of the picture has no line in the photo to confirm it. It counts
 * for this much when paper runs along all of it, as it does when a page leaves the picture.
 */
const BORDER_EDGE_SUPPORT = 0.6;

const SIDE_SAMPLES = 40;

/** How far in from a side, in working pixels, paper is looked for. */
const INSIDE_DISTANCE = 7;

/** How much of the inside of a page has to look like paper. */
const MINIMUM_COVERAGE = 0.8;

const MINIMUM_SUPPORT = 0.45;

let openCv: Promise<{ cv: any }> | null = null;

/** The module has a "then" method, so it has to travel wrapped in an object. */
function loadOpenCv(): Promise<{ cv: any }> {
    openCv ??= import('@/lib/openCvRuntime')
        .then(async ({ openCvRuntime }) => {
            const module = openCvRuntime();

            if (!module.Mat) {
                await new Promise<void>((resolve) => {
                    module.onRuntimeInitialized = () => resolve();
                });
            }

            return { cv: module };
        })
        .catch((error) => {
            openCv = null;

            throw error;
        });

    return openCv;
}

/** Keep the angle of the line's normal in a single half turn around the given centre. */
function normalized({ rho, theta }: Line, centre: number): Line {
    return theta > centre + Math.PI / 2
        ? { rho: -rho, theta: theta - Math.PI }
        : { rho, theta };
}

function intersect(first: Line, second: Line): Point | null {
    const determinant =
        Math.cos(first.theta) * Math.sin(second.theta) -
        Math.sin(first.theta) * Math.cos(second.theta);

    if (Math.abs(determinant) < 1e-6) {
        return null;
    }

    return {
        x:
            (first.rho * Math.sin(second.theta) -
                second.rho * Math.sin(first.theta)) /
            determinant,
        y:
            (second.rho * Math.cos(first.theta) -
                first.rho * Math.cos(second.theta)) /
            determinant,
    };
}

/** Drop lines that repeat a stronger one; OpenCV returns the strongest first. */
function distinctLines(lines: Line[]): Line[] {
    const kept: Line[] = [];

    for (const line of lines) {
        const repeats = kept.some(
            (other) =>
                Math.abs(other.rho - line.rho) < 12 &&
                Math.abs(other.theta - line.theta) < (5 * Math.PI) / 180,
        );

        if (!repeats) {
            kept.push(line);
        }
    }

    return kept;
}

function polygonArea(corners: Point[]): number {
    return Math.abs(
        corners.reduce((total, corner, index) => {
            const next = corners[(index + 1) % corners.length];

            return total + corner.x * next.y - next.x * corner.y;
        }, 0) / 2,
    );
}

/** Whether every corner is open enough for a page seen from an angle. */
function hasPlausibleAngles(corners: Point[]): boolean {
    return corners.every((corner, index) => {
        const previous = corners[(index + 3) % 4];
        const next = corners[(index + 1) % 4];
        const angle = Math.abs(
            Math.atan2(
                (previous.x - corner.x) * (next.y - corner.y) -
                    (previous.y - corner.y) * (next.x - corner.x),
                (previous.x - corner.x) * (next.x - corner.x) +
                    (previous.y - corner.y) * (next.y - corner.y),
            ),
        );

        return angle > (50 * Math.PI) / 180 && angle < (130 * Math.PI) / 180;
    });
}

/** Build every plausible page out of two lines of each direction. */
function candidatesFrom(
    horizontal: Line[],
    vertical: Line[],
    width: number,
    height: number,
    edgeMap: Uint8Array,
    paper: Uint8Array,
): Candidate[] {
    const maximumX = width - 1;
    const maximumY = height - 1;
    const middleX = maximumX / 2;
    const middleY = maximumY / 2;

    const heightAtMiddle = ({ rho, theta }: Line) =>
        (rho - Math.cos(theta) * middleX) / Math.sin(theta);
    const widthAtMiddle = ({ rho, theta }: Line) =>
        (rho - Math.sin(theta) * middleY) / Math.cos(theta);

    const rows = [...horizontal].sort(
        (first, second) => heightAtMiddle(first) - heightAtMiddle(second),
    );
    const columns = [...vertical].sort(
        (first, second) => widthAtMiddle(first) - widthAtMiddle(second),
    );

    const isOnSameBorder = (first: Point, second: Point) =>
        (first.x < 3 && second.x < 3) ||
        (first.x > maximumX - 3 && second.x > maximumX - 3) ||
        (first.y < 3 && second.y < 3) ||
        (first.y > maximumY - 3 && second.y > maximumY - 3);

    const paperAlong = (first: Point, second: Point) => {
        let hits = 0;

        for (let sample = 0; sample < SIDE_SAMPLES; sample++) {
            const share = (sample + 0.5) / SIDE_SAMPLES;
            const x = Math.round(first.x + (second.x - first.x) * share);
            const y = Math.round(first.y + (second.y - first.y) * share);

            hits += paper[y * width + x] ? 1 : 0;
        }

        return hits / SIDE_SAMPLES;
    };

    /** A true side of the page shows as a line with paper just inside it. */
    const visibleShare = (first: Point, second: Point, centre: Point) => {
        const middle = {
            x: (first.x + second.x) / 2,
            y: (first.y + second.y) / 2,
        };
        const toCentre = Math.hypot(centre.x - middle.x, centre.y - middle.y);
        const inwardX = ((centre.x - middle.x) / toCentre) * INSIDE_DISTANCE;
        const inwardY = ((centre.y - middle.y) / toCentre) * INSIDE_DISTANCE;
        let hits = 0;

        for (let sample = 0; sample < SIDE_SAMPLES; sample++) {
            const share = (sample + 0.5) / SIDE_SAMPLES;
            const x = first.x + (second.x - first.x) * share;
            const y = first.y + (second.y - first.y) * share;
            const insideX = Math.round(
                Math.min(maximumX, Math.max(0, x + inwardX)),
            );
            const insideY = Math.round(
                Math.min(maximumY, Math.max(0, y + inwardY)),
            );

            if (
                edgeMap[Math.round(y) * width + Math.round(x)] &&
                paper[insideY * width + insideX]
            ) {
                hits++;
            }
        }

        return hits / SIDE_SAMPLES;
    };

    const isWithinReach = ({ x, y }: Point) =>
        x > -width * CORNER_OVERHANG &&
        x < width * (1 + CORNER_OVERHANG) &&
        y > -height * CORNER_OVERHANG &&
        y < height * (1 + CORNER_OVERHANG);

    const clamp = ({ x, y }: Point): Point => ({
        x: Math.min(maximumX, Math.max(0, x)),
        y: Math.min(maximumY, Math.max(0, y)),
    });

    const candidates: Candidate[] = [];
    const nearlyParallel = (35 * Math.PI) / 180;

    for (let top = 0; top < rows.length; top++) {
        for (let bottom = top + 1; bottom < rows.length; bottom++) {
            if (
                Math.abs(rows[top].theta - rows[bottom].theta) > nearlyParallel
            ) {
                continue;
            }

            for (let left = 0; left < columns.length; left++) {
                for (let right = left + 1; right < columns.length; right++) {
                    if (
                        Math.abs(columns[left].theta - columns[right].theta) >
                        nearlyParallel
                    ) {
                        continue;
                    }

                    const raw = [
                        intersect(rows[top], columns[left]),
                        intersect(rows[top], columns[right]),
                        intersect(rows[bottom], columns[right]),
                        intersect(rows[bottom], columns[left]),
                    ];

                    if (
                        raw.some((corner) => !corner || !isWithinReach(corner))
                    ) {
                        continue;
                    }

                    const corners = raw.map((corner) => clamp(corner!)) as [
                        Point,
                        Point,
                        Point,
                        Point,
                    ];

                    const area = polygonArea(corners) / (width * height);

                    if (area < MINIMUM_AREA || !hasPlausibleAngles(corners)) {
                        continue;
                    }

                    const scaled = corners.map(({ x, y }) => ({
                        x: x / maximumX,
                        y: y / maximumY,
                    })) as Quad;

                    if (!isConvexQuad(scaled)) {
                        continue;
                    }

                    const centre = {
                        x: corners.reduce((sum, { x }) => sum + x, 0) / 4,
                        y: corners.reduce((sum, { y }) => sum + y, 0) / 4,
                    };
                    let borderEdges = 0;
                    let support = 0;

                    corners.forEach((corner, index) => {
                        const next = corners[(index + 1) % 4];

                        if (isOnSameBorder(corner, next)) {
                            borderEdges++;
                            support +=
                                BORDER_EDGE_SUPPORT * paperAlong(corner, next);
                        } else {
                            support += visibleShare(corner, next, centre);
                        }
                    });

                    // A page always shows at least two of its own sides.
                    if (borderEdges <= 2) {
                        candidates.push({
                            corners,
                            support: support / 4,
                            area,
                        });
                    }
                }
            }
        }
    }

    return candidates;
}

function containsPoint(corners: Point[], x: number, y: number): boolean {
    return corners.every((corner, index) => {
        const next = corners[(index + 1) % 4];

        return (
            (next.x - corner.x) * (y - corner.y) -
                (next.y - corner.y) * (x - corner.x) >=
            0
        );
    });
}

/** The share of the inside of the shape that looks like paper. */
function paperCoverage(
    corners: Point[],
    paper: Uint8Array,
    width: number,
    height: number,
): number {
    const step = 6;
    let inside = 0;
    let onPaper = 0;

    for (let y = 0; y < height; y += step) {
        for (let x = 0; x < width; x += step) {
            if (containsPoint(corners, x, y)) {
                inside++;
                onPaper += paper[y * width + x] > 0 ? 1 : 0;
            }
        }
    }

    return inside === 0 ? 0 : onPaper / inside;
}

/** Sides that show in the photo count most; among those, the larger shape is the page. */
function outlineScore({ support, area }: Candidate): number {
    return support * support * Math.sqrt(area);
}

/**
 * Find the corners of the page in the frame, as positions from 0 to 1, or null when no
 * page stands out from a background: a flat scan, or a photograph with no paper in it.
 */
export async function detectPage(
    frame: HTMLCanvasElement,
): Promise<Quad | null> {
    const { cv } = await loadOpenCv();

    const scale = WORKING_EDGE / Math.max(frame.width, frame.height);
    const small = createCanvas(frame.width * scale, frame.height * scale);
    const context = small.getContext('2d', { willReadFrequently: true });

    context!.imageSmoothingQuality = 'high';
    context!.drawImage(frame, 0, 0, small.width, small.height);

    const { width, height } = small;
    const held: { delete: () => void }[] = [];
    const hold = <T extends { delete: () => void }>(item: T): T => {
        held.push(item);

        return item;
    };
    const mat = () => hold(new cv.Mat());

    try {
        const source = hold(cv.imread(small));
        const rgb = mat();
        const hsv = mat();
        const channels = hold(new cv.MatVector());

        cv.cvtColor(source, rgb, cv.COLOR_RGBA2RGB);
        cv.cvtColor(rgb, hsv, cv.COLOR_RGB2HSV);
        cv.split(hsv, channels);

        const saturation = hold(channels.get(1));
        const value = hold(channels.get(2));

        // Paper is what is both bright and nearly colourless. Closing with a dark-removing
        // kernel fills in the writing and printed lines, so they do not count as edges.
        const bright = mat();
        const pale = mat();
        const paper = mat();
        const textKernel = hold(
            cv.getStructuringElement(cv.MORPH_RECT, new cv.Size(9, 9)),
        );
        const crumbKernel = hold(
            cv.getStructuringElement(cv.MORPH_RECT, new cv.Size(7, 7)),
        );

        cv.threshold(value, bright, 0, 255, cv.THRESH_BINARY + cv.THRESH_OTSU);
        cv.threshold(
            saturation,
            pale,
            PALE_SATURATION,
            255,
            cv.THRESH_BINARY_INV,
        );
        cv.bitwise_and(bright, pale, paper);
        cv.morphologyEx(paper, paper, cv.MORPH_CLOSE, textKernel);
        cv.morphologyEx(paper, paper, cv.MORPH_OPEN, crumbKernel);

        const paperShare = cv.countNonZero(paper) / (width * height);

        if (paperShare >= ALREADY_A_SCAN || paperShare < TOO_LITTLE_PAPER) {
            return null;
        }

        const gray = mat();
        const photoEdges = mat();
        const paperEdges = mat();
        const edges = mat();
        const reach = mat();
        const strokeKernel = hold(
            cv.getStructuringElement(cv.MORPH_RECT, new cv.Size(3, 3)),
        );
        const widen = hold(
            cv.getStructuringElement(cv.MORPH_RECT, new cv.Size(5, 5)),
        );

        // A light closing only: a stronger one would also erase the thin shadow line that
        // often is all that separates the page from a white sheet under it.
        cv.cvtColor(source, gray, cv.COLOR_RGBA2GRAY);
        cv.morphologyEx(gray, gray, cv.MORPH_CLOSE, strokeKernel);
        cv.GaussianBlur(gray, gray, new cv.Size(3, 3), 0);
        cv.Canny(gray, photoEdges, 30, 90);
        cv.Canny(paper, paperEdges, 50, 150);
        cv.bitwise_or(photoEdges, paperEdges, edges);
        cv.dilate(edges, reach, widen);

        const found = mat();

        cv.HoughLines(
            edges,
            found,
            1,
            Math.PI / 180,
            Math.round(Math.min(width, height) * 0.16),
        );

        const lines: Line[] = [];

        for (let index = 0; index < found.rows; index++) {
            lines.push({
                rho: found.data32F[index * 2],
                theta: found.data32F[index * 2 + 1],
            });
        }

        const isRow = ({ theta }: Line) =>
            Math.abs(theta - Math.PI / 2) < Math.PI / 4;

        // The border of the picture stands in for a side that runs out of the photo.
        const horizontal = [
            ...distinctLines(lines.filter(isRow)).slice(0, LINES_PER_DIRECTION),
            { rho: 0, theta: Math.PI / 2 },
            { rho: height - 1, theta: Math.PI / 2 },
        ];
        const vertical = [
            ...distinctLines(
                lines
                    .filter((line) => !isRow(line))
                    .map((line) => normalized(line, 0)),
            ).slice(0, LINES_PER_DIRECTION),
            { rho: 0, theta: 0 },
            { rho: width - 1, theta: 0 },
        ];

        const candidates = candidatesFrom(
            horizontal,
            vertical,
            width,
            height,
            reach.data,
            paper.data,
        )
            .sort((first, second) => outlineScore(second) - outlineScore(first))
            .slice(0, FINALISTS);

        let best: { corners: Point[]; score: number } | null = null;

        for (const candidate of candidates) {
            const coverage = paperCoverage(
                candidate.corners,
                paper.data,
                width,
                height,
            );

            if (
                coverage < MINIMUM_COVERAGE ||
                candidate.support < MINIMUM_SUPPORT
            ) {
                continue;
            }

            const score = coverage * outlineScore(candidate);

            if (!best || score > best.score) {
                best = { corners: candidate.corners, score };
            }
        }

        if (!best) {
            return null;
        }

        return best.corners.map(({ x, y }) => ({
            x: x / (width - 1),
            y: y / (height - 1),
        })) as Quad;
    } finally {
        held.forEach((item) => item.delete());
    }
}
