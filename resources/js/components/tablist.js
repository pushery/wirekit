/**
 * A tablist whose selection lives on the server — the bar alone, with no panels.
 *
 * `wirekitTabs` owns which tab is open and shows the matching panel. That is the right
 * model for content the browser already has, and the wrong one for the arrangement a
 * Livewire application reaches for first: a bar over server-rendered content, where
 * choosing a tab is a round trip and the page comes back different. There, the component
 * holds no selection at all — the server just rendered it — and there are no panels to
 * point `aria-controls` at.
 *
 * So this factory holds nothing. No active key, no index, no cached element list. Every
 * piece of state a tablist normally keeps is, in this arrangement, a copy of something
 * the server already said, and a copy is exactly what goes stale: Livewire replaces this
 * markup on every selection, so anything captured at init describes the previous page.
 *
 * Activation is manual, and that is a decision rather than a default. The APG allows
 * either, and automatic activation — selection following focus — is the nicer one when
 * switching costs nothing. Here it costs a request: arrowing across five tabs would fire
 * five round trips and land on the fifth, having rendered four pages nobody asked to see.
 * So arrows move focus, and Enter or Space commits. A tab that is a `<button>` needs no
 * handler for that, the browser does it. A tab that is a link follows Enter on its own and
 * scrolls the page on Space, so the list answers Space for it.
 *
 * A link tab that opens its address with `wire:navigate` leaves a page the reader came to
 * by the keyboard, and Livewire puts the focus nowhere on the next one. The tab the reader
 * chose is selected there, so the focus goes back to it, unless the new page focused
 * something itself.
 *
 * A bar that scrolls sideways brings its selected tab into view: when it starts, when it or a
 * tab changes size (a phone turned, a web font that lands after the first pass), and when a
 * Livewire render moves `aria-selected`. A page opened directly, from a bookmark or a link in a
 * mail, would otherwise show the bar from its first tab with the selected one cut off at the
 * edge. Only the bar scrolls, never the page, and the focus stays where it is
 * (utils/reveal-in-track.js). A selected tab already in full view leaves the bar where it is.
 *
 * Lifecycle resources held on `this`: a ResizeObserver on the bar and its tabs (`_resize`) and a
 * MutationObserver on the selection (`_selection`), both released in `destroy()`. The first list
 * of a page installs the page-wide listeners for the focus after a visit once
 * (`installTabLinkFocus()`), and every later page reuses them.
 */
import { revealInTrack } from '../utils/reveal-in-track.js';
import { moveRovingFocus } from '../utils/roving-focus.js';

/** Remembered on `window`, so a second copy of this module on the page installs nothing. */
const TAB_LINK_FOCUS_FLAG = '__wirekitTabLinkFocusInstalled';

/** The visit a tab link started: the name of its list and the address it opens. */
let pendingTabVisit = null;

/** A list's name as a reader hears it: its label, or the text of what labels it. */
export function tablistName(list) {
    const label = list.getAttribute('aria-label');

    if (label !== null && label.trim() !== '') {
        return label.trim();
    }

    const ids = (list.getAttribute('aria-labelledby') ?? '').split(/\s+/).filter(Boolean);
    const doc = list.ownerDocument ?? globalThis.document;

    return ids.map((id) => doc.getElementById(id)?.textContent?.trim() ?? '').filter(Boolean).join(' ');
}

/** The link tab a click or a key landed on, inside a tab list, or null. */
function tabLinkOf(target) {
    const tab = typeof target?.closest === 'function' ? target.closest('a[role="tab"]') : null;

    return tab && tab.hasAttribute('href') && tab.closest('[role="tablist"]') ? tab : null;
}

/** A click that opens the link in this page: the primary button, no key that sends it elsewhere. */
function opensHere(event) {
    return event.button === 0 && ! event.metaKey && ! event.ctrlKey && ! event.shiftKey && ! event.altKey;
}

/**
 * The selected tab of the list the visit came from, on the page it arrived at: the list with the
 * same name, or, for lists without one, the list whose selected tab opens the same address.
 */
