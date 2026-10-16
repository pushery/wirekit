/**
 * `wirekit:focus-invalid`: the focus goes to the first field of the page that a response rejected.
 *
 * A page dispatches it after a save the server refused, from Livewire with
 * `$this->dispatch('wirekit:focus-invalid')`. The field is the first element in page order that
 * carries `aria-invalid="true"`, inside the element whose id the event names as `within` when it
 * names one. A rejected group, such as a list of radios, hands the focus to its first entry.
 *
 * What hides the field opens first. A collapsible card opens through `wirekit:card-open`, dispatched
 * from the field so that every card around it hears it, a wizard step through its wizard's `goTo()`,
 * and a findable panel (an accordion item, a collapsible, a tab) through `beforematch`, the event the
 * browser's find in page sends it. The focus then waits until the field takes it, a few frames at
 * most. A field that never shows, inside a closed dialog for example, passes it on to the next
 * rejected field, up to three.
 *
 * An event rather than something every response does: under `wire:model.live` a field turns
 * invalid while the reader types in it, and a focus that moved by itself would take them out of the
 * field they are in.
 *
 * Installed once per page, one listener on `window` that lives as long as the page.
 */
import { FOCUS_FIRST_TARGETS } from './focus-first.js';

/**
 * The installation is remembered on `window`, the one scope a second bundle on the page, or a second
 * evaluation of this one, shares with the first.
 */
const INSTALLED_FLAG = '__wirekitFocusInvalid';

/** A field a response rejected. */
export const REJECTED_FIELD = '[aria-invalid="true"]';

/** How many animation frames the focus waits for a field that is still hidden. */
const FRAMES_TO_WAIT = 10;

/** How many rejected fields are tried in turn while each one never shows. */
const FIELDS_TO_TRY = 3;

/** Whether the reader can reach the element: not a hidden input, not out of the tab order, not inert. */
function reachable(el) {
    return ! el.matches('input[type="hidden"], [tabindex="-1"], [aria-hidden="true"]') && el.closest('[inert]') === null;
}

/**
 * The rejected fields inside `root`, in page order, each as the element that takes the focus. An
 * element that takes no focus itself, a group or a wrapper, hands it to the first entry inside it.
 *
 * @param {ParentNode} root
 * @returns {HTMLElement[]}
 */
export function rejectedFields(root) {
    const fields = [];

    for (const candidate of root.querySelectorAll(REJECTED_FIELD)) {
        if (! reachable(candidate)) {
            continue;
        }

        const field = candidate.matches(FOCUS_FIRST_TARGETS)
            ? candidate
            : Array.from(candidate.querySelectorAll(FOCUS_FIRST_TARGETS)).find(reachable);

        if (field && ! fields.includes(field)) {
            fields.push(field);
        }
    }

    return fields;
}

/**
 * The first rejected field inside `root`, in page order, or null.
 *
 * @param {ParentNode} root
 * @returns {HTMLElement|null}
 */
export function firstRejectedField(root) {
    return rejectedFields(root)[0] ?? null;
}

/**
 * Open everything that hides the field: every collapsible card around it, the step of a wizard that
 * holds it, and every findable panel, which opens on `beforematch` as it does for find in page.
 *
 * @param {HTMLElement} field
 */
export function revealField(field) {
    field.dispatchEvent(new CustomEvent('wirekit:card-open', { bubbles: true }));

    for (let node = field.parentElement; node; node = node.parentElement) {
        if (node.hasAttribute('hidden') || node.style.display === 'none') {
            showWizardStep(node);
            node.dispatchEvent(new Event('beforematch'));
        }
    }
}

/**
 * A hidden wizard step shows through its wizard's `goTo()`. A step is no findable panel: find in
 * page never opens one, and a wizard moves between steps only when asked. Going back is never
 * gated, and a step further on holds a field the server refused, which the reader has to correct.
 *
 * @param {HTMLElement} node
 */
function showWizardStep(node) {
    const index = Number(node.getAttribute('data-wk-wizard-step'));
    const Alpine = node.ownerDocument && node.ownerDocument.defaultView ? node.ownerDocument.defaultView.Alpine : undefined;

    if (! Number.isInteger(index) || index < 1 || ! Alpine || typeof Alpine.$data !== 'function') {
        return;
    }

    const wizard = Alpine.$data(node);

    if (wizard && typeof wizard.goTo === 'function') {
        wizard.goTo(index);
    }
}

/**
 * Focus the field, and try again on the next frames while it does not take the focus yet: a panel
 * that just opened shows its content on Alpine's next render. `onGiveUp` runs once the frames are
 * spent and the field still has no focus.
 *
 * @param {HTMLElement} field
 * @param {number} framesLeft
 * @param {(callback: () => void) => void} nextFrame
 * @param {() => void} [onGiveUp]
 */
export function focusWhenReady(field, framesLeft, nextFrame, onGiveUp = () => {}) {
    field.focus();

    if (field.ownerDocument.activeElement === field) {
        return;
    }

    if (framesLeft <= 0) {
        onGiveUp();

        return;
    }

    nextFrame(() => focusWhenReady(field, framesLeft - 1, nextFrame, onGiveUp));
}

/**
 * Reveal the first field and focus it. One that never shows, inside a closed dialog for example,
 * passes the focus on to the next field.
 *
 * @param {HTMLElement[]} fields
 * @param {(callback: () => void) => void} nextFrame
 */
function focusFirstThatShows(fields, nextFrame) {
    const [field, ...rest] = fields;

    if (! field) {
        return;
    }

    revealField(field);
    // After Alpine's flush, which the opening queued as a microtask of its own.
    queueMicrotask(() => focusWhenReady(field, FRAMES_TO_WAIT, nextFrame, () => focusFirstThatShows(rest, nextFrame)));
}

/**
 * Answer `wirekit:focus-invalid` on this page.
 *
 * @param {Window} [win]
 */
export function installFocusInvalid(win = typeof window === 'undefined' ? undefined : window) {
    if (! win || win[INSTALLED_FLAG]) {
        return;
    }

    win[INSTALLED_FLAG] = true;

    const nextFrame = typeof win.requestAnimationFrame === 'function'
        ? (callback) => win.requestAnimationFrame(callback)
        : (callback) => setTimeout(callback, 16);

    win.addEventListener('wirekit:focus-invalid', (event) => {
        const detail = event && event.detail && typeof event.detail === 'object' ? event.detail : {};

        // Livewire sends the event after it has written the response's markup, except through a view
        // transition (`wire:transition`), which writes it a frame or two later. The search waits for
        // a rejected field the same few frames.
        const find = (framesLeft) => {
            const root = typeof detail.within === 'string' && detail.within !== ''
                ? win.document.getElementById(detail.within)
                : win.document;
            const fields = root ? rejectedFields(root).slice(0, FIELDS_TO_TRY) : [];

            if (fields.length > 0) {
                focusFirstThatShows(fields, nextFrame);

                return;
            }

            if (framesLeft > 0) {
                nextFrame(() => find(framesLeft - 1));
            }
        };

        find(FRAMES_TO_WAIT);
    });
}
