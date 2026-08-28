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
 *
 * TWO DRIVERS, and the second one exists for a reason localStorage cannot solve.
 * No server can read localStorage, so a component that remembers a layout choice
 * renders its seed state first and corrects itself after the first paint. Measured
 * by an adopting application at 0.1097 CLS against a budget of 0.1, 53ms in, with
 * the content column moving 187px. The usual answer — a blocking inline script in
 * the head — is unavailable under a strict `script-src 'self'` policy without a
 * nonce, which is not a niche configuration.
 *
 * A cookie is the only store both Blade and Alpine can read, so `driver: 'cookie'`
 * mirrors the flag there and the server seeds the first render correctly. It is
 * opt-in: writing a cookie where a developer asked for localStorage would be a
 * behavior change, and a cookie is the kind of thing an application has to be able
 * to account for.
 */

/** How long a remembered flag survives. A layout preference, not a session. */
const COOKIE_MAX_AGE = 31536000;

/**
 * Read one cookie by name.
 *
 * Values are written as '1'/'0', so nothing here needs decoding — but the name is
 * matched exactly rather than by prefix, because 'wk-rail' and 'wk-rail-2' would
 * otherwise answer for each other.
 */
function readCookie(name) {
    const target = name + '=';

    for (const part of String(document.cookie || '').split(';')) {
        const entry = part.trim();

        if (entry.startsWith(target)) {
            return entry.slice(target.length);
        }
    }

    return null;
}

function writeCookie(name, value) {
    // `Secure` only where the page is already secure: setting it on http drops the
    // cookie silently, and local development is served over http more often than not.
    const secure = window.location.protocol === 'https:' ? '; Secure' : '';

    document.cookie = `${name}=${value}; path=/; max-age=${COOKIE_MAX_AGE}; SameSite=Lax${secure}`;
}

/** Read a stored flag, falling back to the seed when there is nothing to read. */
export function readPersistedFlag(key, fallback, driver = 'local') {
    if (! key) {
        return fallback;
    }

    if (driver === 'cookie') {
        try {
            const stored = readCookie(key);

            return stored === null ? fallback : stored === '1';
        } catch {
            return fallback;
        }
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
export function writePersistedFlag(key, value, driver = 'local') {
    if (! key) {
        return;
    }

    if (driver === 'cookie') {
        try {
            writeCookie(key, value ? '1' : '0');
        } catch {
            // Same failure as below, and the same shape: this visit is still correct.
        }

        return;
    }

    try {
        window.localStorage.setItem(key, value ? '1' : '0');
    } catch {
        // Nothing to report: the state is still correct for this visit, it just
        // will not be remembered for the next one.
    }
}
