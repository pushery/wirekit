/**
 * `$wkScrollTo(selector)` — scroll the nearest ancestor that matches the selector into view, or
 * the first element in the document that does, and do nothing when there is none.
 *
 * `pagination` scrolls to a target after a page turn, and the target is often not an ancestor
 * of the pager: the heading above the list is the usual one. An expression cannot reach the
 * document itself: under Alpine's CSP build `$el.ownerDocument.querySelector(…)` is refused
 * whichever way the expression gets there, because the evaluator checks every value it reads
 * and a Document is one it prohibits, so it would throw on every page turn and scroll nowhere.
 * A magic runs in the bundle, where the document is an ordinary object, and the
 * expression is left with a single call that both builds evaluate.
 */
export function scrollTo(el, selector) {
    if (! el || typeof selector !== 'string' || selector === '') {
        return;
    }

    let target;

    try {
        target = (typeof el.closest === 'function' ? el.closest(selector) : null)
            || (el.ownerDocument ? el.ownerDocument.querySelector(selector) : null);
    } catch {
        // An invalid selector throws; there is nothing to scroll to.
        return;
    }

    if (target && typeof target.scrollIntoView === 'function') {
        target.scrollIntoView();
    }
}

/** Register the magic on an Alpine instance. */
export function registerScrollToMagic(Alpine) {
    Alpine.magic('wkScrollTo', (el) => (selector) => scrollTo(el, selector));
}

export default registerScrollToMagic;
