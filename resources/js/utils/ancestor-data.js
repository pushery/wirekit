/**
 * `$wkAncestorData(selector, key)` — read a data-attribute off the nearest
 * matching ancestor, or an empty string if there is none.
 *
 * Six sub-components (modal/header, drawer/header, alert-dialog/title,
 * alert-dialog/description, dropdown/panel, dropdown/trigger) need the id their
 * PARENT generated, and each one crawled for it by hand:
 *
 *     x-bind:id="$el.closest('[data-wk-title-id]')?.dataset.wkTitleId"
 *
 * Two problems with that, and the second is why it changed now.
 *
 * It is the same fragile traversal written out six times — a selector and a
 * dataset key that have to agree, with nothing checking that they do.
 *
 * And the optional chain does not parse under Alpine's CSP build, so every one
 * of those components was unavailable under a strict Content-Security-Policy.
 * The obvious rewrite is `closest(…) && closest(…).dataset.…`, which parses but
 * queries the DOM twice on every re-evaluation of a bound attribute. A magic
 * costs one registration and reads better than either.
 *
 * Returns '' rather than null/undefined deliberately: the callers bind it to
 * `id`, and an empty string leaves the attribute usefully blank instead of
 * rendering the word "undefined" into the DOM.
 */
export function ancestorData(el, selector, key) {
    if (! el || typeof el.closest !== 'function') {
        return '';
    }

    const ancestor = el.closest(selector);

    if (! ancestor || ! ancestor.dataset) {
        return '';
    }

    const value = ancestor.dataset[key];

    return value === undefined || value === null ? '' : value;
}

/** Register the magic on an Alpine instance. */
export function registerAncestorDataMagic(Alpine) {
    Alpine.magic('wkAncestorData', (el) => (selector, key) => ancestorData(el, selector, key));
}

export default registerAncestorDataMagic;
