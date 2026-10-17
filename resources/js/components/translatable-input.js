/**
 * A value in several languages, `<x-wirekit::translatable-input>`.
 *
 * The primary language's field, and under it one chip per further language with its state:
 * translated, missing, or rejected by the server. Up to the component's inline limit every language
 * has its field on screen; above it a chip opens its language and closes the one open before, and
 * "Show all languages" opens all of them.
 *
 * The state is read from the fields themselves. A field's value is written by `wire:model` after
 * this scope starts, and by the server after a request without an `input` event, so the fields are
 * read a tick after init, on every `input`, and after every Livewire request that succeeded. A field
 * the server rejected carries `aria-invalid="true"`, and its row stays open so the error is seen.
 *
 * Cleanup contract: the Livewire hook is held in `_unhookCommit` and released in `destroy()`; its
 * callback checks `_root` before it reads.
 */

/**
 * @param {object} config
 * @param {string} config.primary - The code of the primary language.
 * @param {string[]} config.locales - Every language's code, the primary one first.
 * @param {Object<string, string>} config.names - Each language's name, for the chips.
 * @param {boolean} config.collapsed - Whether the further languages open one at a time.
 * @param {string} config.placeholder - The primary field's own placeholder.
 * @param {{ translated: string, missing: string, error: string }} config.states - The state words.
 */
export default function wirekitTranslatableInput(config = {}) {
    const primary = String(config.primary ?? '');
    const locales = Array.isArray(config.locales) ? config.locales.map(String) : [];
    const names = config.names && typeof config.names === 'object' ? config.names : {};
    const states = config.states && typeof config.states === 'object' ? config.states : {};

    return {
        values: Object.fromEntries(locales.map((code) => [code, ''])),
        errors: Object.fromEntries(locales.map((code) => [code, false])),
        opened: [],
        all: false,
        collapsed: config.collapsed === true,
        _root: null,
        _unhookCommit: null,

        init() {
            this._root = this.$root;

            // `wire:model` writes the values while Alpine walks the fields below this scope.
            this.$nextTick(() => this.read());

            if (typeof window !== 'undefined' && window.Livewire?.hook) {
                this._unhookCommit = window.Livewire.hook('commit', ({ succeed }) => {
                    succeed(() => queueMicrotask(() => {
                        if (this._root) {
                            this.read();
                        }
                    }));
                });
            }
        },

        destroy() {
            if (typeof this._unhookCommit === 'function') {
                this._unhookCommit();
            }

            this._unhookCommit = null;
            this._root = null;
        },

        /** The field of a language, or null. */
        field(code) {
            return this._root?.querySelector?.(`[data-wk-translation-field="${code}"]`) ?? null;
        },

        /** Read every field's value and whether the server rejected it. */
        read() {
            for (const code of locales) {
                const field = this.field(code);

                if (! field) {
                    continue;
                }

                this.values[code] = typeof field.value === 'string' ? field.value : '';
                this.errors[code] = field.getAttribute('aria-invalid') === 'true';
            }
        },

        /** Follow the reader's typing; the event names its language through the field. */
        typed(event) {
            const code = event?.target?.getAttribute?.('data-wk-translation-field');

            if (code && Object.prototype.hasOwnProperty.call(this.values, code)) {
                this.values[code] = typeof event.target.value === 'string' ? event.target.value : '';
            }
        },

        /** 'error', 'translated' or 'missing'. */
        state(code) {
            if (this.errors[code]) {
                return 'error';
            }

            return String(this.values[code] ?? '').trim() !== '' ? 'translated' : 'missing';
        },

        /** The chip's accessible name: the language and its state in words. */
        chipLabel(code) {
            const name = String(names[code] ?? code);
            const word = String(states[this.state(code)] ?? this.state(code));

            return `${name}: ${word}`;
        },

        /** A missing translation shows the primary value, or the primary placeholder while it is empty. */
        placeholderFor(code) {
            if (code === primary) {
                return String(config.placeholder ?? '');
            }

            const value = String(this.values[primary] ?? '').trim();

            return value !== '' ? value : String(config.placeholder ?? '');
        },

        /** Whether a language's field is on screen. */
        isOpen(code) {
            if (code === primary || ! this.collapsed || this.all) {
                return true;
            }

            return this.opened.includes(code) || this.errors[code] === true;
        },

        /** A chip: open its language, one at a time, or with every row open, focus its field. */
        choose(code) {
            if (! this.collapsed || this.all) {
                this.focusField(code);

                return;
            }

            if (this.opened.includes(code)) {
                this.opened = [];

                return;
            }

            this.opened = [code];
            this.$nextTick(() => this.focusField(code));
        },

        /** Open a row that something outside asked to show, such as `wirekit:focus-invalid`. */
        reveal(code) {
            if (! this.opened.includes(code)) {
                this.opened = [...this.opened, code];
            }
        },

        toggleAll() {
            this.all = ! this.all;

            if (! this.all) {
                this.opened = [];
            }
        },

        focusField(code) {
            this.field(code)?.focus?.();
        },
    };
}
