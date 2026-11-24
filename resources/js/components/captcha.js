/**
 * Captcha — a provider's widget whose token travels in a field of the kit's own.
 *
 * `<x-wirekit::captcha>` renders the provider's widget under `wire:ignore`, so a Livewire render
 * leaves it alone, and beside it a hidden field that carries the caller's `wire:model` and `name`.
 * The token goes into that field with an `input` event, so a `wire:model` binding, a `.live` one
 * and a plain form post all receive it. An expired token, an error and a timeout empty the field
 * the same way, so an old token never stays in the property.
 *
 * Two providers:
 *   - `turnstile`: Cloudflare's widget, rendered explicitly with its site key. It writes no field
 *     of its own (`response-field: false`) and takes the page's mode as `light` or `dark`, since
 *     its `auto` follows the system rather than the page.
 *   - `altcha`: the `altcha-widget` element, a proof of work against a challenge the application
 *     serves. Its `statechange` carries the payload once the state is `verified`.
 *
 * The provider's script loads once per window and address (`utils/script-once.js`), unless the
 * page has it already: Turnstile's `window.turnstile`, ALTCHA's element. A script that does not
 * load leaves the captcha without a token and says so in the console.
 *
 * With `defer`, nothing loads from the provider while the page is only read. The first click,
 * change of a field or key in the form around the captcha, or a click in the captcha's own group
 * where no form holds it, starts the check, once. A focus does not: a reader who moves through the
 * page with Tab focuses every field on the way.
 *
 * A token can be checked once. After the server has checked it, `wirekit:captcha-reset` on
 * `window`, with `{ name }` for one captcha or without a name for every one, starts a new check
 * and empties the field. A name sent as a number names the field of the same digits.
 *
 * The field is one the reader never touches, so each new or emptied token is committed as a
 * native field commits a change: `wire:model.change` and `wire:model.blur` hear it as well
 * (`utils/model-events.js`).
 *
 * Cleanup: the reset listener, the model events, the listeners that wait for the reader, the
 * observer of the page's mode and the widget, released in `destroy()`.
 */

import { themeModeOf } from '../utils/chart-theme-colors.js';
import { watchModelEvents } from '../utils/model-events.js';
import { namesSomething, sameName } from '../utils/same-name.js';
import { loadScriptOnce } from '../utils/script-once.js';

export const WK_CAPTCHA_RESET_EVENT = 'wirekit:captcha-reset';

/** What counts as the reader using the form, for a captcha with `defer`. */
export const WK_CAPTCHA_READER_EVENTS = ['click', 'input', 'keydown'];

/**
 * Keys that move through the page rather than use the form: Tab and Escape, and the modifier keys
 * on their own, which a combination such as Shift+Tab sends first.
 */
const WK_CAPTCHA_PASSING_KEYS = new Set([
    'Tab', 'Escape', 'Shift', 'Control', 'Alt', 'AltGraph', 'Meta', 'CapsLock', 'Fn', 'FnLock',
    'Hyper', 'Super', 'Symbol', 'SymbolLock', 'NumLock', 'ScrollLock',
]);

/**
 * Whether the provider's script has run on the page already.
 *
 * @param {'turnstile'|'altcha'} provider
 * @param {Window} win
 * @returns {boolean}
 */
function providerIsThere(provider, win) {
    if (provider === 'altcha') {
        return typeof win.customElements?.get === 'function' && Boolean(win.customElements.get('altcha-widget'));
    }

    return typeof win.turnstile?.render === 'function';
}

/**
 * Whether an event of `WK_CAPTCHA_READER_EVENTS` is the reader's use of the form. Only an event the
 * browser sends for the reader counts, never one a script dispatches, such as the `input` a
 * component sends when it fills its own field. A click and a change of a field are a use. A key is
 * one unless it passes through the page, or comes with Control, Meta or Alt as a shortcut of the
 * browser, such as find in page; a paste or a character typed with such a key changes the field,
 * and that change counts.
 *
 * @param {Event} event
 * @returns {boolean}
 */
function usesTheForm(event) {
    if (event?.isTrusted !== true) {
        return false;
    }

    if (event.type !== 'keydown') {
        return true;
    }

    if (event.ctrlKey || event.metaKey || event.altKey) {
        return false;
    }

    return ! WK_CAPTCHA_PASSING_KEYS.has(event.key);
}

/**
 * @param {Object} config
 * @param {'turnstile'|'altcha'} config.provider
 * @param {string} config.script       the address of the provider's script
 * @param {string} [config.name]       the field's name, which `wirekit:captcha-reset` can name
 * @param {string} [config.siteKey]    Turnstile: the widget's site key
 * @param {string} [config.challenge]  ALTCHA: the address of a challenge, or the challenge as JSON
 * @param {string} [config.size]       Turnstile: `normal`, `flexible` or `compact`
 * @param {string} [config.language]   the widget's language; empty leaves it to the provider
 * @param {string} [config.action]     Turnstile: a label in its analytics
 * @param {boolean} [config.defer]     wait for the reader to use the form before loading anything
 */
