/**
 * `indeterminate` is a DOM PROPERTY, so the server cannot render it.
 *
 * A checkbox's third state — "some, not all" — has no HTML attribute. It exists only as a
 * property on the element, which means something has to apply it after every render.
 *
 * The component used to do that with `x-init="$el.indeterminate = true"`, and `x-init` runs
 * ONCE. The third state, though, practically always arrives AFTER the first render: a
 * Livewire round trip morphs the element, the attribute text changes, Alpine does not
 * re-initialize, and the property keeps its initial value. Measured in a browser with one of
 * three rows selected — the server rendered `x-init="$el.indeterminate = true"` and
 * `el.indeterminate` was `false`. Nothing errors; the box simply reads as "none selected"
 * while something is selected, which is precisely the state it exists to show.
 *
 * The old form could also only ever turn the state ON: the attribute was emitted only when
 * the prop was true, so going back to a determinate state had nothing to undo it.
 *
 * WHY AN ATTRIBUTE PLUS AN OBSERVER, and not the two obvious alternatives:
 *
 *   - `x-effect` is what `data-table` uses, and it works there because the value it reads is
 *     reactive ALPINE state. Here the value comes from the server, so an effect over a
 *     literal re-runs exactly as often as `x-init` does: once.
 *   - Giving the checkbox its own `x-data` would make the effect reactive, and would also
 *     mean a caller's passed-through `x-data` is silently discarded — HTML keeps the first
 *     of two identical attributes. That is a known defect class in this library and not
 *     worth trading one for.
 *
 * A dedicated attribute that nothing binds has neither problem. It is written by the server
 * on every render and by nobody else, so "the server changed it" is a fact the DOM states
 * rather than something to infer — the same reasoning `observeServerValue` is built on, and
 * the same reason a Livewire hook is avoided: these components are documented to work in a
 * plain form too, and a mutation on an attribute is true whoever wrote it.
 */
export const WK_INDETERMINATE_ATTRIBUTE = 'data-wk-indeterminate';

/** Apply the attribute's current value to the element's property. */
function apply(el) {
    el.indeterminate = el.getAttribute(WK_INDETERMINATE_ATTRIBUTE) === 'true';
}

/**
 * Register the `x-wk-indeterminate` directive.
 *
 * The directive exists so the FIRST application happens at Alpine's own initialization time
 * rather than on a timer, and so teardown has somewhere to live. Every later application
 * comes from the observer.
 */
export function registerIndeterminateDirective(Alpine) {
    Alpine.directive('wk-indeterminate', (el, directive, { cleanup }) => {
        apply(el);

        // Defensive per the house rule for observers: an element can be torn down between
        // init and the first callback, and an observer firing into a dead scope throws
        // where nobody is looking.
        if (typeof MutationObserver === 'undefined') {
            return;
        }

        const observer = new MutationObserver(() => apply(el));

        observer.observe(el, { attributes: true, attributeFilter: [WK_INDETERMINATE_ATTRIBUTE] });

        cleanup(() => observer.disconnect());
    });
}
