/**
 * Accordion — which panels are open, in single or multiple mode.
 *
 * The whole thing was an inline `x-data` declaring two methods, which Alpine's
 * CSP build cannot parse; the spread and the arrow function inside them are out
 * of its grammar too. Under a strict Content-Security-Policy no panel opened —
 * the headers rendered, the chevrons sat still, and nothing said why.
 *
 * `opened` is an ARRAY rather than a single id even in single mode. That looks
 * redundant and is not: the template asks `isOpen(id)` per item, and one shape
 * for both modes keeps that question answerable without the item knowing which
 * mode it is in.
 *
 * Lifecycle resources held on `this`: NONE. The keydown handler is bound
 * declaratively in the template, so Alpine tears it down with the component.
 *
 * @param {Object} config
 * @param {string} [config.mode]  'single' (default) or 'multiple'
 */
import { moveRovingFocus } from '../utils/roving-focus.js';

/**
 * What counts as an accordion header, read from the DOM on every keypress.
 *
 * A descendant selector rather than a `:scope`-anchored one, which is the same
 * choice the tablist makes: a developer who wraps a group of items in a layout
 * element of their own would silently lose the arrow keys under an anchored
 * selector, and a silent loss is the failure nobody reports.
 */
const HEADER_SELECTOR = 'h3 > button[aria-controls]';

/**
 * Which key moves focus where. Home and End are absolute, so they answer even
 * when focus arrived from somewhere unexpected; next and prev wrap.
 */
const HEADER_DIRECTIONS = {
    ArrowDown: 'next',
    ArrowUp: 'prev',
    Home: 'first',
    End: 'last',
};

export default function wirekitAccordion(config = {}) {
    return {
        mode: config.mode === 'multiple' ? 'multiple' : 'single',
        opened: [],

        toggle(id) {
            if (this.mode === 'single') {
                // Clicking the open panel closes it: a single-mode accordion
                // that cannot be fully closed traps the reader in whichever
                // panel they opened last.
                this.opened = this.opened.includes(id) ? [] : [id];

                return;
            }

            this.opened = this.opened.includes(id)
                ? this.opened.filter((openId) => openId !== id)
                : this.opened.concat([id]);
        },

        isOpen(id) {
            return this.opened.includes(id);
        },

        /**
         * Move focus between the headers with the arrow keys, Home and End.
         *
         * The WAI-ARIA accordion pattern calls these keys optional and the
         * documented keyboard model promises them, so they belong in the
         * behavior rather than only in the prose. Focus only: arrowing to a
         * header does NOT open its panel — the same manual-activation model the
         * tablist uses, because a panel can be expensive to reveal and a reader
         * passing through should not pay for every panel on the way.
         *
         * The target check is what keeps Home and End usable INSIDE an open
         * panel. Those two directions are absolute, so without it a caret in a
         * text field would be dragged to the first accordion header instead of
         * to the start of the line. ArrowUp and ArrowDown are safe by
         * construction — focus in a panel is not a header, so the traversal
         * declines to guess which header the reader meant.
         *
         * Nesting: the event is stopped once it has been acted on, so an inner
         * accordion owns its own keys and the outer one never moves focus a
         * second time on the way up. The outer traversal still SEES an open
         * inner accordion's headers, and that is the honest reading of the
         * pattern — on screen they are the next headers.
         *
         * @param {KeyboardEvent} event
         */
        handleKeydown(event) {
            // The key name is a LOOKUP, so an inherited property name such as
            // `toString` would come back truthy and read as a direction. The
            // check is on the type rather than on truthiness for that reason.
            const direction = HEADER_DIRECTIONS[event && event.key];

            if (typeof direction !== 'string') {
                return;
            }

            // Capability-checked rather than assumed: the ESM harness builds this
            // factory against a deliberately barren stub, and a factory that
            // requires more than it uses turns that into a TypeError at init.
            const target = event.target;

            if (! target || typeof target.matches !== 'function' || ! target.matches(HEADER_SELECTOR)) {
                return;
            }

            if (! moveRovingFocus(this.$root, direction, HEADER_SELECTOR)) {
                return;
            }

            // Only once focus actually moved: a key this accordion cannot answer
            // keeps its native behavior, so the page still scrolls.
            if (typeof event.preventDefault === 'function') {
                event.preventDefault();
            }

            if (typeof event.stopPropagation === 'function') {
                event.stopPropagation();
            }
        },
    };
}
