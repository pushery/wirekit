/**
 * The developer-facing warning a component emits when its composition is wrong
 * in a way that still renders.
 *
 * The cases share a shape rather than a number: a composition that renders, looks
 * approximately right, and is subtly wrong — content placed straight into a card with
 * no card.body, plain `<thead>`/`<tr>`/`<td>` dropped into a table slot, a
 * `wire:model` on tabs, whose state is client-only. Nothing throws and nothing looks
 * broken, so the console is the only place the developer finds out.
 *
 * ⚠️ No count here, deliberately. This said "Four components need this" and then listed
 * three; the tree carries eight call surfaces across `resources/js/components/` and
 * `resources/views/components/`, and the number moved every time one was added. A count
 * in a comment is a claim nobody re-measures — `grep -rl 'devWarn' resources/` answers it
 * in a second and cannot go stale.
 *
 * It lives here rather than inline in the templates because `console.warn(…)` as
 * a directive expression PARSES under Alpine's CSP build but does not RUN there:
 * the evaluator resolves an identifier against the Alpine scope alone, with no
 * window fallback, so naming `console` throws while BUILDING the component. That
 * takes the whole element's scope down with it — so the warning did not merely
 * vanish under a strict Content-Security-Policy, it broke the component it was
 * warning about.
 *
 * Every call site is gated on `config('app.debug')` in Blade, so this does not
 * reach a production page in the first place.
 */
export function devWarn(message) {
    if (! message) {
        return;
    }

    console.warn(message);
}
