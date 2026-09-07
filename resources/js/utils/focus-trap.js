/**
 * Focus trap wrapper for WireKit overlay components.
 *
 * Wraps the focus-trap package with WireKit-specific defaults.
 * Used by Modal and Drawer to trap keyboard focus inside the overlay.
 */
import { createFocusTrap as createFocusTrapLib } from 'focus-trap';

/**
 * Create a focus trap for an overlay container.
 *
 * @param {HTMLElement} container - The overlay container element
 * @param {Object} options - Focus trap options
 * @param {Function} options.onDeactivate - Called when trap deactivates (e.g. ESC press)
 * @param {boolean} options.escapeDeactivates - Whether ESC key deactivates the trap
 * @param {boolean} options.clickOutsideDeactivates - Whether clicking outside deactivates
 * @returns {Object} Focus trap instance with activate/deactivate methods
 */
export function createFocusTrap(container, {
    onDeactivate = () => {},
    escapeDeactivates = true,
    clickOutsideDeactivates = false,
    allowOutsideClick = false,
    initialFocus = undefined,
    setReturnFocus = undefined,
} = {}) {
    const opts = {
        // Whether clicking outside deactivates the trap entirely
        clickOutsideDeactivates,
        // Whether clicks outside are allowed to pass through (without deactivating).
        // CRITICAL for modals/drawers — lets backdrop click handlers fire.
        allowOutsideClick,
        // ESC handling — controlled by dismissible prop
        escapeDeactivates,
        // Callback when trap is deactivated (ESC or programmatic)
        onDeactivate,
        // Return focus to the element that was focused before trap activation
        returnFocusOnDeactivate: true,
        // Prevent scroll jump when activating trap
        preventScroll: true,
        // Fallback focus to the container itself if no focusable elements inside.
        //
        // ⚠️ A FUNCTION, AND THE `tabindex` IT WRITES IS THE WHOLE POINT. focus-trap's
        // own README states the precondition next to this option: *"Make sure the
        // fallback element has a negative `tabindex` so it can be programmatically
        // focused."* Handing it the bare node did not meet that — and not one of the
        // panels this library passes carries a `tabindex`: modal, drawer,
        // alert-dialog, popover, command-palette and lightbox are all zero.
        //
        // Nothing failed loudly, which is why it survived. A truthy fallback
        // satisfies the library's "must have at least one tabbable node" check, so
        // no error is thrown; the fallback then resolves to a `<div>` and
        // `node.focus()` on a div without a tabindex is a NO-OP. The trap reports
        // itself active while `document.activeElement` is still `<body>`, and every
        // subsequent Tab is `preventDefault()`ed and handed to that same node — so
        // focus does not move at all.
        //
        // Reachable from a shipped docs preview: the popover placement demo opens
        // four panels whose entire content is plain text. `app-shell.blade.php`
        // measured exactly this in a browser and fixed it locally with its own
        // conditional `:tabindex`; the shared helper is where it belongs.
        //
        // Lazy on purpose. The library only resolves this option once its tabbable
        // set comes up empty (`state.tabbableGroups.length <= 0 && !getNodeForOption(…)`
        // short-circuits), so a panel with real controls is never touched — and
        // `-1` is focusable but NOT tabbable, so the fallback stays the fallback
        // instead of becoming the first tab stop.
        fallbackFocus: () => {
            if (container
                && typeof container.hasAttribute === 'function'
                && typeof container.setAttribute === 'function'
                && ! container.hasAttribute('tabindex')
            ) {
                container.setAttribute('tabindex', '-1');
            }

            return container;
        },
    };

    // Optional initial focus override (e.g. command palette search input,
    // alert-dialog's Cancel button).
    if (initialFocus !== undefined) {
        opts.initialFocus = initialFocus;
    }

    // Optional return-focus override. `returnFocusOnDeactivate` above sends focus
    // back to whatever was focused before the trap opened — but that element may
    // no longer BE in the document: a destructive confirmation typically removes
    // the very row that held its own trigger, and a detached node cannot take
    // focus, so the browser drops it on <body>. A `setReturnFocus` function gets
    // the previously-focused element and can name a survivor instead.
    if (setReturnFocus !== undefined) {
        opts.setReturnFocus = setReturnFocus;
    }

    return createFocusTrapLib(container, opts);
}
