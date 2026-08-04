/**
 * Notice when the server changed a value the component is showing.
 *
 * Livewire patches the DOM in place and Alpine reads `x-data` exactly once, when
 * it initializes the element. So a component seeded from a PHP value keeps
 * whatever that value was on first paint: the server can send a new one on every
 * round trip, the attribute text in the DOM changes, and the live Alpine scope
 * never looks at it again. The reader is left with a control answering a
 * question nobody is asking any more.
 *
 * WHY A DEDICATED ATTRIBUTE, rather than reading the hidden input the component
 * already has. Measured on a real round trip, the two shapes in this library
 * behave differently and only one of them is watchable:
 *
 *   - `segmented-control` and `otp-input` write their hidden input imperatively,
 *     so Livewire's morph lands on the `value` attribute and stays there. An
 *     observer on that input would work.
 *   - `rating`, `data-table`, `notification-center` and `status-matrix` bind it
 *     with `:value="…"`. Alpine owns the attribute there and writes its own
 *     (stale) state back over whatever the morph put in, so an observer would be
 *     racing a binding and would sometimes read the value it was meant to catch
 *     and sometimes the one it was meant to replace.
 *
 * A separate attribute that nothing binds has neither problem. It is written by
 * the server on every render and by nobody else, which makes "the server changed
 * it" a fact the DOM can state rather than something to be inferred.
 *
 * WHY NOT A LIVEWIRE HOOK. `morph.updated` exists and fires (measured), but it
 * would make every component that wants this depend on Livewire being present,
 * and these components are documented to work in a plain form too. A mutation on
 * an attribute is true whoever wrote it.
 *
 * @param {HTMLElement} el       the element carrying the attribute — the component root
 * @param {Function}    onChange called with the new value, only when it differs
 * @returns {Function}  disconnects the observer; call it from destroy()
 */
export const WK_SERVER_VALUE_ATTRIBUTE = 'data-wk-server-value';

export function observeServerValue(el, onChange) {
    // Defensive per the house rule for observers: a component may be torn down
    // between init() and the first callback, and an observer firing into a dead
    // scope throws where nobody is looking.
    if (! el || typeof MutationObserver === 'undefined' || typeof onChange !== 'function') {
        return () => {};
    }

    const observer = new MutationObserver(() => {
        const value = el.getAttribute(WK_SERVER_VALUE_ATTRIBUTE);

        if (value !== null) {
            onChange(value);
        }
    });

    observer.observe(el, { attributes: true, attributeFilter: [WK_SERVER_VALUE_ATTRIBUTE] });

    return () => observer.disconnect();
}
