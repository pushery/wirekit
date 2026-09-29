/**
 * Notice when the server changed a value the component is showing.
 *
 * Seeding the value into `x-data` does not follow it reliably. A Livewire morph
 * that rewrites the `x-data` attribute makes Alpine initialize the component
 * again (a new scope before Alpine 3.16, the same one reset to the seed from
 * 3.16), and an effect queued against the scope before the morph can flush
 * afterwards and write the old value last: a reader returning to a value they
 * already held sees the one they came from. So the value lives in an attribute
 * Alpine does not initialize from, and this helper applies a change of it to the
 * live scope.
 *
 * Why a dedicated attribute, rather than reading the hidden input the component
 * already has: across a real round trip the two shapes in this library behave
 * differently, and only one of them is watchable:
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
 * Why not a Livewire hook: `morph.updated` exists and fires, but it would make
 * every component that wants this depend on Livewire being present,
 * and these components are documented to work in a plain form too. A mutation on
 * an attribute is true whoever wrote it.
 *
 * A mutation record says the attribute was WRITTEN, not that it changed: writing
 * the same value again is a record too. So the helper compares with the value the
 * server wrote last and stays quiet when it is the same. That answers "did the
 * server change it?". Whether the new value differs from what the reader has in
 * front of them is the caller's question, and each caller asks it, because a
 * reader's own choice can reach the server first and come back unchanged.
 *
 * @param {HTMLElement} el        the element carrying the attribute — the component root
 * @param {Function}    onChange  called with the new value when it differs from the one the
 *                                server wrote before; the value present when observing starts
 *                                counts as the first
 * @param {string}      attribute the attribute to follow; a component whose server half is not
 *                                its value (the options of a server search) names its own
 * @returns {Function}  disconnects the observer; call it from destroy()
 */
export const WK_SERVER_VALUE_ATTRIBUTE = 'data-wk-server-value';

export function observeServerValue(el, onChange, attribute = WK_SERVER_VALUE_ATTRIBUTE) {
    // Defensive per the house rule for observers: a component may be torn down
    // between init() and the first callback, and an observer firing into a dead
    // scope throws where nobody is looking. A root without `getAttribute` is not
    // an element and has no attribute to follow; reading one here would throw
    // inside the caller's init().
    if (! el || typeof el.getAttribute !== 'function' || typeof MutationObserver === 'undefined' || typeof onChange !== 'function') {
        return () => {};
    }

    let last = el.getAttribute(attribute);

    const observer = new MutationObserver(() => {
        const value = el.getAttribute(attribute);

        if (value === null || value === last) {
            return;
        }

        last = value;
        onChange(value);
    });

    observer.observe(el, { attributes: true, attributeFilter: [attribute] });

    return () => observer.disconnect();
}
