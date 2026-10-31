import { watchCurrent } from './watch-current.js';

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
    // Defensive, as every observer here is: a component may be torn down
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

/**
 * The value an attribute written with `AlpinePayload::json()` carries, or `undefined` when there is
 * no attribute or its text does not parse. `null` is a value: the server saying there is none.
 *
 * @param {string|null|undefined} raw the attribute's text, as `getAttribute()` returns it
 * @returns {*}
 */
export function jsonValue(raw) {
    if (raw === null || raw === undefined || raw === '') {
        return undefined;
    }

    try {
        return JSON.parse(raw);
    } catch {
        return undefined;
    }
}

/**
 * Tell the answer to a component's own change from a value the server set.
 *
 * With `wire:model.live` every change is a request, and its answer renders the value the request
 * carried. A reader who changed the value again before the answer arrived is ahead of it, and
 * taking it would undo the newer change: a tag added meanwhile goes, a handle jumps back. Livewire
 * leaves its own property alone in that case, since the server returned what it was sent. A
 * component whose value reaches the server through a field it writes itself does the same with
 * this: it notes each value it hands to its model, and a server value equal to one of them answers
 * that change. Requests are answered in the order they were sent, so the values noted before it
 * are answered as well. A value the component never handed over is the server's own.
 *
 * @param {Function} same  whether two values are one value, as the component compares them
 * @returns {{ note: Function, answers: Function }}
 */
export function ownValues(same = (a, b) => a === b) {
    let noted = [];

    return {
        /** The component handed `value` to its model. */
        note(value) {
            noted.push(value);

            // A drag hands over a value per frame, and the answers to all but the last few arrive
            // long before it ends. Bounded, so a model that never answers cannot grow it for good.
            if (noted.length > 256) {
                noted.shift();
            }
        },

        /**
         * Whether `value`, just rendered by the server, answers a value the component handed
         * over. That value and the ones noted before it are forgotten, and every one of them when
         * the value is the server's own.
         */
        answers(value) {
            const at = noted.findIndex((handed) => same(handed, value));

            if (at === -1) {
                noted = [];

                return false;
            }

            noted = noted.slice(at + 1);

            return true;
        },
    };
}

/**
 * Whether a field carries a caller's model binding: `wire:model`, `x-model`, or `x-wk-model`, the
 * caller's `x-model` on a field inside a root of the component's own (utils/caller-model.js), with
 * any modifier. The attributes stay on the element after Livewire and Alpine have read them.
 *
 * @param {Element|null|undefined} el
 * @returns {boolean}
 */
export function hasModelBinding(el) {
    if (! el || ! el.attributes) {
        return false;
    }

    return Array.from(el.attributes).some((attribute) => /^(wire:model|x-model|x-wk-model)(\.|$)/.test(String(attribute?.name ?? '')));
}

/**
 * Follow the value an Alpine page's model writes into a field, for a component that keeps a copy of
 * it for its own display.
 *
 * A `wire:model` comes back with the server's render, through the value the root carries
 * (observeServerValue()), where the answer to a pick of the reader's own is told apart from a newer
 * pick (ownValues()). An `x-model` of a page in the browser only sets the field's `value` property,
 * without an event and without a render, so the copy would keep the value from before. `apply` gets
 * the model's value once the field's binding exists, and again whenever the page writes it. A field
 * that also carries a `wire:model` is left to the render, so a script that sets its property through
 * `$wire` without a request shows in the display with the next render.
 *
 * Called after the field's own directives have run, from `$nextTick()` in init(): the binding is
 * made on the field after its component's root has started. The watch ends with the component, and
 * goes quiet once Alpine initializes the component again (watchCurrent()).
 *
 * @param {{ $watch?: Function }} component - the Alpine component that keeps the copy
 * @param {Element|null|undefined} field - the field the page's model binds
 * @param {(value: unknown) => void} apply
 * @returns {boolean} whether there was a page's model to follow
 */
export function followPageModel(component, field, apply) {
    const model = field?._x_model;
    const names = Array.from(field?.attributes ?? []).map((attribute) => String(attribute?.name ?? ''));
    const bindsThePage = names.some((name) => /^(x-model|x-wk-model)(\.|$)/.test(name)) && ! names.some((name) => /^wire:model(\.|$)/.test(name));

    if (! bindsThePage || typeof model?.get !== 'function' || typeof component?.$watch !== 'function') {
        return false;
    }

    apply(model.get());
    watchCurrent(component, () => model.get(), (value) => apply(value));

    return true;
}
