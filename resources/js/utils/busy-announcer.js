/**
 * `x-wk-announce-busy`: says a control's wait in a status message once it has lasted a moment.
 *
 * A button with `loading` shows a spinner while its request runs, and the spinner is all a sighted
 * reader needs. A screen reader hears nothing of it: the spinner is hidden from assistive
 * technology, `disabled` takes the focus away, and `aria-busy` announces nothing on its own. The
 * directive watches the control's own spinner, the element marked `data-wk-busy-spinner`, and
 * while it shows for longer than BUSY_ANNOUNCE_DELAY it puts the control's `data-wk-busy-text`
 * into one polite status region for the page. When the spinner goes, the region is emptied. A
 * request that ends sooner says nothing, so a quick action does not talk over its own result.
 *
 * The spinner is the signal rather than `disabled` or `aria-busy`, because those mean busy only
 * while the request runs: a control the application disables for another reason, an invalid form
 * for instance, has the attribute and no spinner.
 *
 * Only a change is announced. A control rendered busy says nothing when the page loads, and speaks
 * when it next goes busy.
 *
 * The region is a child of `<body>`, created when the first such control starts, so it is in the
 * document before anything is written to it, and again after a navigation replaced the body. An
 * overlay that makes the rest of the page inert keeps it reachable (`PAGE_INERT_KEEP` in
 * `utils/overlay.js`).
 *
 * Lifecycle: one MutationObserver on the control and one pending timer, both released in the
 * directive's cleanup, which also takes the control's text out of the region. Nothing while Alpine
 * clones an element for a Livewire morph: the clone belongs to a template's document, which has no
 * body, and it is thrown away after the comparison.
 */

export const WK_BUSY_TEXT_ATTRIBUTE = 'data-wk-busy-text';
export const WK_BUSY_SPINNER_ATTRIBUTE = 'data-wk-busy-spinner';
export const WK_BUSY_REGION_ATTRIBUTE = 'data-wk-busy-announcer';

/** How long a wait runs before it is announced, in milliseconds. */
export const BUSY_ANNOUNCE_DELAY = 1000;

// Hidden from sight and still read: the one-pixel box of the `sr-only` utility, written through
// the style object, which a Content-Security-Policy without 'unsafe-inline' allows where a `style`
// attribute would be refused.
const HIDDEN = {
    position: 'absolute',
    width: '1px',
    height: '1px',
    padding: '0',
    margin: '-1px',
    overflow: 'hidden',
    clip: 'rect(0, 0, 0, 0)',
    whiteSpace: 'nowrap',
    border: '0',
};

// The controls whose text the region holds now. The region empties when the last of them settles,
// so one request that ends does not silence another that still runs.
const announcing = new Set();

/**
 * The page's busy region: one polite status region under `<body>`, created when there is none.
 * Null for a document without a body.
 *
 * @param {Document} doc
 * @returns {HTMLElement|null}
 */
export function busyRegion(doc) {
    if (! doc || ! doc.body) {
        return null;
    }

    let region = doc.querySelector(`[${WK_BUSY_REGION_ATTRIBUTE}]`);

    if (! region) {
        region = doc.createElement('div');
        region.setAttribute(WK_BUSY_REGION_ATTRIBUTE, '');
        region.setAttribute('role', 'status');
        region.setAttribute('aria-live', 'polite');
        Object.assign(region.style, HIDDEN);
        doc.body.appendChild(region);
    }

    return region;
}

/**
 * Whether the control shows its spinner now.
 *
 * @param {HTMLElement} control
 * @returns {boolean}
 */
export function showsItsSpinner(control) {
    const spinner = control.querySelector(`[${WK_BUSY_SPINNER_ATTRIBUTE}]`);

    return Boolean(spinner) && spinner.getClientRects().length > 0;
}

/**
 * Put the control's busy text into the region.
 *
 * @param {HTMLElement} control
 */
export function announceBusy(control) {
    const text = control.getAttribute(WK_BUSY_TEXT_ATTRIBUTE) || '';

    if (text === '') {
        return;
    }

    const region = busyRegion(control.ownerDocument);

    if (! region) {
        return;
    }

    announcing.add(control);
    region.textContent = text;
}

/**
 * Take the control's text out of the region, and empty the region when no other control waits.
 *
 * @param {HTMLElement} control
 */
export function releaseBusy(control) {
    if (! announcing.delete(control) || announcing.size > 0) {
        return;
    }

    const region = control.ownerDocument.querySelector(`[${WK_BUSY_REGION_ATTRIBUTE}]`);

    if (region) {
        region.textContent = '';
    }
}

/**
 * Register the `x-wk-announce-busy` directive.
 *
 * @param {object} Alpine
 */
export function registerAnnounceBusyDirective(Alpine) {
    const skipDuringClone = typeof Alpine.skipDuringClone === 'function' ? Alpine.skipDuringClone : (callback) => callback;

    Alpine.directive('wk-announce-busy', skipDuringClone((el, directive, { cleanup }) => {
        if (typeof MutationObserver === 'undefined') {
            return;
        }

        busyRegion(el.ownerDocument);

        // The state the control starts in is where changes are counted from, not a change.
        let busy = showsItsSpinner(el);
        let timer = 0;

        const settle = () => {
            const now = showsItsSpinner(el);

            if (now === busy) {
                return;
            }

            busy = now;
            clearTimeout(timer);
            timer = 0;

            if (busy) {
                timer = setTimeout(() => {
                    timer = 0;

                    if (busy) {
                        announceBusy(el);
                    }
                }, BUSY_ANNOUNCE_DELAY);
            } else {
                releaseBusy(el);
            }
        };

        // Livewire shows and hides the spinner through its `style`; a render that adds or removes
        // it, as the declarative `loading` does, changes the control's children.
        const observer = new MutationObserver(settle);
        observer.observe(el, { subtree: true, childList: true, attributes: true, attributeFilter: ['style', 'class', 'hidden'] });

        cleanup(() => {
            observer.disconnect();
            clearTimeout(timer);
            releaseBusy(el);
        });
    }));
}
