import openCvModule from '@techstark/opencv-js';

/**
 * Hands out the OpenCV module from behind a function. The module has a "then" method of
 * its own, and importing it directly would have the browser treat it as a promise and
 * call that method again and again, which freezes the page.
 */
export function openCvRuntime(): any {
    return openCvModule;
}
