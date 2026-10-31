import { pressTakenFrom, watchPaletteHotkeys } from '../utils/palette-hotkeys.js';

/**
 * A shortcut as `aria-keyshortcuts` states it, such as `Meta+K` or `Control+Shift+P`, as the key
 * event a press of it reports. ARIA names keys by the key values of UI Events, which is what
 * `KeyboardEvent.key` holds, apart from the two keys it spells as words.
 *
 * @param {string} shortcut
 * @returns {object}
 */
const pressOf = (shortcut) => {
    const parts = shortcut.split('+');
    const name = parts.pop() ?? '';

    return {
        key: name === 'Plus' ? '+' : (name === 'Space' ? ' ' : name),
        code: '',
        metaKey: parts.includes('Meta'),
        ctrlKey: parts.includes('Control'),
        altKey: parts.includes('Alt'),
        shiftKey: parts.includes('Shift'),
        repeat: false,
        // Made on the page rather than in a field, which is where the statement holds.
        composedPath: () => [],
    };
};

/**
 * A field that opens the command palette and hands it what is typed.
 *
 * The field sits in a page's header and looks like a search input. A click on it, Enter or the
 * Down arrow opens the palette; so does the first key typed into it, and the palette opens with
 * that text in its own field. The field keeps the focus until the palette's trap moves it, so
 * every key typed in that moment lands here, and each one is handed over again: the palette
 * takes the text for as long as its own field does not have the focus.
 *
 * Text an input method is still composing is not handed over. Opening the palette moves the
 * focus, which would end the composition half-way; the text goes when the composition ends.
 *
 * Once the focus has moved into the palette the field empties itself, so it reads as an empty
 * search field again when the palette closes and the focus comes back to it. The opening palette
 * makes the page around it inert, and WebKit answers that by taking the focus from this field
 * before the palette's own field has it: for that moment the focus is nowhere, a key typed then
 * still reaches this field, and the text it holds is what the palette takes over. The field
 * therefore empties itself when the focus arrives somewhere, not when it leaves.
 *
 * The field states the palette's shortcut as `aria-keyshortcuts`, and while another palette on the
 * page has taken the key (`takesHotkey`), the key opens that palette and not this field's: the
 * field then states only the shortcuts that still open its palette, and the shortcut shown at its
 * end goes once none does. Both come back when the other palette leaves the page.
 *
 * The methods are named after what they do to the palette rather than `open`: a name Alpine finds
 * on `window` as well would turn a scope lost in a Livewire morph into a call to `window.open`.
 *
 * @param {object} [config]
 * @param {string|null} [config.for]  the `name` of the palette to open; without one the field
 *   opens every palette on the page that answers an unnamed event
 * @param {string|null} [config.keyShortcuts]  the field's `aria-keyshortcuts` as the server wrote
 *   it, shortcuts separated by spaces
 */
export default function wirekitCommandPaletteTrigger(config = {}) {
    const palette = typeof config.for === 'string' && config.for !== '' ? config.for : null;

    const stated = typeof config.keyShortcuts === 'string' ? config.keyShortcuts.trim().split(/\s+/).filter((shortcut) => shortcut !== '') : [];

    // The palettes this field opens: the one of its name, or without one every palette that
    // answers an event without a name.
    const opens = (claim) => (palette === null ? claim.namedOnly !== true : claim.name === palette);

    return {
        // The shortcuts the field states, bound to its `aria-keyshortcuts`, and whether the one
        // shown at its end is visible.
        keyShortcuts: stated.length > 0 ? stated.join(' ') : null,
        shortcutShown: true,

        /** Stops following the page's palettes. */
        _stopWatching: null,

        init() {
            if (stated.length === 0) {
                return;
            }

            this._stopWatching = watchPaletteHotkeys(() => this._stateShortcuts());
            this._stateShortcuts();
        },

        /** The shortcuts of the field's statement that open its palette, with the page as it is. */
        _stateShortcuts() {
            const open = stated.filter((shortcut) => ! pressTakenFrom(pressOf(shortcut), opens));

            this.keyShortcuts = open.length > 0 ? open.join(' ') : null;
            this.shortcutShown = open.length > 0;
        },

        /** Open the palette with what the field holds, or hand it over if the palette is open. */
        openPalette() {
            const query = typeof this.$refs?.field?.value === 'string' ? this.$refs.field.value : '';
            const detail = palette === null ? { query } : { name: palette, query };

            window.dispatchEvent(new CustomEvent('wirekit-command-palette-show', { detail }));
        },

        /**
         * A key typed into the field. Text still being composed waits for the end of the
         * composition, which arrives as its own event.
         *
         * @param {Event} [event]
         */
        type(event) {
            if (event && event.isComposing === true) {
                return;
            }

            this.openPalette();
        },

        /**
         * The focus left the field. Where it went, the text has gone there too, and the field is
         * empty for the next search. A focus that left for nowhere has not arrived yet, so the
         * field keeps its text until it does.
         *
         * @param {FocusEvent} [event]
         */
        clear(event) {
            const field = this.$refs?.field;

            if (! field) {
                return;
            }

            this._stopWaiting();

            if (event && event.relatedTarget === null && typeof document !== 'undefined') {
                this._onArrival = () => {
                    this._onArrival = null;
                    field.value = '';
                };
                document.addEventListener('focusin', this._onArrival, { once: true });

                return;
            }

            field.value = '';
        },

        /** The listener that empties the field once a focus that left for nowhere arrives. */
        _onArrival: null,

        _stopWaiting() {
            if (this._onArrival && typeof document !== 'undefined') {
                document.removeEventListener('focusin', this._onArrival);
            }

            this._onArrival = null;
        },

        destroy() {
            this._stopWaiting();

            if (this._stopWatching) {
                this._stopWatching();
                this._stopWatching = null;
            }
        },
    };
}
