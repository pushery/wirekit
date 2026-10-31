/**
 * `data-wk-submit-once`: a form that sends itself once.
 *
 * A form that Livewire does not send is an ordinary submission: the browser sends it and shows the
 * page that answers. A second press before that page arrives sends the form again, and the browser
 * shows the answer to the second request. Where the server takes a request only once, a code that
 * is good for one sign-in or a mail whose link the next mail replaces, the second request undoes
 * the first.
 *
 * On a form with the attribute the first submission goes, and every further one is held back until
 * the page changes:
 *
 *   - the form and the button that sent it carry `aria-busy="true"`. Nothing is disabled, so the
 *     focus stays on the button, where a disabled one would hand it to the page;
 *   - a submission another script has taken over, as Livewire's `wire:submit` does, holds nothing,
 *     and one that closes a dialog (`method="dialog"`, or a button's `formmethod="dialog"`) is left
 *     alone, also while the form is held: a Cancel button closes its dialog after a download;
 *   - the form takes a submission again when the browser shows the page from its back-forward
 *     cache, and after `data-wk-submit-once-release` milliseconds for an answer that leaves the
 *     page as it is, a download for example: 10 000 when the form names none, and `0` waits for
 *     the page alone.
 *
 * Without the script the form is an ordinary form.
 *
 * Installed once per page: one listener for `submit` on `window`, the last stop of the event, so
 * every script on its way has had its say, and one for `pageshow`.
 */

import { timerDelay } from './timer-delay.js';

/**
 * The installation is remembered on `window`, the one scope a second bundle on the page, or a second
 * evaluation of this one, shares with the first.
 */
const INSTALLED_FLAG = '__wirekitSubmitOnce';

/** The attribute that asks for it, on a `<form>`. */
export const WK_SUBMIT_ONCE_ATTRIBUTE = 'data-wk-submit-once';

/** How long a held form waits for the page to change, in milliseconds. */
export const WK_SUBMIT_ONCE_RELEASE_ATTRIBUTE = 'data-wk-submit-once-release';

/** The wait when the form names none. */
export const DEFAULT_RELEASE_MS = 10000;

/**
 * The milliseconds after which a held form takes a submission again, `0` for never. A value that is
 * no number of milliseconds is the default.
 *
 * @param {Element} form
 * @returns {number}
 */
export function releaseAfter(form) {
    const value = form.getAttribute(WK_SUBMIT_ONCE_RELEASE_ATTRIBUTE);

    if (value === null || value.trim() === '') {
        return DEFAULT_RELEASE_MS;
    }

    const ms = Number(value);

    return Number.isFinite(ms) && ms >= 0 ? Math.round(ms) : DEFAULT_RELEASE_MS;
}

/**
 * Whether the submission closes a dialog, which sends nothing and leaves the page as it is. The
 * button's `formmethod` wins over the form's `method`, as it does for the browser.
 *
 * @param {Element} form
 * @param {Element|null} submitter
 * @returns {boolean}
 */
export function closesADialog(form, submitter) {
    const method = submitter && submitter.hasAttribute('formmethod')
        ? submitter.getAttribute('formmethod')
        : form.getAttribute('method');

    return (method ?? '').trim().toLowerCase() === 'dialog';
}

/**
 * Hold every form with the attribute on this page to one submission.
 *
 * @param {Window} [win]
 */
export function installSubmitOnce(win = typeof window === 'undefined' ? undefined : window) {
    if (! win || win[INSTALLED_FLAG]) {
        return;
    }

    win[INSTALLED_FLAG] = true;

    /** @type {Map<Element, {timer: unknown, busy: Element[]}>} the forms held, and what each set busy */
    const held = new Map();

    const release = (form) => {
        const entry = held.get(form);

        if (! entry) {
            return;
        }

        held.delete(form);

        if (entry.timer !== null) {
            win.clearTimeout(entry.timer);
        }

        entry.busy.forEach((el) => el.removeAttribute('aria-busy'));
    };

    win.addEventListener('submit', (event) => {
        const form = event.target;

        if (! form || typeof form.hasAttribute !== 'function' || ! form.hasAttribute(WK_SUBMIT_ONCE_ATTRIBUTE)) {
            return;
        }

        const submitter = event.submitter ?? null;

        // Closing a dialog sends nothing, so it goes while the form is held, and the hold stays.
        if (closesADialog(form, submitter)) {
            return;
        }

        if (held.has(form)) {
            event.preventDefault();

            return;
        }

        if (event.defaultPrevented) {
            return;
        }

        // Only what this sets busy is set back, so an `aria-busy` the page wrote itself stays.
        const busy = [form, submitter].filter((el) => el && el.getAttribute('aria-busy') !== 'true');
        busy.forEach((el) => el.setAttribute('aria-busy', 'true'));

        const ms = releaseAfter(form);

        held.set(form, { busy, timer: ms > 0 ? win.setTimeout(() => release(form), timerDelay(ms)) : null });
    });

    // A page the browser shows again from its back-forward cache is the page before the
    // submission, and its forms take one again.
    win.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            [...held.keys()].forEach(release);
        }
    });
}
