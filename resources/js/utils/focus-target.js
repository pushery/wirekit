/**
 * `wirekit:focus`: the focus goes to the element a response names.
 *
 * An action that removes the control the reader used, a delete in a row of a list or a rename that
 * puts a form in its place, leaves the focus on nothing, and the browser hands it to the start of
 * the page. The action names where it goes instead, from Livewire with
 * `$this->dispatch('wirekit:focus', target: 'tags-heading')`: the id of an element, or a CSS
 * selector for the first element that matches.
 *
 * The element is looked for once the response's markup is written. Livewire sends the event after
 * it has written it, except through a view transition (`wire:transition`), which writes it a frame
 * or two later, so the search waits the same few frames as `wirekit:focus-invalid`. What hides the
 * element opens first, as it does for a rejected field, and the focus then waits until the element
 * takes it.
 *
 * An element that takes no focus of its own, a heading or a list, gets `tabindex="-1"`, which lets
 * a script focus it without putting it in the tab order. A Livewire render removes an attribute its
 * template does not write, and the focus goes with it, so the template writes it; this is the
 * fallback for the response that names the element first.
 *
 * Installed once per page, one listener on `window` that lives as long as the page.
 */
import { focusWhenReady, revealField } from './focus-invalid.js';

/**
 * The installation is remembered on `window`, the one scope a second bundle on the page, or a second
 * evaluation of this one, shares with the first.
 */
const INSTALLED_FLAG = '__wirekitFocusTarget';

/** How many animation frames the search and the focus wait. */
const FRAMES_TO_WAIT = 10;

/**
 * The element `target` names in `doc`: the one with that id, or else the first that matches it as a
 * selector. A text that is neither, such as an id that starts with a digit written with `#`, names
 * nothing.
 *
 * @param {Document} doc
 * @param {unknown} target
 * @returns {HTMLElement|null}
 */
export function namedElement(doc, target) {
    if (typeof target !== 'string' || target.trim() === '') {
        return null;
    }

    const byId = doc.getElementById(target);

    if (byId) {
        return byId;
    }

    try {
        return doc.querySelector(target);
    } catch {
        return null;
    }
}

/**
 * The text a target names. Livewire's `dispatch('wirekit:focus', target: $tag->id)` sends an integer
 * as a number, and a number names the element whose id is its digits, as an event's name does in
 * `utils/same-name.js`. A blank text, and any other value, names nothing.
 *
 * @param {unknown} target
 * @returns {string|null}
 */
export function targetText(target) {
    if (typeof target === 'number') {
        return Number.isFinite(target) ? String(target) : null;
    }

    return typeof target === 'string' && target.trim() !== '' ? target : null;
}

/**
 * Give an element that takes no focus of its own `tabindex="-1"`. One that does, a button or a link,
 * keeps its place in the tab order, which an attribute of -1 would take from it.
 *
 * @param {HTMLElement} el
 * @returns {HTMLElement}
 */
export function letItTakeTheFocus(el) {
    if (el.tabIndex < 0 && ! el.hasAttribute('tabindex')) {
        el.setAttribute('tabindex', '-1');
    }

    return el;
}

/**
 * Answer `wirekit:focus` on this page.
 *
 * @param {Window} [win]
 */
export function installFocusTarget(win = typeof window === 'undefined' ? undefined : window) {
    if (! win || win[INSTALLED_FLAG]) {
        return;
    }

    win[INSTALLED_FLAG] = true;

    const nextFrame = typeof win.requestAnimationFrame === 'function'
        ? (callback) => win.requestAnimationFrame(callback)
        : (callback) => setTimeout(callback, 16);

    win.addEventListener('wirekit:focus', (event) => {
        const detail = event && event.detail && typeof event.detail === 'object' ? event.detail : {};
        const target = targetText(detail.target);

        // A target that names nothing does so on every frame, so there is nothing to wait for.
        if (target === null) {
            return;
        }

        const find = (framesLeft) => {
            const el = namedElement(win.document, target);

            if (el) {
                revealField(el);
                letItTakeTheFocus(el);
                // After Alpine's flush, which an opening queued as a microtask of its own.
                queueMicrotask(() => focusWhenReady(el, FRAMES_TO_WAIT, nextFrame));

                return;
            }

            if (framesLeft > 0) {
                nextFrame(() => find(framesLeft - 1));
            }
        };

        find(FRAMES_TO_WAIT);
    });
}
