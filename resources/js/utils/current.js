/**
 * `x-wk-current`: a navigation Livewire keeps across `wire:navigate` marks the page the reader is
 * on, not the page it was rendered on.
 *
 * Livewire puts an element inside `@persist` back after a visit without rendering it again, so
 * the entry a sidebar marked as current stays the page the reader left: its classes and its
 * `aria-current="page"`. Livewire's own `wire:current` moves classes and `data-current` but no
 * `aria-current`, so a screen reader went on naming the old page.
 *
 * Put it on the navigation; the kit's navigation components do it with `track-current`. Once when
 * it starts, after every `livewire:navigated` and whenever the address moves without a visit, the
 * scope compares the link of each entry with the address. The entry whose link is the longest path that is the page's own or a parent of it,
 * by whole segments, is the current one, so "/orders" is current on "/orders/42" and "/" only
 * where nothing longer matches. Where entries share that path and differ by their query, the one
 * whose query the page carries is current. A link to another host, or to an anchor on the page,
 * never is.
 *
 * The current entry takes `aria-current` (the value in `data-wk-current-aria`, "page" without one),
 * the classes in `data-wk-current-classes` and `data-active="true"` where the entry carries
 * `data-active`; every other entry drops them and takes the classes in `data-wk-idle-classes`.
 * The kit's entries render both class lists under `track-current`: the classes a current entry
 * has and an idle one has not, and the other way round, so nothing else on the entry moves.
 *
 * The address moves without a visit when Livewire writes a `#[Url]` property into it, and when Back
 * or Forward returns to an address written that way. Livewire tells the page neither: it writes a
 * `replaceState` in a microtask after the response that changed the property, a `pushState` at
 * once, and dispatches nothing for either. So every scope is marked again after each Livewire
 * response, once those writes have run, and on `popstate`. A script that writes the address itself
 * dispatches `wirekit:address-changed` on the window afterwards.
 *
 * Lifecycle: the scope carries `data-wk-current-scope`, and one set of listeners per page refreshes
 * every scope in the document. A scope inside `@persist` is never initialized again, so the
 * listeners find it by its attribute rather than through anything its first initialization
 * registered.
 */

export const WK_CURRENT_SCOPE_ATTRIBUTE = 'data-wk-current-scope';
export const WK_CURRENT_CLASSES_ATTRIBUTE = 'data-wk-current-classes';
export const WK_IDLE_CLASSES_ATTRIBUTE = 'data-wk-idle-classes';
export const WK_CURRENT_ARIA_ATTRIBUTE = 'data-wk-current-aria';

/** Dispatched on the window by a script that wrote the address itself, to mark the scopes again. */
export const WK_ADDRESS_CHANGED_EVENT = 'wirekit:address-changed';

/** Remembered on `window`, the one scope a second copy of this module on the page shares. */
const INSTALLED_FLAG = '__wirekitCurrentInstalled';

/** A path's segments, without the slashes that end it. */
function segments(pathname) {
    return String(pathname).replace(/\/+$/, '').split('/');
}

/**
 * How many segments of a link's path lead to the page's path, or -1 when the link is not the
 * page or a parent of it.
 *
 * @param {URL} link
 * @param {URL} page
 */
export function matchLength(link, page) {
    if (link.host !== page.host) {
        return -1;
    }

    const own = segments(link.pathname);
    const actual = segments(page.pathname);

    if (own.length > actual.length) {
        return -1;
    }

    for (let index = 0; index < own.length; index++) {
        if (own[index] !== actual[index]) {
            return -1;
        }
    }

    return own.length;
}

/**
 * How many of a link's query parameters the page carries with the same value, or -1 when the page
 * lacks one of them. A link without a query fits every page, with 0.
 *
 * @param {URL} link
 * @param {URL} page
 */
export function queryFit(link, page) {
    let fit = 0;

    for (const [key, value] of link.searchParams) {
        if (! page.searchParams.getAll(key).includes(value)) {
            return -1;
        }

        fit++;
    }

    return fit;
}

/** The words of a class list, empty when there is none. */
function words(list) {
    return String(list ?? '').split(/\s+/).filter(Boolean);
}

/** The link an entry navigates to, or null for an anchor on the page and anything without one. */
function linkOf(el, page) {
    const href = el.getAttribute('href');

    if (href === null || href.trim() === '' || href.trim().startsWith('#')) {
        return null;
    }

    try {
        return new URL(href, page);
    } catch {
        return null;
    }
}

/**
 * Move one entry to current or idle, writing only what changes.
 *
 * Every Livewire response marks the scopes again, and `classList.add()` and `classList.remove()`
 * write the class attribute even when no class changes: an unchanged entry would still be a
 * mutation on every response.
 */
