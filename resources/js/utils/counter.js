/**
 * A field's character count, counted in the browser.
 *
 * A count under a field that only moved when the server answered needed `wire:model.live`: a
 * request on every pause in typing and a new render of the form. `x-wk-counter` counts where the
 * characters are typed, without a request, and holds two things apart. The visible count is short
 * ("12 / 60") and hidden from a screen reader, which would otherwise read it out on every key. A
 * polite live region says it in words once typing pauses: what is left of a limit, how far past
 * it the text is, or the count itself, and with a sentence the developer wrote, that sentence,
 * except past the limit, which a sentence about what is left cannot say.
 *
 * It counts what `maxlength` counts in Chromium and Firefox, UTF-16 code units, so the number a
 * reader sees is the one the field stops at; an emoji can count as two.
 *
 * The directive takes no expression, so Alpine's CSP build runs it as it is. Everything it reads
 * is on the element: `data-wk-counter-for` names the field, `data-wk-counter-format` a sentence
 * with `:count`, `:max` and `:remaining`, `data-wk-counter-phrases` the translated plural forms
 * and `data-wk-counter-locale` the language that picks among them. The limit is read from the
 * field each time, so a `maxlength` that changes after the render counts too.
 */
import { pluralize } from './plural.js';

/** How long typing has to pause before the count is said. */
export const COUNTER_ANNOUNCE_DELAY = 500;

/**
 * The characters a value holds, as `maxlength` counts them.
 *
 * @param {unknown} value
 * @returns {number}
 */
export function characterCount(value) {
    return typeof value === 'string' ? value.length : 0;
}

/**
 * The visible text of the counter.
 *
 * @param {number} count
 * @param {number|null} max the field's limit, or null without one
 * @param {string|null} format a sentence with `:count`, `:max` and `:remaining`, or null
 * @returns {string}
 */
export function counterText(count, max, format) {
    if (typeof format === 'string' && format !== '') {
        // `:remaining` first, so a sentence never has the `:max` inside it replaced on its own. It
        // stops at 0: past the limit the counter's color says so, and a negative count reads as
        // a mistake.
        return format
            .split(':remaining').join(max === null ? '' : String(Math.max(0, max - count)))
            .split(':count').join(String(count))
            .split(':max').join(max === null ? '' : String(max));
    }

    return max === null ? String(count) : `${count} / ${max}`;
}

/**
 * What the live region says once typing pauses.
 *
 * @param {number} count
 * @param {number|null} max
 * @param {string|null} format
 * @param {{count?: Object, remaining?: Object, over?: Object}} phrases plural forms by sample count
 * @param {string} locale
 * @returns {string}
 */
export function counterAnnouncement(count, max, format, phrases, locale) {
    const forms = phrases || {};

    // Past the limit the kit's own words, whatever the sentence: a sentence about what is left
    // cannot say how far past the limit the text is.
    if (max !== null && count > max) {
        return pluralize(forms.over, count - max, locale);
    }

    if (typeof format === 'string' && format !== '') {
        return counterText(count, max, format);
    }

    return max === null
        ? pluralize(forms.count, count, locale)
        : pluralize(forms.remaining, max - count, locale);
}

/**
 * The limit a field carries, or null without one. `maxLength` is -1 on a field without the
 * attribute, and a negative or unreadable value is no limit.
 *
 * @param {HTMLInputElement|HTMLTextAreaElement} field
 * @returns {number|null}
 */
export function fieldLimit(field) {
    const max = Number(field && field.maxLength);

    return Number.isInteger(max) && max >= 0 ? max : null;
}

/**
 * Register the `x-wk-counter` directive. It goes on the counter's element, which holds a
 * `[data-wk-counter-text]` for the visible count and a `[data-wk-counter-announcer]` live region.
 *
 * Idempotent: a second bundle registers the same handler.
 *
 * @param {object} Alpine
 */
export function registerCounterDirective(Alpine) {
    Alpine.directive('wk-counter', (el, directive, { cleanup }) => {
        const field = typeof document !== 'undefined' ? document.getElementById(el.getAttribute('data-wk-counter-for') || '') : null;
        const text = el.querySelector('[data-wk-counter-text]');
        const announcer = el.querySelector('[data-wk-counter-announcer]');

        if (! field || ! text) {
            return;
        }

        const format = el.getAttribute('data-wk-counter-format');
        const locale = el.getAttribute('data-wk-counter-locale') || 'en';
        let phrases = {};

        try {
            phrases = JSON.parse(el.getAttribute('data-wk-counter-phrases') || '{}') || {};
        } catch {
            // Unreadable forms leave the number on its own, which still says the count.
            phrases = {};
        }

        let timer = null;

        const update = (announce) => {
            const count = characterCount(field.value);
            const max = fieldLimit(field);

            text.textContent = counterText(count, max, format);

            if (max !== null && count > max) {
                el.setAttribute('data-wk-counter-over', '');
            } else {
                el.removeAttribute('data-wk-counter-over');
            }

            if (! announce || ! announcer) {
                return;
            }

            clearTimeout(timer);
            timer = setTimeout(() => {
                timer = null;
                announcer.textContent = counterAnnouncement(characterCount(field.value), fieldLimit(field), format, phrases, locale);
            }, COUNTER_ANNOUNCE_DELAY);
        };

        const typed = () => update(true);
        // A value set from outside, a reset form or an answer from the server, is shown and not said.
        const set = () => update(false);
        const reset = () => setTimeout(set, 0);

        field.addEventListener('input', typed);
        field.addEventListener('change', set);

        const form = field.form || null;

        if (form) {
            form.addEventListener('reset', reset);
        }

        // Livewire writes a bound field's value without an input event, after a request.
        const livewire = typeof window !== 'undefined' ? window.Livewire : undefined;
        const unhook = livewire && typeof livewire.hook === 'function'
            ? livewire.hook('commit', ({ succeed }) => succeed(() => queueMicrotask(set)))
            : null;

        // Once now and once after the directives initialized beside this one, among them the
        // `wire:model` that writes the field's first value.
        set();
        queueMicrotask(set);

        cleanup(() => {
            clearTimeout(timer);
            field.removeEventListener('input', typed);
            field.removeEventListener('change', set);

            if (form) {
                form.removeEventListener('reset', reset);
            }

            if (typeof unhook === 'function') {
                unhook();
            }
        });
    });
}
