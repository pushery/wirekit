/**
 * Where a chart's data points lead.
 *
 * The server sends a grid of addresses with the chart's data, `wkLinks`: one row per dataset, one
 * entry per point, null where a point leads nowhere (`Pushery\WireKit\Charts\ChartPointLinks`). A
 * chart factory takes the grid out of the configuration before the library sees it, shows a
 * pointer over a point that has an address, and follows the address of the point a reader clicks
 * or taps, the way a link would.
 */

import { safeHref } from './safe-href.js';

/**
 * Take the grid out of a chart's configuration, so the library never reads a key it does not know.
 *
 * @param {Object} config - The configuration or a data payload, changed in place.
 * @returns {Array|null} The grid, or null when the chart has none.
 */
export function takePointLinks(config) {
    if (! config || typeof config !== 'object' || ! Object.prototype.hasOwnProperty.call(config, 'wkLinks')) {
        return null;
    }

    const links = config.wkLinks;
    delete config.wkLinks;

    return Array.isArray(links) ? links : null;
}

/**
 * The address of one point, or null when it has none.
 *
 * Read through the kit's rule for link targets a second time: the server already applied it, and
 * a grid is cheap to check where a click is about to leave the page.
 *
 * @param {Array|null} links - The grid.
 * @param {number} datasetIndex
 * @param {number} pointIndex
 * @returns {string|null}
 */
export function pointLink(links, datasetIndex, pointIndex) {
    if (! Array.isArray(links) || ! Number.isInteger(datasetIndex) || ! Number.isInteger(pointIndex)) {
        return null;
    }

    const row = links[datasetIndex];
    const url = Array.isArray(row) ? safeHref(row[pointIndex]) : '';

    return url === '' ? null : url;
}

/**
 * Follow an address the way a link does.
 *
 * A click that would open a link somewhere else, with Ctrl, Cmd or Shift or the middle button,
 * opens a new tab. A fragment of this page is a jump within it. Another address on this site goes
 * through Livewire's navigation where the page runs Livewire, as a `wire:navigate` link does, and
 * anything else through the browser's own.
 *
 * @param {string} url
 * @param {MouseEvent|PointerEvent|null} event - The click, read for its keys and button.
 * @param {Window} win
 */
export function followPointLink(url, event = null, win = window) {
    const safe = safeHref(url);
    let target;

    try {
        target = safe === '' ? null : new URL(safe, win.location.href);
    } catch {
        target = null;
    }

    if (! target) {
        return;
    }

    if (event && (event.metaKey || event.ctrlKey || event.shiftKey || event.button === 1)) {
        win.open(target.href, '_blank', 'noopener');

        return;
    }

    // A fragment of this page is a jump within it, which no navigation of a whole page should replace.
    if (target.hash !== '' && target.href.split('#')[0] === String(win.location.href).split('#')[0]) {
        win.location.assign(target.href);

        return;
    }

    if (target.origin === win.location.origin && typeof win.Livewire?.navigate === 'function') {
        win.Livewire.navigate(target.href);

        return;
    }

    win.location.assign(target.href);
}
