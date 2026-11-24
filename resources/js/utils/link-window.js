/** The links a click can follow: an `a` and the `area` of an image map, each with an address. */
export const WK_CLICKED_LINK_SELECTOR = 'a[href], area[href]';

/**
 * The link a click lands on, the element clicked or one around it, or null for a click on no link.
 *
 * @param {EventTarget|null|undefined} target the click's `event.target`
 * @returns {Element|null}
 */
export function clickedLink(target) {
    return typeof target?.closest === 'function' ? target.closest(WK_CLICKED_LINK_SELECTOR) : null;
}

/**
 * The window a click on a link opens its address in, as the browser picks it.
 *
 * A link without a `target` of its own opens where the page's first `<base target>` says, and an
 * empty `target=""` of its own is this window. The keywords count in any case, as the browser
 * reads them. `_top` and `_parent` name this window on a page no frame holds, as `_self` does, and
 * the window above in a frame. Any other name, `_blank` among them, is a window of its own.
 *
 * `window` and `document` are read only where they exist: without them the page counts as one no
 * frame holds, and no `<base target>` applies.
 *
 * @param {Element} link
 * @returns {'_self'|'_top'|'_parent'|'_blank'}
 */
export function windowTheLinkOpens(link) {
    const own = link.getAttribute('target');
    const doc = typeof document === 'undefined' ? null : document;
    const base = own === null && doc !== null && typeof doc.querySelector === 'function' ? doc.querySelector('base[target]') : null;
    const baseTarget = typeof base?.getAttribute === 'function' ? base.getAttribute('target') : null;
    const target = String(own ?? baseTarget ?? '').trim().toLowerCase();

    if (target === '' || target === '_self') {
        return '_self';
    }

    if (target === '_top' || target === '_parent') {
        const win = typeof window === 'undefined' ? null : window;
        const above = win === null ? null : (target === '_top' ? win.top : win.parent);

        return above === null || above === undefined || above === win ? '_self' : target;
    }

    return '_blank';
}
