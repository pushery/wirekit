/**
 * The developer-facing warning a component emits when its composition is wrong
 * in a way that still renders.
 *
 * Four components need this: content placed straight into a card with no
 * card.body, plain `<thead>`/`<tr>`/`<td>` dropped into a table slot, and a
 * `wire:model` on tabs, which are client-only state. Each produces a page that
 * looks approximately right and is subtly wrong, so the console is the only
 * place the developer finds out.
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
