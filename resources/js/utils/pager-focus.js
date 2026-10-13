import { scrollTo } from './scroll-to.js';

/**
 * `$wkPagerTurn(name, scrollTarget)` and `$wkPagerLanded(name)`: a page turned under Livewire keeps
 * the focus in its pager.
 *
 * Under Livewire `pagination` turns the page in the same document, and the server renders the
 * navigation again. The page number the reader chose was a link and comes back as the current
 * page, a span, and `Previous` on the first page and `Next` on the last come back as spans too. A
 * morph cannot change an element's tag, so it replaces the element, and the focus it held falls to
 * the page: the next Tab starts at the top of the document.
 *
 * `$wkPagerTurn` runs in the click of every page control. It notes that this pager is turning and
 * scrolls to the pager's target, the work `$wkScrollTo` did there, since a handler in Alpine's CSP
 * build holds one call. `$wkPagerLanded` runs in the `x-init` of the elements a turn renders new:
 * the current page and a disabled end. Once the morph is done, if the focus has fallen to the
 * page, it goes to the current page, else to the first link of the pager, without scrolling, since
 * the turn already scrolled to where the new page starts. A turn that kept its control, `Next` in
 * the middle of the list, leaves the focus where it is.
 *
 * Keyed by the paginator's page name, so two pagers on one page keep their own turns, and a turn
 * that never lands, a request that failed, is forgotten after ten seconds.
 */
const pending = new Map();

/** How long a turn waits for the navigation it renders. */
const PENDING_MS = 10000;

export function pagerTurn(el, name, scrollTarget) {
    pending.set(String(name), Date.now());

    if (typeof scrollTarget === 'string' && scrollTarget !== '') {
        scrollTo(el, scrollTarget);
    }
}

export function pagerLanded(el, name) {
    const key = String(name);
    const at = pending.get(key);

    if (at === undefined) {
        return;
    }

    pending.delete(key);

    if (Date.now() - at > PENDING_MS) {
        return;
    }

    // After the morph, not during it: an element is initialized while the rest of the
    // navigation may still be on its way in, and the current page is the one to find.
    setTimeout(() => {
        const doc = el.ownerDocument;
        const active = doc ? doc.activeElement : null;

        if (active && active !== doc.body) {
            return;
        }

        const nav = typeof el.closest === 'function' ? el.closest('nav') : null;
        const target = nav ? (nav.querySelector('[aria-current="page"]') || nav.querySelector('a[href]')) : null;

        if (target && typeof target.focus === 'function') {
            target.focus({ preventScroll: true });
        }
    }, 0);
}

/** Register both magics on an Alpine instance. */
export function registerPagerFocusMagics(Alpine) {
    Alpine.magic('wkPagerTurn', (el) => (name, scrollTarget) => pagerTurn(el, name, scrollTarget));
    Alpine.magic('wkPagerLanded', (el) => (name) => pagerLanded(el, name));
}

export default registerPagerFocusMagics;
