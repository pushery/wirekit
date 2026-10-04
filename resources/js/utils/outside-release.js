/**
 * Close a panel when a press that began outside it ends outside it.
 *
 * ## When it closes
 *
 * On the release, never on the press (WCAG 2.5.2, Pointer Cancellation). A reader who presses
 * beside an open menu and slides back onto it before letting go keeps the menu, and the
 * platform's own light dismiss for `popover` and `<dialog closedby="any">` decides the same
 * way: it closes on `pointerup`, and only when the press both began and ended outside.
 *
 * A press that ends inside closes nothing, and neither does a touch the browser turned into a
 * scroll: that one ends in `pointercancel`, with no `pointerup`.
 *
 * ## Where the release is read
 *
 * From its coordinates, not from `event.target`. A touch captures its pointer to the element it
 * began on, so the target of its `pointerup` is where the press started, wherever the finger is
 * lifted. A release over no element at all, outside the window, counts as outside.
 *
 * ## Why the document, in the capture phase
 *
 * The panels this serves are teleported out of their component's root, so a Blade
 * `x-on:click.outside` on the root would read a click inside the panel as outside. The caller's
 * `contains` names both halves, the root and the open panel.
 *
 * @param {Document} doc - The document to listen on
 * @param {Object} options
 * @param {(node: Node) => boolean} options.contains - Whether a node is inside the component or its open panel
 * @param {() => boolean} options.isOpen - Whether a panel is open
 * @param {() => void} options.close - Closes it
 * @param {() => void} [options.keep] - Runs when a press that began outside did not close the
 *     panel: it ended inside, or the browser canceled it. A caller that let something go on the
 *     press takes it back here.
 * @returns {() => void} Removes the listeners
 */
export function closeOnOutsideRelease(doc, { contains, isOpen, close, keep = () => {} }) {
    let pressedOutside = false;

    // An element, or nothing. The guard is for the `Node` check a test environment lacks.
    const isNode = (node) => node != null && (typeof Node === 'undefined' || node instanceof Node);
    const outside = (node) => ! isNode(node) || ! contains(node);

    const onDown = (event) => {
        pressedOutside = isOpen() && isNode(event.target) && ! contains(event.target);
    };

    const onUp = (event) => {
        const began = pressedOutside;
        pressedOutside = false;

        if (! began || ! isOpen()) return;

        const at = typeof doc.elementFromPoint === 'function'
            ? doc.elementFromPoint(event.clientX, event.clientY)
            : event.target;

        if (outside(at)) {
            close();

            return;
        }

        keep();
    };

    const onCancel = () => {
        const began = pressedOutside;
        pressedOutside = false;

        if (began && isOpen()) keep();
    };

    doc.addEventListener('pointerdown', onDown, { capture: true });
    doc.addEventListener('pointerup', onUp, { capture: true });
    doc.addEventListener('pointercancel', onCancel, { capture: true });

    return () => {
        doc.removeEventListener('pointerdown', onDown, { capture: true });
        doc.removeEventListener('pointerup', onUp, { capture: true });
        doc.removeEventListener('pointercancel', onCancel, { capture: true });
    };
}
