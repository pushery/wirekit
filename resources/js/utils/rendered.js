/**
 * Whether an element is drawn: it has at least one box.
 *
 * The menus collect their entries by role and `aria-disabled`, and a list built that way also
 * holds entries hidden with `x-show` or `hidden`. An arrow key landing on one of those calls
 * `focus()` on an element that cannot take focus, focus stays where it was, and the reader is
 * stuck above it. An element with `display: none`, or inside something that has it, has no box,
 * so asking for boxes answers "can this be focused and seen" in every engine the package
 * supports, without the newer `checkVisibility()`.
 *
 * @param {Element} el
 * @returns {boolean}
 */
export function isRendered(el) {
    return !! el && typeof el.getClientRects === 'function' && el.getClientRects().length > 0;
}
