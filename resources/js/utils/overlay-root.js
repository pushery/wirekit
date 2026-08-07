/**
 * The landmark container every teleported overlay panel lives in.
 *
 * Twenty-four components teleport their panel to `<body>` — dropdowns, tooltips, popovers,
 * comboboxes, menus, the command palette. That is correct positioning (a panel inside an
 * `overflow: hidden` ancestor is clipped by it) and it puts the panel OUTSIDE every landmark
 * on the page: not in `<main>`, not in `<nav>`, not in anything. axe reports `region` for
 * each one, and a developer auditing their own app cannot fix it — the markup belongs to us.
 *
 * A screen-reader user meets the same problem the audit describes: content that exists in no
 * region at all, reachable by tabbing into it and then unmoored from the page structure.
 *
 * So the panels teleport into a named region instead. `role="region"` with a label is the
 * smallest correct answer: it is a landmark, it does not claim to be navigation or a main,
 * and it says what it holds.
 *
 * CREATED FROM JAVASCRIPT rather than emitted by a Blade directive, and that is deliberate.
 * `x-teleport` throws when its selector matches nothing, so a container that depended on a
 * directive would take the page down for anyone who loads the bundle another way — a
 * developer bundling it through Vite, a docs site embedding it, anyone not using
 * `@wirekitScripts`. Built here it cannot be missing.
 */
export const OVERLAY_ROOT_ID = 'wk-overlay-root';

/**
 * Return the overlay root, creating it on first use.
 *
 * Idempotent: repeated calls return the same element, and an element the developer put
 * there themselves is adopted rather than duplicated.
 */
export function overlayRoot() {
    let root = document.getElementById(OVERLAY_ROOT_ID);

    if (root) {
        return root;
    }

    root = document.createElement('div');
    root.id = OVERLAY_ROOT_ID;
    root.setAttribute('role', 'region');

    // Named, because an unlabelled region is its own axe finding — and a landmark a screen
    // reader announces without saying what it is helps nobody.
    root.setAttribute('aria-label', 'Overlays');

    document.body.appendChild(root);

    return root;
}

/**
 * Ensure the root exists before Alpine processes any `x-teleport`.
 *
 * `alpine:init` fires before the DOM walk, so a panel that teleports during that walk finds
 * the container already there. Without this the FIRST overlay on a page would teleport into
 * a selector that does not resolve yet — and `x-teleport` treats that as fatal.
 */
export function installOverlayRoot() {
    if (typeof document === 'undefined') {
        return;
    }

    if (document.body) {
        overlayRoot();

        return;
    }

    document.addEventListener('DOMContentLoaded', () => overlayRoot(), { once: true });
}
