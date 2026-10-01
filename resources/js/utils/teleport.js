/**
 * Where a node sits once Alpine's `x-teleport` has moved it.
 *
 * Alpine moves the content of an `x-teleport` to its target and leaves a pointer on the moved
 * root back to the `<template>` it came from, `_x_teleportBack`. The template stays where it was
 * written, which is how a panel teleported to the end of the page is traced back to the dialog
 * that holds its trigger.
 */

const OVERLAY_ROOT_ID = 'wk-overlay-root';

/**
 * The root of the teleported clone a node sits in, or null outside one.
 *
 * @param {Node|null|undefined} node
 * @returns {Element|null}
 */
export function teleportedRootOf(node) {
    for (let current = node; current; current = current.parentNode) {
        if (current._x_teleportBack) {
            return current;
        }
    }

    return null;
}

/**
 * Whether a node sits inside a container, counting a teleported panel as sitting where its
 * `<template>` was written, through as many teleports as lie in between.
 *
 * @param {Node|null|undefined} node
 * @param {HTMLElement} container
 * @returns {boolean}
 */
export function belongsThroughTeleports(node, container) {
    let current = node;

    // A chain of teleports is short. The bound only keeps a malformed chain from looping.
    for (let hops = 0; current && hops < 32; hops++) {
        if (container.contains(current)) {
            return true;
        }

        const root = teleportedRootOf(current);

        if (! root) {
            return false;
        }

        current = root._x_teleportBack;
    }

    return false;
}

/**
 * A test for the tab stops that a Tab leaving a teleported panel cannot reach from `anchor`,
 * the element the panel is drawn beside.
 *
 * On the page, the overlay root is out of reach: it holds this panel and every other teleported
 * one, each drawn somewhere else entirely, so none of them is a sensible neighbor. Inside a
 * modal or a drawer the anchor sits in the overlay root itself, in the teleported clone of that
 * dialog, and only that clone is in reach: the rest of the root is other overlays, and the page
 * behind the dialog is where its focus trap would pull the focus back from.
 *
 * @param {Element} anchor
 * @param {Document} [doc]
 * @returns {(el: Element) => boolean} Whether an element is out of reach.
 */
export function outOfReachBeside(anchor, doc = globalThis.document) {
    const overlayRoot = doc?.getElementById?.(OVERLAY_ROOT_ID) ?? null;

    if (! overlayRoot || ! overlayRoot.contains(anchor)) {
        return (el) => Boolean(overlayRoot?.contains(el));
    }

    // An anchor in the root without a clone around it was placed there by hand, and then the
    // root is all there is to go by.
    const reach = teleportedRootOf(anchor) ?? overlayRoot;

    return (el) => ! reach.contains(el);
}
