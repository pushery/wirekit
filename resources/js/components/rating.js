/**
 * Rating — an interactive star row implementing the WAI-ARIA radio-group
 * keyboard model.
 *
 * The state is two numbers and the interactions are short, so this lived
 * inline in the template. It cannot: Alpine's CSP build parses expressions
 * rather than compiling them, and every one of these handlers used something
 * outside that grammar — several statements in a row, an `if` block, an arrow
 * function, optional chaining. Under a strict Content-Security-Policy the stars
 * rendered and did nothing at all.
 *
 * Arrow-key navigation MOVES FOCUS as well as changing the value. That is not
 * decoration: in a radio-group the selected option is the only tab stop, so a
 * value change that leaves focus behind strands the user on an element that is
 * no longer the one they selected.
 *
 * @param {Object} config
 * @param {number} config.value  the initial rating
 * @param {number} config.max    the highest selectable rating
 */
export default function wirekitRating(config = {}) {
    return {
        rating: Number(config.value) || 0,
        hovered: 0,
        _max: Number(config.max) || 5,

        /**
         * Pick a value, and tell a plain HTML form about it.
         *
         * The hidden input is what a non-Livewire form submits, and assigning
         * `rating` alone does not fire an input event on it — so without this
         * dispatch a plain form silently submits the value the page loaded with.
         */
        select(value) {
            this.rating = value;
            this._notify();
        },

        /** Raise by one and follow with focus, unless already at the top. */
        stepUp() {
            if (this.rating >= this._max) {
                return;
            }

            this.rating++;
            this._notify();
            this._focus(this.$el.nextElementSibling);
        },

        /** Lower by one and follow with focus, unless already at the bottom. */
        stepDown() {
            if (this.rating <= 1) {
                return;
            }

            this.rating--;
            this._notify();
            this._focus(this.$el.previousElementSibling);
        },

        /** Home / End — jump to either end of the scale. */
        selectFirst() {
            this.rating = 1;
            this._notify();
            this._focus(this.$el.parentElement && this.$el.parentElement.firstElementChild);
        },

        selectLast() {
            this.rating = this._max;
            this._notify();
            this._focus(this.$el.parentElement && this.$el.parentElement.lastElementChild);
        },

        /**
         * Focus after the DOM settles.
         *
         * The star that should receive focus may only become focusable once the
         * new rating has rendered, so this waits a tick rather than reaching for
         * an element that is still the old one.
         */
        _focus(element) {
            if (! element || typeof element.focus !== 'function') {
                return;
            }

            this.$nextTick(() => element.focus());
        },

        /** Fire `input` on the hidden field so plain-HTML forms see the change. */
        _notify() {
            const root = this.$el.closest('[x-data]');
            const hidden = root ? root.querySelector('input[type=hidden]') : null;

            if (hidden) {
                hidden.dispatchEvent(new Event('input', { bubbles: true }));
            }
        },
    };
}
