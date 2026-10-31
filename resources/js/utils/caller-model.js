/**
 * `x-wk-model`: an `x-model` a caller writes on a component, read in the scope around it.
 *
 * Alpine reads an `x-model` in the scope of the element it stands on, the nearest `x-data` at or
 * above it. A component whose field stands inside a root of the component's own would therefore
 * read the caller's expression against the component's names first, and a page property named like
 * one of them (`password`, `value`, `start`) would bind the field to the component instead.
 *
 * The component's view renames the caller's `x-model` to `x-wk-model`, its modifiers kept, and marks
 * its own outermost element with `data-wk-ref-scope`, the boundary `x-wk-ref` uses
 * (utils/caller-ref.js). This directive reads and writes the expression in the scope above that
 * boundary and hands Alpine's own `x-model` on the same element a getter and a setter for it, the
 * way Livewire builds `wire:model`. The field keeps everything `x-model` does, the caller's
 * modifiers among it.
 *
 * The expression is read with Alpine's evaluator and written as `expression = value`, the way
 * Alpine's own `x-model` writes it, so the CSP build reads the same grammar for it.
 */
import { closestRefScope } from './caller-ref.js';

/**
 * Bind `el` with Alpine's `x-model`, the caller's expression read and written in the scope around
 * the component that contains it.
 *
 * Returns the function that takes the binding back, or null when there was nothing to bind: an
 * empty expression, or no element above the component to read the scope from.
 */
export function bindCallerModel(Alpine, el, expression, modifiers = []) {
    const written = String(expression ?? '').trim();
    const around = (closestRefScope(el) ?? el)?.parentElement;

    if (written === '' || ! around) {
        return null;
    }

    const read = Alpine.evaluateLater(around, written);
    const write = Alpine.evaluateLater(around, `${written} = __placeholder`);

    return Alpine.bind(el, {
        [['x-model', ...modifiers].join('.')]: () => ({
            get() {
                let value;

                read((result) => {
                    value = result;
                });

                return value;
            },
            set(value) {
                write(() => {}, { scope: { __placeholder: value } });
            },
        }),
    });
}

/** Register the `x-wk-model` directive. Idempotent: a second bundle registers the same handler. */
export function registerCallerModelDirective(Alpine) {
    Alpine.directive('wk-model', (el, { expression, modifiers }, { cleanup }) => {
        const release = bindCallerModel(Alpine, el, expression, modifiers);

        if (typeof release === 'function') {
            cleanup(release);
        }
    });
}
