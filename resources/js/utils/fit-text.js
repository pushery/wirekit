/**
 * Text a script writes into a fitted text.
 *
 * A fitted text (`<x-wirekit::fit-text>`, or a component's `fit` prop) takes its size from
 * invisible copies of itself, which the stylesheet generates from `data-wk-fit`. The server writes
 * that attribute from the text it rendered, so a text an Alpine expression writes later would be
 * set at the size of the old one, and a longer text could run past its box.
 *
 * `x-wk-fit-text="expression"` on the fitted box writes the expression's value into the visible
 * text and into every copy in one effect, so the two cannot disagree. The value is set as text,
 * never as markup, and its white space is collapsed the way the server collapses a slot's.
 */
export const FIT_ATTRIBUTE = 'data-wk-fit';

/**
 * The words of the text, one to a line, which the copies of a text over several lines count as well.
 */
export const FIT_WORDS_ATTRIBUTE = 'data-wk-fit-words';

/**
 * The text the copies measure: white space collapsed the way the browser collapses it, the ends
 * trimmed. A no-break space stays.
 *
 * @param {unknown} value
 * @returns {string}
 */
export function fitPlainText(value) {
    return String(value ?? '').replace(/[ \t\n\f\r]+/g, ' ').replace(/^ | $/g, '');
}

/**
 * The words of a text, one to a line. A no-break space joins two words into one, as it does in the
 * text.
 *
 * @param {string} text  a text as `fitPlainText()` returns it
 * @returns {string}
 */
export function fitWords(text) {
    return text.split(' ').filter((word) => word !== '').join('\n');
}

/**
 * Write a text into a fitted box: the visible text, then every copy.
 *
 * @param {Element} box  the element that carries `data-wk-fit-box`
 * @param {unknown} value
 */
export function writeFitText(box, value) {
    const text = fitPlainText(value);
    const visible = box.querySelector('[data-wk-fit-text]');

    if (visible && visible.textContent !== text) {
        visible.textContent = text;
    }

    for (const copy of [box, ...box.querySelectorAll(`[${FIT_ATTRIBUTE}]`)]) {
        if (copy.hasAttribute(FIT_ATTRIBUTE) && copy.getAttribute(FIT_ATTRIBUTE) !== text) {
            copy.setAttribute(FIT_ATTRIBUTE, text);
        }
    }

    const words = fitWords(text);
    for (const copy of box.querySelectorAll(`[${FIT_WORDS_ATTRIBUTE}]`)) {
        if (copy.hasAttribute(FIT_WORDS_ATTRIBUTE) && copy.getAttribute(FIT_WORDS_ATTRIBUTE) !== words) {
            copy.setAttribute(FIT_WORDS_ATTRIBUTE, words);
        }
    }
}

/**
 * Register the `x-wk-fit-text` directive.
 *
 * @param {object} Alpine
 */
export function registerFitTextDirective(Alpine) {
    Alpine.directive('wk-fit-text', (el, { expression }, { effect, evaluateLater }) => {
        const read = evaluateLater(expression);

        effect(() => {
            read((value) => writeFitText(el, value));
        });
    });
}
