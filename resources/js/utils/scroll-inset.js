/**
 * A surface pinned to an edge of its scroll container tells the container how much of that edge it
 * covers, so an element that takes the focus, or that a fragment link brings into view, comes to
 * rest beside the surface instead of under it (WCAG 2.4.11, Focus Not Obscured).
 *
 * A browser scrolls such an element to the edge of the scroll container, less the container's
 * `scroll-padding`. A pinned surface carries `data-wk-scroll-inset`, or `data-wk-scroll-inset="bottom"`
 * for one at the bottom. The band it covers runs from that edge to its far side while it rests:
 * the container's own padding on that side (none for the page), the surface's `top` or `bottom`,
 * and its height. The container takes the largest band on each side, plus a gap, as
 * `--wk-scroll-inset-top` and `--wk-scroll-inset-bottom`, and the stylesheet turns those into
 * `scroll-padding` at zero specificity, so a value the developer sets wins.
 *
 * The page writes on `<html>`. A box that scrolls by itself is marked `data-wk-scroll-inset-box`
 * for the stylesheet, and a render that takes the mark or the values away gets them back. A
 * surface that is not sticky or fixed at the moment, or not displayed, covers nothing.
 *
 * Installed once per page: a ResizeObserver follows the height of every surface, and one
 * MutationObserver finds surfaces as they come and go, through a Livewire render or
 * `wire:navigate`. With no surface on the page it only watches.
 */
import { scrollParent } from './stuck.js';

export const WK_SCROLL_INSET_ATTRIBUTE = 'data-wk-scroll-inset';
export const WK_SCROLL_INSET_BOX_ATTRIBUTE = 'data-wk-scroll-inset-box';

const PROPERTIES = { top: '--wk-scroll-inset-top', bottom: '--wk-scroll-inset-bottom' };

/**
 * The installation is remembered on `window`, the one scope a second bundle on the page, or a
 * second evaluation of this one, shares with the first. It holds the handle that takes the
 * installation down again, so whichever copy is asked can do it.
 */
const INSTALLED_FLAG = '__wirekitScrollInset';

/**
 * The edge a surface is pinned to.
 *
 * @param {Element} surface
 * @returns {'top'|'bottom'}
 */
export function surfaceEdge(surface) {
    return surface.getAttribute(WK_SCROLL_INSET_ATTRIBUTE) === 'bottom' ? 'bottom' : 'top';
}

/**
 * The box a surface is pinned in, or null for the page. A fixed surface is pinned to the viewport.
 *
 * @param {Element} surface
 * @returns {Element|null}
 */
export function insetContainer(surface) {
    return getComputedStyle(surface).position === 'fixed' ? null : scrollParent(surface);
}

/**
 * The band a surface covers at its edge of the container, in pixels: 0 when it is not pinned now.
 *
 * @param {Element} surface
 * @param {Element|null} container
 * @param {'top'|'bottom'} edge
 * @returns {number}
 */
export function coveredBand(surface, container, edge = surfaceEdge(surface)) {
    const style = getComputedStyle(surface);

    if ((style.position !== 'sticky' && style.position !== 'fixed') || surface.getClientRects().length === 0) {
        return 0;
    }

    const offset = parseFloat(edge === 'bottom' ? style.bottom : style.top);
    const padding = container ? parseFloat(getComputedStyle(container)[edge === 'bottom' ? 'paddingBottom' : 'paddingTop']) : 0;
    const band = (Number.isFinite(padding) ? padding : 0) + (Number.isFinite(offset) ? offset : 0) + surface.getBoundingClientRect().height;

    return band > 0 ? band : 0;
}

/**
 * The largest band on each side of every container that has one; the page is keyed by `<html>`.
 *
 * @param {Iterable<Element>} surfaces
 * @returns {Map<Element, {top: number, bottom: number}>}
 */
export function containerBands(surfaces) {
    const bands = new Map();

    for (const surface of surfaces) {
        const container = insetContainer(surface);
        const edge = surfaceEdge(surface);
        const band = coveredBand(surface, container, edge);

        if (band <= 0) {
            continue;
        }

        const key = container || document.documentElement;
        const entry = bands.get(key) || { top: 0, bottom: 0 };
        entry[edge] = Math.max(entry[edge], band);
        bands.set(key, entry);
    }

    return bands;
}

/**
 * The value written for a band: whole pixels and the gap, or nothing for no band.
 *
 * @param {number} band
 * @returns {string}
 */
export function insetValue(band) {
    return band > 0 ? `calc(${Math.ceil(band)}px + var(--wk-scroll-inset-gap, 0.5rem))` : '';
}

/**
 * Install the page-wide measurement. Idempotent.
 */
