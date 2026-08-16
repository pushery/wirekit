/**
 * A tablist whose selection lives on the SERVER — the bar alone, with no panels.
 *
 * `wirekitTabs` owns which tab is open and shows the matching panel. That is the right
 * model for content the browser already has, and the wrong one for the arrangement a
 * Livewire application reaches for first: a bar over server-rendered content, where
 * choosing a tab is a round trip and the page comes back different. There, the component
 * holds no selection at all — the server just rendered it — and there are no panels to
 * point `aria-controls` at.
 *
 * So this factory holds NOTHING. No active key, no index, no cached element list. Every
 * piece of state a tablist normally keeps is, in this arrangement, a copy of something
 * the server already said, and a copy is exactly what goes stale: Livewire replaces this
 * markup on every selection, so anything captured at init describes the previous page.
 *
 * ACTIVATION IS MANUAL, and that is a decision rather than a default. The APG allows
 * either, and automatic activation — selection following focus — is the nicer one when
 * switching costs nothing. Here it costs a request: arrowing across five tabs would fire
 * five round trips and land on the fifth, having rendered four pages nobody asked to see.
 * So arrows move focus, and Enter or Space commits — which needs no handler at all,
 * because the tab is a real `<button>` and the browser already does it.
 *
 * Lifecycle resources held on `this`: NONE. Nothing is registered, so nothing needs
 * tearing down.
 */
import { moveRovingFocus } from '../utils/roving-focus.js';

export default function wirekitTablist() {
    return {
        /**
         * Move focus, without changing the selection.
         *
         * `$root` rather than `$el`: every caller is a keydown handler on the LIST, but
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
