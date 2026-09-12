/**
 * Shared motion preference.
 *
 * Ten components asked `matchMedia('(prefers-reduced-motion: reduce)')` directly
 * and each got the same answer: the operating system's. That is one voice, and
 * an application can hold a preference of its own — a setting in an account, a
 * toggle in a preferences dialog — that had no way to be heard.
 *
 * A media query can express two states. This needs three, and the middle one is
 * the reason the helper exists:
 *
 *   data-reduce-motion="reduce"          reduce, whatever the OS says
 *   data-reduce-motion="no-preference"   do NOT reduce, even though the OS asks
 *   attribute absent                     follow the OS (the default, unchanged)
 *
 * The attribute is read BEFORE the media query, so an explicit choice always
 * wins over an inferred one. The CSS half of this reads the same attribute on
 * the same element, which is what keeps a component's JS decision (stop an
 * auto-advance, reveal a buffer at once) and its CSS decision (clamp a
 * transition) from disagreeing on the same page.
 */

/** The attribute an application sets on <html> to state its own preference. */
export const MOTION_ATTRIBUTE = 'data-reduce-motion';

/**
 * Should motion be reduced right now?
 *
 * @returns {boolean}
 */
export function prefersReducedMotion() {
    // Guard the whole path: this runs in a test environment and in SSR-adjacent
    // contexts where neither document nor matchMedia is guaranteed, and a
    // motion preference is never worth throwing over.
    //
    // The guard is on `getAttribute` being CALLABLE, not on `document` existing.
    // `?.` only stops at null or undefined, so a stub that defines
    // `document.documentElement` as a plain object sails past it and throws on the
    // call — which is exactly what the ESM harness hands a factory, deliberately, and
    // what the two chart adapters hit the moment they started watching this.
    const root = typeof document !== 'undefined' ? document.documentElement : null;
    const explicit = typeof root?.getAttribute === 'function'
        ? root.getAttribute(MOTION_ATTRIBUTE)
        : null;

    if (explicit === 'reduce') return true;
    if (explicit === 'no-preference') return false;

    // `typeof window`, not `window.matchMedia?.` — an undeclared identifier throws a
    // ReferenceError before optional chaining ever gets a value to test, so the `?.` here
    // protected against a missing METHOD and not against a missing window. The comment above
    // has promised "neither document nor matchMedia is guaranteed" since the helper was
    // written; this line was the half that did not keep it.
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
}

/**
 * Call back whenever the effective preference changes.
 *
 * Both sources can change while the page is open: the OS setting, and the
 * application's own attribute (a user flipping their preferences without a
 * reload). Watching only the media query would miss the second, which is
 * precisely the case this whole helper was added for.
 *
 * @param {(reduced: boolean) => void} onChange
 * @returns {() => void} unsubscribe
 */
export function watchReducedMotion(onChange) {
    let last = prefersReducedMotion();
    const emit = () => {
        const now = prefersReducedMotion();
        if (now !== last) {
            last = now;
            onChange(now);
        }
    };

    // Same guard as `prefersReducedMotion()` above, and for the same reason: this runs
    // wherever that does, and a watch is even less worth throwing over than a read.
    const query = typeof window !== 'undefined'
        ? window.matchMedia?.('(prefers-reduced-motion: reduce)')
        : null;
    query?.addEventListener?.('change', emit);

    // The attribute lives on <html>, so observe exactly that one attribute on
    // exactly that one node rather than the document — a document-wide observer
    // for a single attribute is a cost every page would pay forever.
    let observer = null;
    if (typeof MutationObserver !== 'undefined' && typeof document !== 'undefined') {
        observer = new MutationObserver(emit);
        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: [MOTION_ATTRIBUTE],
        });
    }

    return () => {
        query?.removeEventListener?.('change', emit);
        observer?.disconnect();
    };
}
