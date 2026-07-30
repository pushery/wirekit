/**
 * Slider — the mirror of a native range input, and the bubble that follows it.
 *
 * The component is a thin layer over `<input type="range">`: the element owns
 * its value, and `current` only mirrors it so the bubble and the announced text
 * have something reactive to read. That direction matters and is the reason
 * there is no `:value` binding on the input — see the template.
 *
 * This moved out of an inline `x-data` because Alpine's CSP build parses one
 * expression and allows neither method shorthand nor getters, so under a strict
 * Content-Security-Policy the whole object literal failed to build: the element
 * ended up with an empty scope and every directive on it silently did nothing —
 * no bubble, no announced text, no Livewire resync.
 *
 * Lifecycle resources held on `this`:
 *   - _unhookResync (the function Livewire.hook returns) — released in destroy().
 *     It used to be discarded, and the comment here claimed a post-teardown fire
 *     was harmless. It is not: after a livewire:navigate the handler still runs
 *     and reads $refs on a scope that no longer exists.
 *
 * @param {Object} config
 * @param {string} config.current   the input's value at render time, as a string
 * @param {number} config.min       the input's min, mirrored for the pct math
 * @param {number} config.max       the input's max, mirrored for the pct math
 * @param {Object} config.marksMap  value -> label, for the announced text
 */
export default function wirekitSlider(config = {}) {
    return {
        current: config.current != null ? String(config.current) : '',
        min: Number(config.min ?? 0),
        max: Number(config.max ?? 100),
        marksMap: config.marksMap || {},

        _resync: null,
        _unhookResync: null,

        /** Pull the element's own value back into the mirror. */
        syncFromInput() {
            const el = this.$refs.control;

            if (el && String(el.value) !== String(this.current)) {
                this.current = String(el.value);
            }
        },

        /**
         * Push the mirror back onto the element — the other direction, and only
         * for the optimistic layer.
         *
         * The template deliberately does not bind `:value="current"`, because
         * Alpine would then re-assert a stale mirror over whatever Livewire just
         * wrote. That leaves one case unserved: a rollback writes `current`, and
         * without this the element keeps the refused value — the thumb sits
         * where the server said no while everything derived from the mirror
         * says otherwise.
         *
         * Named in the layer's `after`, which runs after EVERY write including
         * the undo, so the two can never be one apart.
         */
        syncToInput() {
            const el = this.$refs.control;

            if (el && String(el.value) !== String(this.current)) {
                el.value = String(this.current);
            }
        },

        /**
         * Watch for a value that changed without the browser telling us.
         *
         * Assigning `el.value` fires no event and mutates no attribute, so it is
         * invisible to both x-effect and a MutationObserver (verified). Livewire's
         * x-model effect does exactly that on a server-side change, so the one
         * reliable signal is Livewire's own commit hook — after a round trip, look
         * at the element again. Apps without Livewire never call it and pay
         * nothing; there, only the browser moves the thumb and @input suffices.
         */
        initResync() {
            if (typeof window === 'undefined' || ! window.Livewire?.hook) {
                return;
            }

            this._resync = () => queueMicrotask(() => this.syncFromInput());

            // The return value is an unhook function, and dropping it is what kept
            // this handler alive past its component.
            this._unhookResync = window.Livewire.hook('commit', ({ succeed }) => succeed(this._resync));
        },

        destroy() {
            if (this._unhookResync) {
                this._unhookResync();
                this._unhookResync = null;
            }
        },

        get pct() {
            const r = (this.max - this.min) || 1;

            return Math.max(0, Math.min(100, ((Number(this.current) - this.min) / r) * 100));
        },

        get valueText() {
            // Labeled-mark map wins (announces 'Low' for value 0); otherwise the
            // raw number IS the value. Kept live so keyboard / drag updates the
            // announced text as the thumb moves.
            return this.marksMap[this.current] ?? String(this.current);
        },

        /**
         * Where the bubble sits, and how far it shifts back — both as OBJECTS.
         *
         * Alpine's `bind:style` given a STRING replaces the element's whole
         * `style` attribute on every reactive update, taking the static styles
         * with it. Neither element carries static inline styles today, so the
         * string form these replaced was not yet broken — but it is one static
         * style away from the "element loses its background on first update"
         * regression already documented on reading-progress, and the object form
         * merges property by property instead.
         */
        bubbleStyle() {
            return { left: `${this.pct}%` };
        },

        /**
         * The bubble shifts by its own position so it stays WITHIN the track at
         * the extremes: 0% left-aligns with the thumb, 100% right-aligns, and it
         * centers (-50%) in the middle. The native thumb exposes no width to JS,
         * so pct doubles as the shift.
         */
        bubbleShiftStyle() {
            return { transform: `translateX(-${this.pct}%)` };
        },
    };
}
