/**
 * Focus trap wrapper for WireKit overlay components.
 *
 * Wraps the focus-trap package with WireKit-specific defaults.
 * Used by Modal and Drawer to trap keyboard focus inside the overlay.
 *
 * A panel that a component inside the trapped element teleports to the overlay root, such as a
 * dropdown menu or a combobox list, belongs to the trap as well: it is taken in as a container
 * the moment focus or a press lands in it.
 */
import { createFocusTrap as createFocusTrapLib } from 'focus-trap';
import { belongsThroughTeleports, teleportedRootOf } from './teleport.js';

// Re-exported: the trap is where the adoption of teleported panels lives, and callers that
// reason about it import these two from here.
export { belongsThroughTeleports, teleportedRootOf };

/**
 * Whether an Escape still belongs to the trap.
 *
 * An element inside the trap that already acted on the key says so with `preventDefault()`: a
 * menu that closed, a list that folded, a card that was being dragged. That press is spent, and
 * closing the dialog around it as well would lose everything typed into the dialog. focus-trap
 * reads Escape in the bubble phase so that the element's own handler has run first, and asks
 * `escapeDeactivates` with the event for exactly this check.
 *
 * @param {boolean|Function} setting - The caller's `escapeDeactivates`.
 * @returns {boolean|Function} What focus-trap receives: `false` stays `false`.
 */
export function escapeUnlessHandled(setting) {
    if (typeof setting === 'function') {
        return (event) => ! event?.defaultPrevented && setting(event) !== false;
    }

    return setting ? (event) => ! event?.defaultPrevented : false;
}

/**
 * The traps that are active right now, each with the teleported panels it has taken in beside its
 * own container.
 *
 * @type {Set<{ trap: Object|null, container: HTMLElement, extras: Set<Element> }>}
 */
const liveTraps = new Set();

/**
 * Take the teleported panel a focus or a press landed in into every active trap its trigger sits
 * in.
 *
 * A dropdown, a combobox list or a filter popover inside a modal is teleported to the overlay
 * root, beside the modal's panel rather than inside it. A trap with the panel as its only
 * container treats every focus there as an escape and pulls it back, and a trap that allows no
 * press outside swallows every click there. This runs on the window in the capture phase, ahead
 * of the trap's own listeners on the document, so the panel is a container of the trap by the
 * time they ask. Panels that have left the page are dropped on the way.
 *
 * @param {Node|null|undefined} target
 * @param {Iterable<{ trap: Object|null, container: HTMLElement, extras: Set<Element> }>} [traps]
 */
export function adoptTeleportedTarget(target, traps = liveTraps) {
    const root = teleportedRootOf(target);

    if (! root) {
        return;
    }

    for (const entry of traps) {
        const trap = entry.trap;

        if (! trap || ! trap.active || trap.paused) continue;
        if (entry.container.contains(target) || entry.extras.has(root)) continue;
        if (! belongsThroughTeleports(root._x_teleportBack, entry.container)) continue;

        for (const extra of entry.extras) {
            if (! extra.isConnected) {
                entry.extras.delete(extra);
            }
        }

        entry.extras.add(root);

        try {
            trap.updateContainerElements([entry.container, ...entry.extras]);
        } catch {
            // The library refuses some container sets, such as a positive tabindex once there is
            // more than one container. The panel stays out, and the trap keeps the set it had.
            entry.extras.delete(root);
            trap.updateContainerElements([entry.container, ...entry.extras]);
        }
    }
}

/**
 * Whether a key event started inside a panel the trap has taken in.
 *
 * A Tab pressed there is the panel's own. A menu closes on it and hands the focus back to its
 * trigger, and a popover keeps its own edges. The library's Tab handling works on the tabbable
 * nodes of each container, and a menu whose items are reached with the arrow keys has none, so
 * it would pick a destination in a group that does not exist and throw. A Tab that would then
 * leave the dialog from the trigger is wrapped by `wrapTabLeavingTheTrap()`.
 *
 * @param {Set<Element>} extras
 * @param {Event} event
 * @returns {boolean}
 */
export function startsInAdoptedPanel(extras, event) {
    const target = typeof event?.composedPath === 'function' ? event.composedPath()[0] : event?.target;

    for (const extra of extras) {
        if (target && extra.contains(target)) {
            return true;
        }
    }

    return false;
}

function isTab(event) {
    return event?.key === 'Tab' || event?.keyCode === 9;
}

/** What can take a Tab, before the checks on the element itself. */
const TAB_STOP = 'a[href], area[href], button, input, select, textarea, iframe, summary, audio[controls], video[controls], [contenteditable], [tabindex]';

/**
 * The elements of a container a Tab stops on, in document order.
 *
 * @param {Element} container
 * @returns {Element[]}
 */
export function tabStopsOf(container) {
    return [...container.querySelectorAll(TAB_STOP)].filter((el) => el.tabIndex >= 0
        && ! el.disabled
        && el.type !== 'hidden'
        && ! el.closest?.('[inert]')
        && (typeof el.getClientRects !== 'function' || el.getClientRects().length > 0));
}

