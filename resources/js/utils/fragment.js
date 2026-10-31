/**
 * The id a fragment names, or an empty string. A fragment is percent-encoded in the address, and an
 * id is compared as written, so `#zahlung%C3%BCbersicht` names the element `zahlungübersicht`. A
 * fragment that does not decode is read as it stands.
 *
 * @param {string} hash  `location.hash`, with its `#` or without
 * @returns {string}
 */
export function fragmentId(hash) {
    const raw = String(hash || '').replace(/^#/, '');

    if (raw === '') {
        return '';
    }

    try {
        return decodeURIComponent(raw);
    } catch {
        return raw;
    }
}

/** An address without its fragment. */
const withoutFragment = (href) => String(href).replace(/#[\s\S]*$/, '');

/**
 * Whether a click follows a link to the fragment the address already names, on this page.
 *
 * The browser scrolls to that fragment once more, but fires no `hashchange`, since the address
 * does not change. A link that opens elsewhere, in a new tab or window, through the target it
 * names or as a download, is none, and neither is a click a script has taken over. The fragments
 * are compared as the ids they name, so a percent-encoding written in other letters names the same.
 *
 * @param {MouseEvent} event
 * @param {Location|null|undefined} location  the page's address
 * @returns {boolean}
 */
export function followsTheFragmentAgain(event, location) {
    if (! event || ! location || event.defaultPrevented || event.button !== 0
        || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return false;
    }

    const link = typeof event.target?.closest === 'function' ? event.target.closest('a[href]') : null;

    // An SVG link holds its address as an object rather than text, and is left to the browser.
    if (! link || typeof link.href !== 'string' || link.hasAttribute('download')) {
        return false;
    }

    const target = (link.getAttribute('target') ?? '').trim().toLowerCase();

    if (target !== '' && target !== '_self') {
        return false;
    }

    const id = fragmentId(location.hash);

    return id !== ''
        && withoutFragment(link.href) === withoutFragment(location.href)
        && fragmentId(link.hash) === id;
}

/**
 * Call `again` each time the reader follows a link to the fragment the address already names.
 * Something that opens for a link, and that the reader closed after following it, would otherwise
 * stay closed for the second click. `again` runs once the browser has scrolled to the fragment
 * itself, where a `hashchange` would come, and not for a click another listener took over on its
 * way.
 *
 * @param {() => void} again
 * @param {Window} [win]
 * @returns {() => void} stops listening
 */
export function onFragmentFollowedAgain(again, win = typeof window === 'undefined' ? undefined : window) {
    if (! win || typeof win.addEventListener !== 'function') {
        return () => {};
    }

    let timer = null;

    const onClick = (event) => {
        if (! followsTheFragmentAgain(event, win.location)) {
            return;
        }

        win.clearTimeout(timer);
        timer = win.setTimeout(() => {
            timer = null;

            if (! event.defaultPrevented) {
                again();
            }
        }, 0);
    };

    win.addEventListener('click', onClick);

    return () => {
        win.removeEventListener('click', onClick);
        win.clearTimeout(timer);
        timer = null;
    };
}
