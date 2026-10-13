/**
 * `x-wk-focus-first`: when its expression turns true, the focus moves to the first entry inside.
 *
 * For a list a button opens that stands before the button in the page, as the navbar's mobile menu
 * does below its breakpoint: with the focus left on the button, the next Tab would leave the list
 * behind. An element under `display: none` takes no focus, so the move waits for Alpine's next tick
 * and then for the element to be rendered. An opening transition holds the tick until Alpine has
 * shown the element, two frames in; without a transition `x-show` shows it in the next animation
 * frame, after the tick, so the directive waits a few frames for it. A tick that finds the
 * expression false again, after Escape or a second press of the button, leaves the focus where the
 * reader put it, and the state a page loads with moves nothing.
 *
 * The entry is the first candidate that takes the focus: not a hidden input, not `tabindex="-1"`,
 * not inside `inert`, rendered, and focused once asked. A combobox renders its hidden value field
 * before its visible one, so the first match alone is not always an entry a reader can use.
 *
 * A directive rather than a component, so the bar keeps its state in an inline `x-data` that
 * evaluates without any of WireKit's bundles. Where this directive is not registered, Alpine
 * ignores it and the menu opens with the focus left where it was.
 *
 * On a morph's clone it does nothing (`skipDuringClone`), so a Livewire update collects nothing.
 * Cleanup: none beyond the effect Alpine releases with the element; a frame still pending reads the
 * expression again before it moves anything.
 */
export const FOCUS_FIRST_TARGETS = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(', ');

/** Candidates that match the list above and still are no entry for the reader. */
const NOT_AN_ENTRY = 'input[type="hidden"], [tabindex="-1"]';

/** How many animation frames to wait for an element that is not rendered yet. */
const FRAMES_TO_WAIT = 10;

/** Focus the first candidate inside `el` that takes the focus; whether one did. */
export function focusFirstEntry(el) {
    for (const candidate of el.querySelectorAll(FOCUS_FIRST_TARGETS)) {
        if (candidate.matches(NOT_AN_ENTRY) || candidate.closest('[inert]') || candidate.getClientRects().length === 0) {
            continue;
        }

        candidate.focus();

        if (candidate.ownerDocument.activeElement === candidate) {
            return true;
        }
    }

    return false;
}

export function registerFocusFirstDirective(Alpine) {
    const skipDuringClone = typeof Alpine.skipDuringClone === 'function' ? Alpine.skipDuringClone : (callback) => callback;
    const nextTick = typeof Alpine.nextTick === 'function' ? Alpine.nextTick : (callback) => Promise.resolve().then(callback);
    const nextFrame = typeof requestAnimationFrame === 'function' ? requestAnimationFrame : (callback) => setTimeout(callback, 16);

    Alpine.directive('wk-focus-first', skipDuringClone((el, { expression }, { evaluateLater, effect }) => {
        const read = evaluateLater(expression);
        // Unknown until the first reading: the state the page loads with moves nothing.
        let was = null;

        effect(() => read((value) => {
            const now = Boolean(value);
            const opened = was === false && now;

            was = now;

            if (! opened) {
                return;
            }

            nextTick(() => {
                let frames = 0;

                const attempt = () => read((still) => {
                    if (! still) {
                        return;
                    }

                    if (el.getClientRects().length === 0 && frames < FRAMES_TO_WAIT) {
                        frames += 1;
                        nextFrame(attempt);

                        return;
                    }

                    focusFirstEntry(el);
                });

                attempt();
            });
        }));
    }));
}
