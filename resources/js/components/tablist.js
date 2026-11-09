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
 * Lifecycle resources held on `this`: none. The first list of a page installs the page-wide
 * listeners for that focus once (`installTabLinkFocus()`), and every later page reuses them.
 */
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
        init() {
            installTabLinkFocus();
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
