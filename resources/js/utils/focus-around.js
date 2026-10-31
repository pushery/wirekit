/**
 * Where the focus goes when an element that holds it hides or leaves the page: the next control
 * after the element in document order, or the last one before it when nothing follows. Without
 * this the element takes the focus with it as it hides, and the browser drops it to the page, so
 * the next Tab starts somewhere the reader never was (WCAG 2.4.3).
 *
 * Skipped: anything inside the element itself, a disabled or invisible control, one outside the
 * tab order, and one under `inert` (a page behind an open modal).
 */
import { controlIsDisabled } from './fieldset-disabled.js';
import { FOCUSABLE } from './first-control.js';

// Node.DOCUMENT_POSITION_PRECEDING and _FOLLOWING, spelled out so the module reads the same
// where no `Node` global exists.
const PRECEDING = 2;
const FOLLOWING = 4;

/**
 * @param {Element} root the element that is about to hide, or the one around it
 * @returns {Element|null}
 */
export function focusTargetAround(root) {
    if (typeof document === 'undefined' || ! root || typeof root.compareDocumentPosition !== 'function') {
        return null;
    }

    const candidates = [...document.querySelectorAll(FOCUSABLE)].filter((el) => ! root.contains(el)
        && ! controlIsDisabled(el)
        && el.tabIndex >= 0
        && el.getClientRects().length > 0
        && ! el.closest('[inert]'));

    const after = candidates.find((el) => root.compareDocumentPosition(el) & FOLLOWING);

    return after ?? candidates.filter((el) => root.compareDocumentPosition(el) & PRECEDING).pop() ?? null;
}

export default focusTargetAround;
