/**
 * What the stylesheet does to a single-key shortcut, for a script that has to follow it.
 *
 * `<x-wirekit::kbd shortcut="…">` marks the drawn keys of a single-key shortcut, and the stylesheet
 * hides them while the reader has switched single keys off, which `markKeyDisplay()` in
 * `shortcuts.js` says on the root, or while an element in the page turns them off. Kept apart from
 * `shortcuts.js`, which a component that only follows the keys has no other use for.
 */

/**
 * Whether the stylesheet hides the drawn keys of a single-key shortcut now: the reader has switched
 * them off, which `markKeyDisplay()` says on the root, or an element in the page turns them off.
 * These are the two conditions of the stylesheet's rule, read from the page rather than from the
 * stored choice, so a caller follows what is drawn.
 */
export function singleKeysHidden(doc = typeof document !== 'undefined' ? document : null) {
    if (! doc) {
        return false;
    }

    return doc.documentElement?.hasAttribute?.('data-wk-single-keys-off') === true
        || (doc.querySelector?.('[data-wk-single-keys="off"]') ?? null) !== null;
}

/** The callers of watchSingleKeysHidden(), and the one observer that serves them all. */
const singleKeysWatchers = new Set();
let singleKeysObserver = null;
let singleKeysWereHidden = false;

/**
 * Whether a change can move what `singleKeysHidden()` answers: one of its two attributes changed, or
 * an element that carries the screen's attribute, or holds one that does, came or went.
 */
function movesSingleKeys(record) {
    if (record.type === 'attributes') {
        return true;
    }

    const marked = (node) => node?.nodeType === 1
        && (node.hasAttribute?.('data-wk-single-keys') === true || (node.querySelector?.('[data-wk-single-keys]') ?? null) !== null);

    return Array.from(record.addedNodes ?? []).some(marked) || Array.from(record.removedNodes ?? []).some(marked);
}

/**
 * Call `callback` with the new answer whenever single-key shortcuts start or stop being hidden: the
 * reader flips the switch and the root is marked again, or an element that turns them off comes or
 * goes with an update or a new page. One observer serves every caller and stops with the last one.
 *
 * @param {(hidden: boolean) => void} callback
 * @returns {() => void} the function that stops this caller
 */
export function watchSingleKeysHidden(callback, doc = typeof document !== 'undefined' ? document : null) {
    const root = doc?.documentElement;

    if (! root || typeof MutationObserver !== 'function') {
        return () => {};
    }

    singleKeysWatchers.add(callback);

    if (singleKeysObserver === null) {
        singleKeysWereHidden = singleKeysHidden(doc);
        singleKeysObserver = new MutationObserver((records) => {
            if (! records.some(movesSingleKeys)) {
                return;
            }

            const hidden = singleKeysHidden(doc);

            if (hidden === singleKeysWereHidden) {
                return;
            }

            singleKeysWereHidden = hidden;

            for (const watcher of [...singleKeysWatchers]) {
                watcher(hidden);
            }
        });
        singleKeysObserver.observe(root, {
            attributes: true,
            attributeFilter: ['data-wk-single-keys-off', 'data-wk-single-keys'],
            childList: true,
            subtree: true,
        });
    }

    return () => {
        singleKeysWatchers.delete(callback);

        if (singleKeysWatchers.size === 0 && singleKeysObserver !== null) {
            singleKeysObserver.disconnect();
            singleKeysObserver = null;
        }
    };
}
