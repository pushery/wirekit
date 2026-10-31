// One segmenter for every text: building one per call would repeat the locale lookup.
let segmenter = null;

/**
 * The graphemes of a text, each what a reader sees as one character: an emoji with its modifiers
 * or its joined parts, a flag, a letter with its accent. Without `Intl.Segmenter` the code points,
 * which still never cut a character outside the Basic Multilingual Plane in two.
 *
 * @param {unknown} text
 * @returns {string[]}
 */
export function graphemesOf(text) {
    const value = String(text ?? '');

    if (segmenter === null && typeof Intl !== 'undefined' && typeof Intl.Segmenter === 'function') {
        segmenter = new Intl.Segmenter(undefined, { granularity: 'grapheme' });
    }

    return segmenter ? Array.from(segmenter.segment(value), (part) => part.segment) : Array.from(value);
}

/**
 * A text cut to at most `max` graphemes, with `ellipsis` after it where something was cut.
 *
 * @param {unknown} text
 * @param {number} max
 * @param {string} [ellipsis]
 * @returns {string}
 */
export function cutGraphemes(text, max, ellipsis = '') {
    const graphemes = graphemesOf(text);

    return graphemes.length > max ? graphemes.slice(0, max).join('') + ellipsis : graphemes.join('');
}