export function chosenTabAfterVisit(doc, visit) {
    const lists = Array.from(doc.querySelectorAll('[role="tablist"]'));
    const selected = (list) => list.querySelector('[role="tab"][aria-selected="true"]');
    const named = visit.name === '' ? null : lists.find((list) => tablistName(list) === visit.name);

    if (named) {
        return selected(named);
    }

    if (visit.name !== '') {
        return null;
    }

    const same = lists.find((list) => selected(list)?.href === visit.href);

    return same ? selected(same) : null;
}

/** Install, once per page, the listeners that bring the focus back to a chosen tab link. */
export function installTabLinkFocus(win = globalThis.window, doc = globalThis.document) {
    if (! win || ! doc || win[TAB_LINK_FOCUS_FLAG]) {
        return;
    }

    win[TAB_LINK_FOCUS_FLAG] = true;

    doc.addEventListener('click', (event) => {
        const tab = tabLinkOf(event.target);

        if (tab && opensHere(event) && tab.getAttribute('aria-disabled') !== 'true') {
            pendingTabVisit = { name: tablistName(tab.closest('[role="tablist"]')), href: tab.href };
        }
    }, true);

    // A visit to another address than the one the tab opens is not the tab's visit.
    doc.addEventListener('livewire:navigate', (event) => {
        const url = event.detail?.url;

        if (pendingTabVisit && url && String(url) !== pendingTabVisit.href) {
            pendingTabVisit = null;
        }
    });

    doc.addEventListener('livewire:navigated', () => {
        const visit = pendingTabVisit;
        pendingTabVisit = null;

        if (! visit) {
            return;
        }

        const tab = chosenTabAfterVisit(doc, visit);
        const focused = doc.activeElement;

        if (tab && (focused === null || focused === doc.body)) {
            tab.focus();
        }
    });
}

export default function wirekitTablist() {
    return {
        _resize: null,
        _selection: null,

        init() {
            installTabLinkFocus();

            const reveal = () => this.revealSelected();

            // Both guarded because a plain unit harness has neither; the bar then reveals once.
            if (typeof ResizeObserver === 'function') {
                this._resize = new ResizeObserver(reveal);
                this._resize.observe(this.$root);
                Array.from(this.$root?.children ?? []).forEach((tab) => this._resize.observe(tab));
            }

            // A Livewire render that selects another tab changes `aria-selected` in place, and
            // the bar keeps its size, so only this notices.
            if (typeof MutationObserver === 'function') {
                this._selection = new MutationObserver(reveal);
                this._selection.observe(this.$root, { subtree: true, attributes: true, attributeFilter: ['aria-selected'] });
            }

            reveal();
        },

        destroy() {
            this._resize?.disconnect();
            this._selection?.disconnect();
            this._resize = null;
            this._selection = null;
        },

        /** Bring the selected tab of a bar that scrolls sideways into the visible part of the bar. */
        revealSelected() {
            const list = this.$root;

            if (typeof list?.querySelector !== 'function') {
                return;
            }

            revealInTrack(list, list.querySelector('[role="tab"][aria-selected="true"]'));
        },

        /**
         * Space on a tab that is a link: a link follows Enter but not Space, and Space would
         * scroll the page, so the list sends the link's click itself. A tab that is a button,
         * and a disabled link tab, keep what the browser does.
         *
         * @param {KeyboardEvent} event
         */
        activateLink(event) {
            const tab = tabLinkOf(event.target);

            if (! tab || ! this.$root.contains(tab) || tab.getAttribute('aria-disabled') === 'true') {
                return;
            }

            event.preventDefault();
            tab.click();
        },

        /**
         * Move focus, without changing the selection.
         *
         * `$root` rather than `$el`: every caller is a keydown handler on the list, but
         * the event originates at a tab, and a handler that reached for the event's own
         * element would search a button for buttons. The client-side tabs component
         * carries the same note for the same reason — it was a real defect there first.
         *
         * @param {'next'|'prev'|'first'|'last'} direction
         */
        moveFocus(direction) {
            moveRovingFocus(this.$root, direction);
        },
    };
}
