/**
 * The element that actually scrolls a node into view, or null when the window does.
 *
 * The reading family used to scroll the WINDOW unconditionally, which works only for a page that
 * scrolls as a whole. An application that owns its scroll region — and WireKit's own
 * `<x-wirekit::main>` is one, it carries `overflow-y-auto` — got nothing: the window has nowhere to
 * go, so a jump did nothing at all and there was no error to see.
 *
 * Detected rather than configured. A `scroll-root` prop would put the burden on the developer to
 * describe their own layout to a component that can look, and the answer it would be given is
 * exactly what this walk finds.
 *
 * Both conditions matter: an ancestor may declare `overflow-y: auto` and have nothing to scroll,
 * in which case it is not the container the reader is moving through, and scrolling it would move
 * nothing while the real one stays put.
 *
 * @param {Element|null} el
 * @returns {Element|null}
 */
export function scrollRootOf(el) {
    let node = el?.parentElement ?? null;

    while (node && node !== document.body && node !== document.documentElement) {
        const overflowY = getComputedStyle(node).overflowY;

        if ((overflowY === 'auto' || overflowY === 'scroll') && node.scrollHeight > node.clientHeight) {
            return node;
        }

        node = node.parentElement;
    }

    return null;
}
