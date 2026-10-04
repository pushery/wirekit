/**
 * The events a `wire:model` on an element nobody focuses has to hear.
 *
 * Several components keep the value they hand to `wire:model` on an element the reader never
 * touches: a hidden input written by the component's script, or the textarea behind an editor.
 * Livewire passes `.blur` and `.change` on to Alpine's `x-model`, which then writes the property
 * on a `blur` or a `change` of that element itself and on nothing else. The script fired `input`
 * there and nothing more, so a `wire:model.blur` or a `wire:model.change` never reached the
 * server, and the field showed a value the component did not hold.
 */

import { belongsThroughTeleports } from './teleport.js';

/**
 * Fire `change` and `blur` on a component's model elements at the moments a native field does.
 *
 * - `change`, bubbling, when a value is committed. A component whose choices are discrete (a
 *   star, a segment, a date) calls `commit()` after each one, like a radio button or a select;
 *   a component the reader types into leaves it to the moment focus leaves, like a text field.
 * - `blur`, not bubbling, when focus leaves the component altogether, after any `change` it
 *   still owes. A panel the component teleported elsewhere in the document counts as part of
 *   it, so moving into its own listbox or picker is not leaving.
 *
 * A press counts like focus. WebKit does not focus a button on a click, so a star or a segment
 * chosen with the pointer there never takes focus, and the reader leaves it by pressing
 * somewhere else rather than by moving focus.
 *
 * `change` fires only for a value that differs from the one last committed, as a native field
 * fires it only after an edit.
 *
 * A model element the reader can focus and type into, such as the field of a number input,
 * fires its own `change` and `blur`. Its own `change` counts as the commit, and when focus leaves
 * that element itself, its own `blur` stands: neither is fired a second time.
 *
 * @param {HTMLElement|null|undefined} root The component's root.
 * @param {() => (HTMLElement|null|undefined|Array<HTMLElement|null|undefined>)} models
 *     The element or elements that carry the binding, read each time, because a morph can
 *     replace them.
 * @param {Object} [options]
 * @param {() => void} [options.beforeLeave] Runs when the reader leaves, before anything is
 *     fired: a component that writes its value late, on a timer, writes it out here.
 * @param {(el: HTMLElement) => *} [options.detail] The value to hand `x-model` with each event.
 *     `x-model` reads `detail` from a `CustomEvent` and the element's own value from any other
 *     event, which is wrong in two cases: an element holding a serialized form of the value, so
 *     that a list bound to an `array` property arrives as its JSON text, and a binding on an
 *     element that has no value, a component's root. Compared as JSON to decide what changed.
 * @returns {{ commit: () => void, dispose: () => void }}
 */
export function watchModelEvents(root, models, options = {}) {
    const doc = root?.ownerDocument;
    const committed = new WeakMap();
    let inside = false;
    let disposed = false;
    // Whether the latest press landed inside. A press on a part that takes no focus, such as an
    // option of a teleported list, moves focus to nowhere, and that is not the reader leaving.
    let pressedInside = false;

    const elements = () => {
        const found = models();

        return (Array.isArray(found) ? found : [found]).filter((el) => el && typeof el.dispatchEvent === 'function');
    };

    const belongs = (node) => Boolean(node) && belongsThroughTeleports(node, root);

    const valueOf = (el) => (typeof options.detail === 'function' ? JSON.stringify(options.detail(el)) : el.value);

    const fire = (el, type, bubbles) => {
        const event = typeof options.detail === 'function'
            ? new CustomEvent(type, { detail: options.detail(el), bubbles })
            : (type === 'blur' ? new FocusEvent('blur') : new Event(type, { bubbles }));

        el.dispatchEvent(event);
    };

    const remember = () => {
        for (const el of elements()) {
            committed.set(el, valueOf(el));
        }
    };

    const commit = () => {
        if (disposed) {
            return;
        }

        for (const el of elements()) {
            const value = valueOf(el);

            if (committed.has(el) && committed.get(el) === value) {
                continue;
            }

            committed.set(el, value);
            fire(el, 'change', true);
        }
    };

    const leave = (blurredByBrowser = null) => {
        inside = false;

        if (typeof options.beforeLeave === 'function') {
            options.beforeLeave();
        }

        commit();

        for (const el of elements()) {
            if (el !== blurredByBrowser) {
                fire(el, 'blur', false);
            }
        }
    };

    const onEnterOrLeave = (event) => {
        if (disposed) {
            return;
        }

        pressedInside = event.type === 'pointerdown' && belongs(event.target);

        if (belongs(event.target)) {
            if (! inside) {
                inside = true;
                remember();
            }

            return;
        }

        // Focus or a press arrived outside while the component still counted as focused: the reader
        // pressed elsewhere, or the element that held focus was removed, which fires no `focusout`.
        if (inside) {
            leave();
        }
    };

    const onFocusOut = (event) => {
        if (disposed || ! inside || ! belongs(event.target) || belongs(event.relatedTarget)) {
            return;
        }

        // Focus going nowhere after a press inside: the next press or focus decides.
        if (! event.relatedTarget && pressedInside) {
            return;
        }

        // The browser has just blurred this element itself if it is one of the model elements.
        leave(elements().includes(event.target) ? event.target : null);
    };

    // A native `change` on a model element the reader typed into is that element's commit.
    const onNativeChange = (event) => {
        if (! disposed && event.isTrusted && elements().includes(event.target)) {
            committed.set(event.target, valueOf(event.target));
        }
    };

    remember();

    if (doc) {
        doc.addEventListener('focusin', onEnterOrLeave, true);
        doc.addEventListener('pointerdown', onEnterOrLeave, true);
        doc.addEventListener('focusout', onFocusOut, true);
        doc.addEventListener('change', onNativeChange, true);
    }

    return {
        commit,
        dispose() {
            disposed = true;

            if (doc) {
                doc.removeEventListener('focusin', onEnterOrLeave, true);
                doc.removeEventListener('pointerdown', onEnterOrLeave, true);
                doc.removeEventListener('focusout', onFocusOut, true);
                doc.removeEventListener('change', onNativeChange, true);
            }
        },
    };
}