function mark(el, current) {
    const on = words(el.getAttribute(WK_CURRENT_CLASSES_ATTRIBUTE));
    const off = words(el.getAttribute(WK_IDLE_CLASSES_ATTRIBUTE));
    const leaving = (current ? off : on).filter((name) => el.classList.contains(name));
    const arriving = (current ? on : off).filter((name) => ! el.classList.contains(name));

    if (leaving.length > 0) {
        el.classList.remove(...leaving);
    }

    if (arriving.length > 0) {
        el.classList.add(...arriving);
    }

    const aria = current ? (el.getAttribute(WK_CURRENT_ARIA_ATTRIBUTE) || 'page') : null;

    if (aria === null) {
        if (el.hasAttribute('aria-current')) {
            el.removeAttribute('aria-current');
        }
    } else if (el.getAttribute('aria-current') !== aria) {
        el.setAttribute('aria-current', aria);
    }

    const active = current ? 'true' : 'false';

    if (el.hasAttribute('data-active') && el.getAttribute('data-active') !== active) {
        el.setAttribute('data-active', active);
    }
}

/**
 * Mark the entry of a scope that leads to the page, and only that one. Returns the entries marked
 * as current.
 *
 * @param {Element} scope
 * @param {URL} page
 * @returns {Element[]}
 */
export function markCurrent(scope, page) {
    // The scope's own links, not those of a scope inside it, which answers for itself.
    const entries = Array.from(scope.querySelectorAll('a[href]'))
        .filter((el) => el.parentElement?.closest(`[${WK_CURRENT_SCOPE_ATTRIBUTE}]`) === scope)
        .map((el) => {
            const link = linkOf(el, page);

            return { el, length: link ? matchLength(link, page) : -1, fit: link ? queryFit(link, page) : -1 };
        });

    const best = entries.reduce((longest, entry) => Math.max(longest, entry.length), -1);

    // Among the entries at the longest path, two views of one page that differ by their query,
    // `?status=open` and `?status=closed`, would both be current. The ones whose query the page
    // carries win, the most specific first; where none does, the path alone decides.
    const bestFit = entries
        .filter((entry) => best > -1 && entry.length === best)
        .reduce((most, entry) => Math.max(most, entry.fit), -1);
    const current = [];

    for (const entry of entries) {
        const isCurrent = best > -1 && entry.length === best && (bestFit === -1 || entry.fit === bestFit);

        mark(entry.el, isCurrent);

        if (isCurrent) {
            current.push(entry.el);
        }
    }

    return current;
}

/** Every scope in the document, marked against the address the page is at. */
function refreshAll() {
    const page = new URL(window.location.href);

    for (const scope of Array.from(document.querySelectorAll(`[${WK_CURRENT_SCOPE_ATTRIBUTE}]`))) {
        markCurrent(scope, page);
    }
}

/**
 * After each Livewire response, once the address it led to is written. Livewire writes a `#[Url]`
 * property from an effect that runs after the response, and its `replaceState` waits for a further
 * microtask; a timer runs after both.
 *
 * @param {{hook: Function}} livewire
 */
function hookLivewire(livewire) {
    livewire.hook('commit', ({ succeed }) => {
        succeed(() => setTimeout(refreshAll, 0));
    });
}

/** The listeners that mark every scope again, once per page. */
function installRefresh() {
    document.addEventListener('livewire:navigated', refreshAll);
    window.addEventListener('popstate', refreshAll);
    window.addEventListener(WK_ADDRESS_CHANGED_EVENT, refreshAll);

    if (window.Livewire?.hook) {
        hookLivewire(window.Livewire);

        return;
    }

    document.addEventListener('livewire:init', () => {
        if (window.Livewire?.hook) {
            hookLivewire(window.Livewire);
        }
    }, { once: true });
}

/**
 * Register the `x-wk-current` directive.
 *
 * @param {object} Alpine
 */
export function registerCurrentDirective(Alpine) {
    // On a clone for a Livewire morph, only the scope attribute: the morph compares the clone with
    // the element and would take the attribute off the element without it.
    const skipDuringClone = typeof Alpine.skipDuringClone === 'function' ? Alpine.skipDuringClone : (callback) => callback;

    Alpine.directive('wk-current', skipDuringClone((el) => {
        el.setAttribute(WK_CURRENT_SCOPE_ATTRIBUTE, '');

        if (typeof window !== 'undefined' && typeof document !== 'undefined' && ! window[INSTALLED_FLAG]) {
            window[INSTALLED_FLAG] = true;
            installRefresh();
        }

        markCurrent(el, new URL(window.location.href));
    }, (el) => {
        el.setAttribute(WK_CURRENT_SCOPE_ATTRIBUTE, '');
    }));

    // A navigation is rarely inside an `x-data` of its own: the selector makes Alpine reach it.
    if (typeof Alpine.addInitSelector === 'function') {
        Alpine.addInitSelector(() => '[x-wk-current]');
    }
}
