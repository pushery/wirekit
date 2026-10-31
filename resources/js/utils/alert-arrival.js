/**
 * An alert that a Livewire render adds to the page is announced.
 *
 * `<x-wirekit::alert>` is a status message: `role="status"`, or `role="alert"` for `danger`. The
 * usual message after an action is an alert the next render brings, `@if ($notice)` around it,
 * and Livewire's morph inserts it into the page together with its text. A screen reader announces
 * reliably only text that appears in a region that was already there (WCAG 4.1.3, technique
 * ARIA22). An inserted `role="alert"` is announced by the browsers as it arrives; an inserted
 * `role="status"` is not, so the reader who just submitted a form hears nothing of its result.
 *
 * This module gives the page one polite status region under `<body>`, in place from the start,
 * and hands it the words of every polite alert a render adds: the visually hidden intent, the
 * title and the message, not its actions or its dismiss button. An alert that arrives inside a
 * live region of the page's own is left to that region, and one rendered with the page says
 * nothing, as a status already there when the page loads is not news.
 *
 * The words go in a moment after the region is emptied, so a second "Saved." after the first is
 * announced as well, and leave it a while later, so a reader moving through the page does not
 * come across them there. Alerts a single render adds are said together.
 *
 * The region survives a page a `wire:navigate` swapped in, and an overlay that makes the rest of
 * the page inert keeps it reachable (`PAGE_INERT_KEEP` in `utils/overlay.js`). Installed once per
 * page; outside Livewire nothing renders an alert after the page loaded, and nothing is hooked.
 */

export const WK_ALERT_ATTRIBUTE = 'data-wk-alert';
export const WK_ALERT_TEXT_ATTRIBUTE = 'data-wk-alert-text';
export const WK_ALERT_REGION_ATTRIBUTE = 'data-wk-alert-announcer';

/** How long after emptying the region the words go in, in milliseconds. */
export const ALERT_ANNOUNCE_DELAY = 100;

/** How long the words stay in the region, in milliseconds. */
export const ALERT_ANNOUNCE_HOLD = 7000;

/** Remembered on `window`, the one scope every bundle on the page shares (utils/cleared-field.js). */
const INSTALLED_FLAG = '__wirekitAlertArrival';

// Hidden from sight and still read: the box of the `sr-only` utility, written through the style
// object, which a Content-Security-Policy without 'unsafe-inline' allows where a `style` attribute
// would be refused.
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

// A live region of the page's own around an alert speaks for it.
const LIVE_REGION = '[role="status"], [role="alert"], [role="log"], [aria-live="polite"], [aria-live="assertive"]';

let pending = [];
let writeTimer = null;
let clearTimer = null;

/**
 * The page's alert region: one polite status region under `<body>`, created when there is none.
 * Null for a document without a body.
 *
 * @param {Document} doc
 * @returns {HTMLElement|null}
 */
export function alertRegion(doc) {
    if (! doc || ! doc.body) {
        return null;
    }

    let region = doc.querySelector(`[${WK_ALERT_REGION_ATTRIBUTE}]`);

    if (! region) {
        region = doc.createElement('div');
        region.setAttribute(WK_ALERT_REGION_ATTRIBUTE, '');
        region.setAttribute('role', 'status');
        region.setAttribute('aria-live', 'polite');
        Object.assign(region.style, HIDDEN);
        doc.body.appendChild(region);
    }

    return region;
}

/**
 * The words a screen reader would hear for the alert: its parts marked `data-wk-alert-text`, in
 * order, each with its white space folded.
 *
 * @param {Element} alert
 * @returns {string}
 */
export function alertWords(alert) {
    return Array.from(alert.querySelectorAll(`[${WK_ALERT_TEXT_ATTRIBUTE}]`))
        .filter((part) => part.closest(`[${WK_ALERT_ATTRIBUTE}]`) === alert)
        .map((part) => (part.textContent ?? '').replace(/\s+/g, ' ').trim())
        .filter((words) => words !== '')
        .join(' ');
}

/**
 * Whether the region should say the alert: a polite alert that shows, outside any live region of
 * the page's own.
 *
 * @param {Element} alert
 * @returns {boolean}
 */
export function relays(alert) {
    if (alert.getAttribute('role') !== 'status') {
        return false;
    }

    if (alert.parentElement && alert.parentElement.closest(LIVE_REGION)) {
        return false;
    }

    return alert.getClientRects().length > 0;
}

/**
 * The alerts in an element a render added: the element itself and the alerts inside it.
 *
 * @param {Element} el
 * @returns {Element[]}
 */
export function alertsIn(el) {
    if (! el || typeof el.matches !== 'function') {
        return [];
    }

    const found = el.matches(`[${WK_ALERT_ATTRIBUTE}]`) ? [el] : [];

    return found.concat(Array.from(el.querySelectorAll(`[${WK_ALERT_ATTRIBUTE}]`)));
}

/**
 * Hand the words of the polite alerts in an element a render added to the region.
 *
 * @param {Element} el
 */
export function announceArrival(el) {
    const words = alertsIn(el).filter(relays).map(alertWords).filter((text) => text !== '');

    if (words.length === 0) {
        return;
    }

    const region = alertRegion(el.ownerDocument);

    if (! region) {
        return;
    }

    pending.push(...words);
    region.textContent = '';
    clearTimeout(clearTimer);
    clearTimeout(writeTimer);

    writeTimer = setTimeout(() => {
        const said = pending.join(' ');

        pending = [];
        region.textContent = said;

        clearTimer = setTimeout(() => {
            if (region.textContent === said) {
                region.textContent = '';
            }
        }, ALERT_ANNOUNCE_HOLD);
    }, ALERT_ANNOUNCE_DELAY);
}

/** @param {{hook: Function}} livewire */
function hookLivewire(livewire) {
    alertRegion(document);

    livewire.hook('morph.added', ({ el }) => announceArrival(el));

    // A navigation swaps the body, region included, so the next page gets its own before any
    // render can add an alert to it.
    document.addEventListener('livewire:navigated', () => alertRegion(document));
}

/**
 * Install the relay once per page. Safe to call from every bundle, and from a bundle evaluated
 * again: every call after the first does nothing.
 */
export function installAlertArrival() {
    if (typeof document === 'undefined' || typeof window === 'undefined' || window[INSTALLED_FLAG]) {
        return;
    }

    window[INSTALLED_FLAG] = true;

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
