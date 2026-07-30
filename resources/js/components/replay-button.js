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

            root.innerHTML = source;

            // The replaced markup carries its own directives, and Alpine only
            // walks a tree once. Without this the demo comes back as static
            // HTML — visually right, completely dead.
            if (window.Alpine) {
                window.Alpine.initTree(root);
            }

            root.dispatchEvent(new CustomEvent('wirekit:replayed', { bubbles: true }));
        },
    };
}
