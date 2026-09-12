/**
 * Send the reader's focus to a heading they have just jumped to.
 *
 * A native in-page anchor moves TWO things: the scroll position and the sequential-navigation
 * starting point. Every component here calls `preventDefault()` on the click so it can do the
 * scroll itself — smooth, offset for a sticky header, inside the right scroll container — and
 * so it inherits responsibility for the second half.
 *
 * None of them did it. Focus stayed on the link that was clicked, so the next Tab went to the
 * NEXT link in the navigation instead of into the section the reader had just asked for. A
 * keyboard reader jumping to "Installation" landed there visually and, on their next keypress,
 * was back in the table of contents.
 *
 * `tabindex="-1"`, only when the element does not already carry one: a heading is not focusable
 * by default, and `-1` makes it programmatically focusable without adding a tab stop the reader
 * has to pass through on every future Tab. This is the long-standing skip-link pattern.
 *
 * `preventScroll: true` because the caller has already scrolled, usually smoothly. Letting the
 * browser scroll again would jump past the animation to the element's default position and
 * undo the offset the caller applied for its sticky header.
 *
 * @param {Element|null} target The heading, or null — a caller that could not resolve one
 *   should not have to guard the call.
 * @returns {boolean} Whether focus was moved, so a caller can tell "did nothing" from "worked".
 */
export function focusHeading(target) {
    if (! target || typeof target.focus !== 'function') {
        return false;
    }

    if (typeof target.hasAttribute === 'function' && ! target.hasAttribute('tabindex')) {
        target.setAttribute('tabindex', '-1');
    }

    target.focus({ preventScroll: true });

    return true;
}
