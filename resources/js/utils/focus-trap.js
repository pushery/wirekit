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
        // Fallback focus to the container itself if no focusable elements inside
        fallbackFocus: container,
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
