/**
 * `x-wk-ref`: an `x-ref` a caller writes on a component, registered where the caller's `$refs`
 * reads it.
 *
 * Alpine registers an `x-ref` on the nearest `x-data` root, the element itself included, and
 * `$refs` collects only the roots above the element that asks. A component that renders a root
 * of its own around the element carrying the caller's attributes, or on that element, would
 * therefore take the caller's ref, and the caller's `$refs` would never see it.
 *
 * The component's view moves the caller's name from `x-ref` to `x-wk-ref` on the element the
 * attributes land on, and marks its own outermost element with `data-wk-ref-scope`. This
 * directive registers the element on the root above that boundary: the root Alpine would have
 * chosen if the component rendered no roots of its own. A caller's name therefore never meets
 * a ref the component uses internally either.
 *
 * The name is read as written, never evaluated, the same as Alpine's own `x-ref`, so the
 * directive needs nothing from the expression grammar of the CSP build.
 */
export const WK_REF_SCOPE_ATTRIBUTE = 'data-wk-ref-scope';

/**
 * Register `el` under `name` on the root above the component that contains it.
 *
 * Returns the function that takes the registration back, or null when there was nothing to
 * register: an empty name, or no root above the component at all, which is also what Alpine's
 * own `x-ref` does with a ref outside every component.
 */
export function registerCallerRef(Alpine, el, name) {
    const trimmed = String(name ?? '').trim();

    if (trimmed === '' || ! el) {
        return null;
    }

    const scope = el.closest?.(`[${WK_REF_SCOPE_ATTRIBUTE}]`) ?? el;
    const holder = Alpine.closestRoot(scope.parentElement);

    if (! holder) {
        return null;
    }

    if (! holder._x_refs) {
        holder._x_refs = {};
    }

    holder._x_refs[trimmed] = el;

    return () => {
        // Only its own entry: a later element may have taken the name since.
        if (holder._x_refs && holder._x_refs[trimmed] === el) {
            delete holder._x_refs[trimmed];
        }
    };
}

/**
 * Register the `x-wk-ref` directive. Idempotent: a second bundle registers the same handler.
 *
 * The work happens in the `inline` hook, as in Alpine's own `x-ref`. Alpine runs `inline` while it
 * walks the tree and defers every other handler until the walk is over, so a ref registered in
 * the deferred handler would arrive after the caller's `init()` ran. `$refs` also caches, per
 * element, the ref tables it found on first use: a caller that reads `this.$refs` in `init()`
 * and has no ref table yet would keep a cache without the one this creates, for good.
 */
export function registerCallerRefDirective(Alpine) {
    const handler = () => {};

    handler.inline = (el, { expression }, { cleanup }) => {
        const release = registerCallerRef(Alpine, el, expression);

        if (release) {
            cleanup(release);
        }
    };

    Alpine.directive('wk-ref', handler);
}
