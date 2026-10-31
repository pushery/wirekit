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
 * Lifecycle resources held on `this`:
 *   - _fragmentAgain (a click listener on `window`, utils/fragment.js) — opens the item a link
 *     names when the reader follows it a second time, which fires no `hashchange`; stopped in
 *     destroy().
 * The keydown and hashchange handlers are bound declaratively in the template, so Alpine tears
 * them down with the component.
 *
 * @param {Object} config
 * @param {string} [config.mode]  'single' (default) or 'multiple'
 */
import { focusClicked } from '../utils/focus-clicked.js';
import { fragmentId, onFragmentFollowedAgain } from '../utils/fragment.js';
import { prefersReducedMotion, scrollBehavior } from '../utils/motion.js';
import { moveRovingFocus } from '../utils/roving-focus.js';

/**
 * What counts as an accordion header, read from the DOM on every keypress.
 *
 * A descendant selector rather than a `:scope`-anchored one, which is the same
 * choice the tablist makes: a developer who wraps a group of items in a layout
 * element of their own would silently lose the arrow keys under an anchored
 * selector, and a silent loss is the failure nobody reports.
 *
 * Every heading level is listed, and the list is the accordion's `level`
 * prop written out: the item template emits `<h{{ $levelValue }}>` for any of
 * 1..6, and the docs tell a reader to set `level="2"` directly under the page
 * `<h1>`. With only the default level listed, both gates below would close on
 * such an accordion — `target.matches()` false, `moveRovingFocus` finding no
 * items — and Arrow keys, Home and End would be dead while Enter and Space
 * still toggle. Add a level to the prop and it belongs here too.
 *
 * `:is()` is far below the supported-browser baseline (Chrome 88, Safari 14,
 * Firefox 78 against a floor of 111 / 16.4 / 128), so it needs no `@supports`
 * and no fallback.
 */
const HEADER_SELECTOR = ':is(h1, h2, h3, h4, h5, h6) > button[aria-controls]';

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
        _fragmentAgain: null,

        init() {
            this.openAtStart();
            this.openForFragment();
            this._fragmentAgain = onFragmentFollowedAgain(() => this.openForFragment());
        },

        /**
         * Open the items the server rendered open, the ones that carry `data-wk-accordion-open`. In
         * single mode only one can be open, and the first in the document is the one that stays.
         * Before the items' bindings first run, so an open panel is never closed and opened again.
         */
        openAtStart() {
            const open = this._ownItems().filter((item) => item.hasAttribute('data-wk-accordion-open')).map((item) => item.id);

            if (open.length === 0) {
                return;
            }

            this.opened = this.mode === 'single' ? [open[0]] : [...new Set([...this.opened, ...open])];
        },

        /** This accordion's own items in document order, not those of an accordion nested in it. */
        _ownItems() {
            const root = this.$root;

            if (! root || typeof root.querySelectorAll !== 'function') {
                return [];
            }

            return [...root.querySelectorAll('[data-wk-accordion-item]')].filter((item) => this._ownItemAround(item) === item);
        },

        destroy() {
            this._fragmentAgain?.();
            this._fragmentAgain = null;
        },

        /**
         * Open or close a panel. `header` is the header a click toggled it from, which takes the
         * focus where the engine did not, so the arrows go on from it (utils/focus-clicked.js).
         */
        toggle(id, header = null) {
            focusClicked(header);

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

        /**
         * Open a panel the browser's find in page matched, and leave an open one alone.
         *
         * Not `toggle()`: a match must never close anything the reader is looking at. In single
         * mode opening it closes the other panel, which is what a click on its header would do.
         */
        reveal(id) {
            if (! this.isOpen(id)) {
                this.toggle(id);
            }
        },

        isOpen(id) {
            return this.opened.includes(id);
        },

        /**
         * Open the item the address names, or the item that holds the element it names, and bring
         * that element into view. Read on start, on `hashchange`, which the template binds, and when
         * the reader follows a link to the same fragment again, which fires none.
         *
         * An item carries its id on its own element, so a link to `#shipping` reaches it, and the
         * browser scrolls to its header by itself; it does not open the item, and it cannot scroll
         * to an element inside a closed panel at all. An item of an accordion nested in this one is
         * that accordion's to open; this one opens its own item around it, so the path is visible.
         */
        openForFragment() {
            const root = this.$root;
            const doc = root ? root.ownerDocument : null;

            if (! doc || typeof doc.getElementById !== 'function') {
                return;
            }

            // A test harness hands some factories a `window` without a `location`.
            const id = fragmentId(typeof window !== 'undefined' && window.location ? window.location.hash : '');

            if (id === '') {
                return;
            }

            const target = doc.getElementById(id);
            const item = this._ownItemAround(target);

            if (! item) {
                return;
            }

            this.reveal(item.id);
            this.$nextTick(() => target.scrollIntoView({ block: 'start', behavior: scrollBehavior(! prefersReducedMotion()) }));
        },

        /** The item of this accordion that is or holds `node`, or null. */
        _ownItemAround(node) {
            let item = node && typeof node.closest === 'function' ? node.closest('[data-wk-accordion-item]') : null;

            while (item) {
                const parent = item.parentElement;

                if (parent && parent.closest('[data-wk-accordion-mode]') === this.$root) {
                    return item;
                }

                item = parent ? parent.closest('[data-wk-accordion-item]') : null;
            }

            return null;
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
