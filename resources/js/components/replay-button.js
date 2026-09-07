/**
 * Replay button — re-mounts a demo from the snapshot stored beside it.
 *
 * The handler declared two constants, returned early, branched on an `if` and
 * constructed a `CustomEvent`. Alpine's CSP build parses one expression and
 * allows none of those, so under a strict Content-Security-Policy the button was
 * inert: it looked like a control and reset nothing.
 *
 * The button carried no scope of its own before — the handler read `$el` and
 * worked entirely on the DOM around it. It gains one now, because a directive
 * that cannot hold statements has to call something, and something has to be in
 * scope to be called.
 *
 * The target is found by walking UP from the button, not by querying the
 * document: a page may hold several replayable demos, and a document-wide lookup
 * would reset whichever one happened to come first.
 *
 * ⚠️ WHICH MEANS THE BUTTON IS ALWAYS INSIDE WHAT IT REPLACES, and that is why
 * this file has to think about focus at all. `closest()` matches the element
 * itself or an ancestor and never a sibling, so in the only arrangement where
 * `replay()` does anything, `root.innerHTML = source` detaches the very button
 * the reader just pressed. A focused element removed from the document does not
 * hand focus to its replacement — the browser drops it to `<body>`, so the next
 * Tab restarts at the top of the page (WCAG 2.4.3). On a docs page carrying
 * dozens of previews that is the whole page again, per replay.
 *
 * `docs/components/replay-button.md` described that outcome accurately and then
 * asked the DEVELOPER to repair it from the `wirekit:replayed` listener. Making
 * every developer write the same restore is the workaround this library is not
 * allowed to ship: the factory owns the swap, so the factory owns the focus.
 * The event still fires afterwards, so a listener that wants focus somewhere
 * else — the demo's first control, say — overrides this rather than fighting it.
 */
export default function wirekitReplayButton() {
    return {
        replay() {
            const button = this.$el;
            const selector = button.dataset.replayTargetSelector;
            const root = selector
                ? button.closest(selector)
                : button.closest('[data-replay-target]');

            // A button with no replayable ancestor does nothing. That is a
            // developer wiring mistake rather than a runtime condition, and
            // throwing here would take the rest of the page's Alpine with it.
            if (! root) {
                return;
            }

            const source = root.dataset.replaySource;

            // An ABSENT snapshot means nothing was captured; an EMPTY one is a
            // legitimate snapshot of empty content. The distinction is why this
            // reads `undefined` rather than falsiness.
            if (source === undefined) {
                return;
            }

            // Both readings happen BEFORE the swap, because afterwards this node
            // is detached and neither question has an answer any more.
            //
            // `document.activeElement === button` is the whole gate on purpose: a
            // mouse reader on macOS never focuses a button by clicking it, so
            // there is nothing to put back and moving focus would be an unasked-for
            // jump. A keyboard reader is on the button by definition.
            const wasFocused = typeof document !== 'undefined' && document.activeElement === button;
            const position = wasFocused ? this._buttonIndex(root, button) : -1;

            root.innerHTML = source;

            // The replaced markup carries its own directives, and Alpine only
            // walks a tree once. Without this the demo comes back as static
            // HTML — visually right, completely dead.
            if (window.Alpine) {
                window.Alpine.initTree(root);
            }

            // After the re-bind, so the button focus lands on is live rather than
            // inert markup; before the announcement, so a listener that wants
            // focus elsewhere overrides this instead of racing it.
            if (wasFocused) {
                this._restoreFocus(root, position);
            }

            root.dispatchEvent(new CustomEvent('wirekit:replayed', { bubbles: true }));
        },

        /**
         * Where this button sits among the replay buttons inside `root`.
         *
         * The rebuilt button is a NEW node — the snapshot is a string, so identity
         * cannot survive the swap and the position is what carries over. A demo
         * with one button, which is every documented shape, gets 0 either way.
         *
         * `wk-replay-button` is the component's published BEM root, emitted through
         * `$attributes->merge()` so a caller's own class adds to it rather than
         * replacing it.
         *
         * @param {Element} root
         * @param {Element} button
         * @returns {number} the index, or 0 when the DOM cannot be asked
         */
        _buttonIndex(root, button) {
            if (typeof root.querySelectorAll !== 'function') {
                return 0;
            }

            return Math.max(0, Array.from(root.querySelectorAll('.wk-replay-button')).indexOf(button));
        },

        /**
         * Put focus back inside the replayed demo.
         *
         * ⚠️ THE FALLBACK IS LOAD-BEARING, not defensive padding. A snapshot is
         * whatever was captured, and one taken without the button in it rebuilds a
         * demo with no replay control at all — the documented manual shape renders
         * demo and button from one string precisely so this cannot happen, but the
         * factory cannot assume the reader followed it. Landing on the root with
         * `tabindex="-1"` keeps the reader at the demo instead of at `<body>`;
         * `-1` is programmatically focusable and NOT tabbable, so it adds no tab
         * stop. Same shape as `overlay.js`'s `makeFocusable()`.
         *
         * @param {Element} root
         * @param {number} position
         */
        _restoreFocus(root, position) {
            const rebuilt = typeof root.querySelectorAll === 'function'
                ? Array.from(root.querySelectorAll('.wk-replay-button'))
                : [];

            const target = rebuilt[position] ?? rebuilt[0] ?? null;

            if (target && typeof target.focus === 'function') {
                target.focus();

                return;
            }

            if (typeof root.focus !== 'function') {
                return;
            }

            if (typeof root.hasAttribute === 'function'
                && typeof root.setAttribute === 'function'
                && ! root.hasAttribute('tabindex')
            ) {
                root.setAttribute('tabindex', '-1');
            }

            root.focus();
        },
    };
}