export default function wirekitCaptcha(config = {}) {
    return {
        provider: config.provider === 'altcha' ? 'altcha' : 'turnstile',
        _widgetId: null,
        _widget: null,
        _onReset: null,
        _onState: null,
        _modelEvents: null,
        _theme: null,
        _themeObserver: null,
        _themeDebounce: null,
        _readerScope: null,
        _onReader: null,
        _gone: false,

        init() {
            this._onReset = (event) => {
                const name = event?.detail?.name;

                if (namesSomething(name) && ! sameName(name, config.name)) {
                    return;
                }

                this.reset();
            };
            window.addEventListener(WK_CAPTCHA_RESET_EVENT, this._onReset);

            this._modelEvents = watchModelEvents(this.$root, () => this.$refs?.field);

            if (config.defer) {
                this._waitForTheReader();
            } else {
                this._start();
            }
        },

        destroy() {
            this._gone = true;
            this._stopWaiting();
            window.removeEventListener(WK_CAPTCHA_RESET_EVENT, this._onReset);
            this._modelEvents?.dispose();
            this._modelEvents = null;
            this._themeObserver?.disconnect();
            this._themeObserver = null;
            clearTimeout(this._themeDebounce);

            // Turnstile calls no callback of the widget's when it removes one.
            if (this.provider === 'turnstile' && this._widgetId !== null) {
                window.turnstile?.remove?.(this._widgetId);
            }

            if (this._widget) {
                this._widget.removeEventListener('statechange', this._onState);
                this._widget.remove();
            }

            this._widgetId = null;
            this._widget = null;
        },

        /**
         * `defer`: the check starts with the reader's first click, change of a field or key in the
         * form around the captcha, or with a click in its group where no form holds it, so a page
         * that is only read contacts no provider. A focus is no use of the form, whether a Tab
         * brought it there or `autofocus` put it there before the captcha started.
         */
        _waitForTheReader() {
            const scope = this.$el?.closest?.('form') ?? this.$el?.closest?.('[data-wk-captcha]') ?? this.$el;

            if (! scope || typeof scope.addEventListener !== 'function') {
                this._start();

                return;
            }

            this._readerScope = scope;
            this._onReader = (event) => {
                if (! usesTheForm(event)) {
                    return;
                }

                this._stopWaiting();
                this._start();
            };

            // Passive: the captcha only notes the reader's event and never holds back what it does.
            for (const type of WK_CAPTCHA_READER_EVENTS) {
                scope.addEventListener(type, this._onReader, { passive: true });
            }
        },

        _stopWaiting() {
            if (! this._readerScope) {
                return;
            }

            for (const type of WK_CAPTCHA_READER_EVENTS) {
                this._readerScope.removeEventListener(type, this._onReader);
            }

            this._readerScope = null;
            this._onReader = null;
        },

        async _start() {
            if (! providerIsThere(this.provider, window)) {
                try {
                    await loadScriptOnce(config.script, { module: this.provider === 'altcha' });
                } catch (error) {
                    console.error('[wirekit] the captcha script did not load, so this captcha gives no token.', error);

                    return;
                }
            }

            // Taken off the page while its script loaded.
            if (this._gone) {
                return;
            }

            if (this.provider === 'altcha') {
                this._renderAltcha();
            } else {
                this._renderTurnstile();
            }
        },

        _renderTurnstile() {
            const turnstile = window.turnstile;

            if (typeof turnstile?.render !== 'function') {
                return;
            }

            const options = {
                sitekey: config.siteKey,
                theme: themeModeOf(this.$el),
                size: config.size || 'normal',
                'response-field': false,
                callback: (token) => this._write(typeof token === 'string' ? token : ''),
                'expired-callback': () => this._write(''),
                'error-callback': () => this._write(''),
                'timeout-callback': () => this._write(''),
            };

            if (config.language) {
                options.language = config.language;
            }

            if (config.action) {
                options.action = config.action;
            }

            this._theme = options.theme;
            this._widgetId = turnstile.render(this.$refs.widget, options);
            this._watchTheme();
        },

        /**
         * Turnstile takes its theme only when it draws, so a switch of the page's mode draws the
         * widget again in the new one. Only while the field holds no token: drawing it again
         * discards a token the reader already earned, so a widget that gave one keeps its look.
         * The mode sits on `<html>` or `<body>`, as the charts read it.
         */
        _watchTheme() {
            if (this._themeObserver || typeof MutationObserver !== 'function') {
                return;
            }

            this._themeObserver = new MutationObserver(() => {
                clearTimeout(this._themeDebounce);
                this._themeDebounce = setTimeout(() => this._followTheme(), 50);
            });

            const watched = { attributes: true, attributeFilter: ['class'] };

            this._themeObserver.observe(document.documentElement, watched);

            if (document.body) {
                this._themeObserver.observe(document.body, watched);
            }
        },

        _followTheme() {
            if (this._gone || this._widgetId === null || this.$refs.field?.value || themeModeOf(this.$el) === this._theme) {
                return;
            }

            window.turnstile?.remove?.(this._widgetId);
            this._widgetId = null;
            this._renderTurnstile();
        },

        _renderAltcha() {
            const widget = document.createElement('altcha-widget');

            widget.setAttribute('challenge', config.challenge ?? '');

            if (config.language) {
                widget.setAttribute('language', config.language);
            }

            this._onState = (event) => {
                const { state, payload } = event?.detail ?? {};

                if (state === 'verified' && typeof payload === 'string') {
                    this._write(payload);
                } else if (state !== 'verifying') {
                    this._write('');
                }
            };
            widget.addEventListener('statechange', this._onState);

            this.$refs.widget.appendChild(widget);
            this._widget = widget;
        },

        /** A new check, and an empty field until it gives a token. */
        reset() {
            if (this.provider === 'turnstile' && this._widgetId !== null) {
                window.turnstile?.reset?.(this._widgetId);
            }

            if (this.provider === 'altcha' && typeof this._widget?.reset === 'function') {
                this._widget.reset();
            }

            this._write('');
        },

        _write(value) {
            const field = this.$refs.field;

            if (! field || field.value === value) {
                return;
            }

            field.value = value;
            field.dispatchEvent(new Event('input', { bubbles: true }));
            this._modelEvents?.commit();
        },
    };
}