/**
 * Wrap a Tab that started in a panel the trap took in, once that panel has handled it.
 *
 * The trap leaves such a Tab to the panel (see `startsInAdoptedPanel()`). A menu closes on it
 * and hands the focus back to its trigger, and the browser then moves on from there, which is
 * where the key belongs. Past the last control of the dialog, or before its first, that move
 * would leave the dialog, and the trap only wraps a Tab that starts inside its own container. So
 * it wraps here: on the window, after the panel's own handler and before the browser's move.
 * A Tab something already took, and one after which the focus is still in the panel, are left
 * alone.
 *
 * @param {KeyboardEvent} event
 * @param {Iterable<{ trap: Object|null, container: HTMLElement, extras: Set<Element> }>} [traps]
 * @param {Document} [doc]
 */
export function wrapTabLeavingTheTrap(event, traps = liveTraps, doc = globalThis.document) {
    if (! isTab(event) || event.defaultPrevented) return;

    for (const entry of traps) {
        const trap = entry.trap;

        if (! trap || ! trap.active || trap.paused) continue;
        if (! startsInAdoptedPanel(entry.extras, event)) continue;

        const active = doc?.activeElement;

        if (! active || ! entry.container.contains(active)) return;

        const stops = tabStopsOf(entry.container);
        const edge = event.shiftKey ? stops[0] : stops[stops.length - 1];

        if (! edge || active !== edge) return;

        event.preventDefault();
        (event.shiftKey ? stops[stops.length - 1] : stops[0]).focus();

        return;
    }
}

let adoptionListening = false;

/** Installed once, with the first trap, and only where there is a window to listen on. */
function listenForTeleportedPanels() {
    if (adoptionListening || typeof window === 'undefined' || typeof window.addEventListener !== 'function') {
        return;
    }

    adoptionListening = true;

    const adopt = (event) => {
        if (liveTraps.size === 0) return;
        adoptTeleportedTarget(typeof event.composedPath === 'function' ? event.composedPath()[0] : event.target);
    };

    // The four events focus-trap decides on: focus coming in, and a press outside its containers.
    for (const type of ['focusin', 'mousedown', 'touchstart', 'click']) {
        window.addEventListener(type, adopt, { capture: true, passive: true });
    }

    // In the bubble phase, after the handler of the panel the Tab started in.
    window.addEventListener('keydown', (event) => {
        if (liveTraps.size === 0) return;
        wrapTabLeavingTheTrap(event);
    });
}

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
    const entry = { trap: null, container, extras: new Set() };

    listenForTeleportedPanels();

    const opts = {
        // Whether clicking outside deactivates the trap entirely
        clickOutsideDeactivates,
        // Whether clicks outside are allowed to pass through (without deactivating).
        // CRITICAL for modals/drawers — lets backdrop click handlers fire.
        allowOutsideClick,
        // ESC handling — controlled by dismissible prop, and never for a press an element
        // inside the trap has already handled
        escapeDeactivates: escapeUnlessHandled(escapeDeactivates),
        // Callback when trap is deactivated (ESC or programmatic). The trap stops taking in
        // teleported panels before the caller hears of it.
        onDeactivate: () => {
            liveTraps.delete(entry);
            entry.extras.clear();
            onDeactivate();
        },
        // An active trap takes in the teleported panels whose trigger sits inside it, see
        // `adoptTeleportedTarget()`.
        onActivate: () => {
            liveTraps.add(entry);
        },
        // Tab and Shift+Tab as the library defines them, except in a panel the trap has taken in,
        // which handles the key itself (see `startsInAdoptedPanel()`).
        isKeyForward: (event) => isTab(event) && ! event.shiftKey && ! startsInAdoptedPanel(entry.extras, event),
        isKeyBackward: (event) => isTab(event) && event.shiftKey && ! startsInAdoptedPanel(entry.extras, event),
        // Return focus to the element that was focused before trap activation
        returnFocusOnDeactivate: true,
        // Prevent scroll jump when activating trap
        preventScroll: true,
        // Fallback focus to the container itself if no focusable elements inside.
        //
        // A function, and the `tabindex` it writes is the whole point. focus-trap's
        // own README states the precondition next to this option: *"Make sure the
        // fallback element has a negative `tabindex` so it can be programmatically
        // focused."* The panels this library passes — modal, drawer, alert-dialog,
        // popover, command-palette and lightbox — carry no `tabindex` of their own,
        // so a bare node would not meet it.
        //
        // Without it nothing fails loudly. A truthy fallback
        // satisfies the library's "must have at least one tabbable node" check, so
        // no error is thrown; the fallback then resolves to a `<div>` and
        // `node.focus()` on a div without a tabindex is a NO-OP. The trap reports
        // itself active while `document.activeElement` is still `<body>`, and every
        // subsequent Tab is `preventDefault()`ed and handed to that same node — so
        // focus does not move at all.
        //
        // Reachable whenever a panel's entire content is plain text, as in a
        // popover that holds only a sentence.
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

    entry.trap = createFocusTrapLib(container, opts);

    return entry.trap;
}
