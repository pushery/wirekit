/**
 * A token a button puts into a field, in the browser, without a request to the server.
 *
 * `x-wk-insert-token` goes on the group of buttons an input carries under it (`tokens`). A
 * button's `data-wk-insert-token` is what it inserts: at the caret the reader left in the field,
 * over the text they selected there, or at the end of the field before they placed a caret. The
 * field hears an `input` and a `change` event, so `wire:model` and `x-model` take the new value at
 * once; bound with `.blur`, it travels with the form's next submit or the next time the field is
 * left. The focus stays on the button, and the caret moves past what was inserted, so a second
 * token follows the first.
 *
 * Only a caret the reader placed counts. Tabbing into a field selects its text in some browsers,
 * and a token would then replace everything typed so far, so the keys that move the focus or
 * change nothing in the field leave the caret where it was.
 *
 * A token that would take the value past the field's `maxlength` is not inserted, as typing stops
 * there, and a field that is disabled or read-only takes none.
 *
 * The directive takes no expression, so Alpine's CSP build runs it as it is. Everything it reads
 * is on the elements: `data-wk-insert-for` names the field, `data-wk-insert-token` the token.
 */
import { controlIsDisabled } from './fieldset-disabled.js';

/** Keys that leave the caret where the reader had it, the focus moving away and back included. */
const KEYS_THAT_KEEP_THE_CARET = new Set(['Tab', 'Shift', 'Control', 'Alt', 'Meta', 'Escape', 'CapsLock']);

/**
 * The field's value with the token inserted, and the caret after it; null where the token would
 * take the value past the field's limit.
 *
 * @param {unknown} value the field's value
 * @param {string} token
 * @param {number|null} start the caret, or the start of a selection; null for the end of the value
 * @param {number|null} end the end of a selection; null for the caret alone
 * @param {number|null} max the field's limit, or null without one
 * @returns {{value: string, caret: number}|null}
 */
export function valueWithToken(value, token, start, end, max) {
    const text = typeof value === 'string' ? value : '';
    const from = Number.isInteger(start) ? Math.min(Math.max(start, 0), text.length) : text.length;
    const to = Number.isInteger(end) ? Math.min(Math.max(end, from), text.length) : from;
    const next = text.slice(0, from) + token + text.slice(to);

    if (Number.isInteger(max) && max >= 0 && next.length > max) {
        return null;
    }

    return { value: next, caret: from + token.length };
}

/**
 * Register the `x-wk-insert-token` directive. It goes on the group of buttons, which names its
 * field in `data-wk-insert-for`.
 *
 * Idempotent: a second bundle registers the same handler.
 *
 * @param {object} Alpine
 */
export function registerInsertTokenDirective(Alpine) {
    Alpine.directive('wk-insert-token', (el, directive, { cleanup }) => {
        const field = typeof document !== 'undefined' ? document.getElementById(el.getAttribute('data-wk-insert-for') || '') : null;

        if (! field) {
            return;
        }

        // Where the reader left the caret, or null before they placed one.
        let caret = null;

        const remember = () => {
            if (typeof field.selectionStart === 'number') {
                caret = { start: field.selectionStart, end: typeof field.selectionEnd === 'number' ? field.selectionEnd : field.selectionStart };
            }
        };

        const onKeyup = (event) => {
            if (! KEYS_THAT_KEEP_THE_CARET.has(event.key)) {
                remember();
            }
        };

        const onClick = (event) => {
            const button = event.target && typeof event.target.closest === 'function'
                ? event.target.closest('[data-wk-insert-token]')
                : null;

            // Disabled includes a fieldset around the field (utils/fieldset-disabled.js).
            if (! button || ! el.contains(button) || controlIsDisabled(field) || field.readOnly) {
                return;
            }

            const token = button.getAttribute('data-wk-insert-token') || '';

            if (token === '') {
                return;
            }

            const next = valueWithToken(field.value, token, caret ? caret.start : null, caret ? caret.end : null, field.maxLength);

            if (! next) {
                return;
            }

            field.value = next.value;
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));

            // After the events: a listener of ours on `input` reads the caret the browser moved to
            // the end when the value was set.
            caret = { start: next.caret, end: next.caret };
        };

        field.addEventListener('click', remember);
        field.addEventListener('keyup', onKeyup);
        field.addEventListener('input', remember);
        el.addEventListener('click', onClick);

        cleanup(() => {
            field.removeEventListener('click', remember);
            field.removeEventListener('keyup', onKeyup);
            field.removeEventListener('input', remember);
            el.removeEventListener('click', onClick);
        });
    });
}
