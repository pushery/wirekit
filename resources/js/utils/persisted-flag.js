/**
 * A boolean that survives a reload, when storage allows it.
 *
 * Three components keep one — the sidebar rail's folded state, and the open
 * state of each of its two disclosure shapes. The mechanic used to be emitted
 * as JavaScript source from PHP so all three would share it; that made it one
 * implementation, but an implementation living in a string, which Alpine's CSP
 * build cannot parse. Same idea, expressed where it can be read and tested.
 *
 * `localStorage` throws rather than returning null in private mode and when
 * storage is disabled entirely, so every access is guarded. A reader whose
 * browser refuses storage gets a component that simply forgets between visits,
 * which is the right failure — never a broken page.
 */

/** Read a stored flag, falling back to the seed when there is nothing to read. */
export function readPersistedFlag(key, fallback) {
    if (! key) {
        return fallback;
    }

    try {
        const stored = window.localStorage.getItem(key);

        // A stored value wins over the seed; nothing stored leaves the seed
        // alone, so the attribute on the tag still decides the first visit.
        return stored === null ? fallback : stored === '1';
    } catch {
        return fallback;
    }
}

/** Store a flag, or do nothing at all when there is no key or no storage. */
export function writePersistedFlag(key, value) {
    if (! key) {
        return;
    }

    try {
        window.localStorage.setItem(key, value ? '1' : '0');
    } catch {
        // Nothing to report: the state is still correct for this visit, it just
        // will not be remembered for the next one.
    }
}