export function installScrollInset() {
    if (typeof window === 'undefined' || typeof document === 'undefined' || window[INSTALLED_FLAG]) {
        return;
    }

    const root = document.documentElement;
    const surfaces = new Set();
    const written = new Map();
    const guards = new Map();
    let frame = 0;

    const write = (container, values) => {
        for (const edge of ['top', 'bottom']) {
            const property = PROPERTIES[edge];

            if (values[edge] !== '') {
                if (container.style.getPropertyValue(property) !== values[edge]) {
                    container.style.setProperty(property, values[edge]);
                }
            } else if (container.style.getPropertyValue(property) !== '') {
                container.style.removeProperty(property);
            }
        }

        if (container !== root && ! container.hasAttribute(WK_SCROLL_INSET_BOX_ATTRIBUTE)) {
            container.setAttribute(WK_SCROLL_INSET_BOX_ATTRIBUTE, '');
        }
    };

    const clear = (container) => {
        container.style.removeProperty(PROPERTIES.top);
        container.style.removeProperty(PROPERTIES.bottom);

        if (container !== root) {
            container.removeAttribute(WK_SCROLL_INSET_BOX_ATTRIBUTE);
        }

        const observer = guards.get(container);

        if (observer) {
            observer.disconnect();
            guards.delete(container);
        }
    };

    // A Livewire render writes the attributes the server sent, which carry neither the mark nor the
    // values; this puts them back. Writing only what differs keeps the observer from feeding itself.
    const guard = (container) => {
        if (container === root || guards.has(container) || typeof MutationObserver === 'undefined') {
            return;
        }

        const observer = new MutationObserver(() => {
            const values = written.get(container);

            if (values) {
                write(container, values);
            }
        });

        observer.observe(container, { attributes: true, attributeFilter: ['style', WK_SCROLL_INSET_BOX_ATTRIBUTE] });
        guards.set(container, observer);
    };

    const resize = typeof ResizeObserver === 'function' ? new ResizeObserver(() => schedule()) : null;

    const measure = () => {
        frame = 0;

        for (const surface of [...surfaces]) {
            if (! surface.isConnected || ! surface.hasAttribute(WK_SCROLL_INSET_ATTRIBUTE)) {
                surfaces.delete(surface);

                if (resize) {
                    resize.unobserve(surface);
                }
            }
        }

        const bands = containerBands(surfaces);

        for (const container of [...written.keys()]) {
            if (! bands.has(container)) {
                clear(container);
                written.delete(container);
            }
        }

        for (const [container, band] of bands) {
            const values = { top: insetValue(band.top), bottom: insetValue(band.bottom) };
            written.set(container, values);
            write(container, values);
            guard(container);
        }
    };

    // One measurement a frame, however many changes arrive in it.
    function schedule() {
        if (frame === 0 && typeof requestAnimationFrame === 'function') {
            frame = requestAnimationFrame(measure);
        }
    }

    // Takes in every surface at or under a node; true when one was new.
    const adopt = (node) => {
        if (! node || node.nodeType !== 1) {
            return false;
        }

        const found = node.hasAttribute(WK_SCROLL_INSET_ATTRIBUTE) ? [node] : [];

        if (typeof node.querySelectorAll === 'function') {
            found.push(...node.querySelectorAll(`[${WK_SCROLL_INSET_ATTRIBUTE}]`));
        }

        let added = false;

        for (const surface of found) {
            if (! surfaces.has(surface)) {
                surfaces.add(surface);
                added = true;

                if (resize) {
                    resize.observe(surface);
                }
            }
        }

        return added;
    };

    const finder = typeof MutationObserver !== 'undefined' ? new MutationObserver((mutations) => {
        let changed = false;

        for (const mutation of mutations) {
            if (mutation.type === 'attributes') {
                adopt(mutation.target);
                changed = true;

                continue;
            }

            for (const node of mutation.addedNodes) {
                changed = adopt(node) || changed;
            }

            if (mutation.removedNodes.length > 0 && surfaces.size > 0) {
                changed = true;
            }
        }

        if (changed) {
            schedule();
        }
    }) : null;

    if (finder) {
        finder.observe(root, { childList: true, subtree: true, attributes: true, attributeFilter: [WK_SCROLL_INSET_ATTRIBUTE] });
    }

    window.addEventListener('resize', schedule, { passive: true });

    window[INSTALLED_FLAG] = {
        uninstall() {
            if (finder) {
                finder.disconnect();
            }

            if (resize) {
                resize.disconnect();
            }

            window.removeEventListener('resize', schedule);

            if (frame !== 0 && typeof cancelAnimationFrame === 'function') {
                cancelAnimationFrame(frame);
            }

            frame = 0;

            // Every container is left as it was found, its guard with it.
            for (const container of [...written.keys()]) {
                clear(container);
            }

            written.clear();
            surfaces.clear();
            window[INSTALLED_FLAG] = undefined;
        },
    };

    adopt(root);
    schedule();
}

/**
 * Take the page-wide measurement down again: both observers, every box's guard, the resize
 * listener and what was written. A page keeps it for its whole life; this is for a host that
 * removes WireKit from a page that goes on running.
 */
export function uninstallScrollInset() {
    if (typeof window !== 'undefined' && window[INSTALLED_FLAG] && typeof window[INSTALLED_FLAG].uninstall === 'function') {
        window[INSTALLED_FLAG].uninstall();
    }
}
