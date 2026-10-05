/**
 * Where a key press lands, for shortcuts made of one character key.
 *
 * A shortcut of one character without a modifier steps aside wherever the reader is typing, so the
 * character arrives (WCAG 2.1.4). A text field, a textarea, a select (where a letter picks an
 * option) and any editable element take typed text; a checkbox, a radio, a button or a range
 * types nothing, so a shortcut still works from there.
 */

const NOT_TEXT = new Set(['checkbox', 'radio', 'button', 'submit', 'reset', 'range', 'color', 'file', 'image', 'hidden']);

/**
 * Whether a key press goes into a place that takes typed text.
 *
 * Read from the first element of the event's path, so a field inside a shadow root counts as the
 * field and not as its host.
 *
 * @param {KeyboardEvent} event
 * @returns {boolean}
 */
export function typesText(event) {
    const path = typeof event?.composedPath === 'function' ? event.composedPath() : [];
    const target = path[0] ?? event?.target ?? null;

    if (! target || target.nodeType !== 1) {
        return false;
    }

    if (target.isContentEditable) {
        return true;
    }

    const tag = String(target.tagName || '').toLowerCase();

    if (tag === 'textarea' || tag === 'select') {
        return true;
    }

    if (tag !== 'input') {
        return false;
    }

    return ! NOT_TEXT.has(String(target.type || 'text').toLowerCase());
}
