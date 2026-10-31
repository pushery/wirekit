/**
 * The reader entering and leaving a component as one unit.
 *
 * `focus` and `blur` do not bubble. A composite control takes focus on its parts (a box per digit,
 * the two ends of a range, a row of radios, a field with a panel teleported elsewhere) and never on
 * its root, and a listener a developer writes on the component's tag lands on that root. So an
 * `x-on:blur` on a tags input or a `wire:blur` on a rating never ran. Handing the listener to every
 * part instead would run it on each move between them, which is not the reader leaving.
 */

import { belongsThroughTeleports } from './teleport.js';

/**
 * Follow the reader into and out of a component. A panel the component teleported elsewhere in the
 * document counts as part of it, so moving into its own listbox or picker is not leaving.
 *
 * A press counts like focus. WebKit does not focus a button on a click, so a star or a segment
 * chosen with the pointer there never takes focus, and the reader leaves it by pressing somewhere
 * else rather than by moving focus.
 *
 * @param {HTMLElement|null|undefined} root The component's root.
 * @param {Object} [handlers]
 * @param {(event: Event) => void} [handlers.enter] Runs when focus or a press arrives from outside,
 *     with the `focusin` or `pointerdown` that brought it.
 * @param {(focusOut: FocusEvent|null) => void} [handlers.leave] Runs when the reader leaves: with the
 *     `focusout` of the part that lost focus, or with null when focus or a press arriving elsewhere
 *     showed it.
 * @returns {() => void} Stops following.
 */
export function watchFocusUnit(root, handlers = {}) {
    const doc = root?.ownerDocument;
    let inside = false;
    let stopped = false;
    // Whether the latest press landed inside. A press on a part that takes no focus, such as an
    // option of a teleported list, moves focus to nowhere, and that is not the reader leaving.
    let pressedInside = false;

    const belongs = (node) => Boolean(node) && belongsThroughTeleports(node, root);

    const leave = (focusOut) => {
        inside = false;
        handlers.leave?.(focusOut);
    };

    const onEnterOrLeave = (event) => {
        if (stopped) {
            return;
        }

        pressedInside = event.type === 'pointerdown' && belongs(event.target);

        if (belongs(event.target)) {
            if (! inside) {
                inside = true;
                handlers.enter?.(event);
            }

            return;
        }

        // Focus or a press arrived outside while the component still counted as focused: the reader
        // pressed elsewhere, or the element that held focus was removed, which fires no `focusout`.
        if (inside) {
            leave(null);
        }
    };

    const onFocusOut = (event) => {
        if (stopped || ! inside || ! belongs(event.target) || belongs(event.relatedTarget)) {
            return;
        }

        // Focus going nowhere after a press inside: the next press or focus decides.
        if (! event.relatedTarget && pressedInside) {
            return;
        }

        leave(event);
    };

    if (doc) {
        doc.addEventListener('focusin', onEnterOrLeave, true);
        doc.addEventListener('pointerdown', onEnterOrLeave, true);
        doc.addEventListener('focusout', onFocusOut, true);
    }

    return () => {
        stopped = true;

        if (doc) {
            doc.removeEventListener('focusin', onEnterOrLeave, true);
            doc.removeEventListener('pointerdown', onEnterOrLeave, true);
            doc.removeEventListener('focusout', onFocusOut, true);
        }
    };
}

/**
 * The `focus` and `blur` `x-wk-focus-unit` fires on its element, as a field fires them: neither
 * bubbles, and `relatedTarget` names the element focus came from or goes to, where there is one.
 *
 * @param {HTMLElement} el The root of a composite control.
 * @param {string[]} [modifiers] `focus-only` leaves the `blur` to the component.
 * @returns {() => void} Stops announcing.
 */
export function announceFocusUnit(el, modifiers = []) {
    const blurs = ! modifiers.includes('focus-only');

    return watchFocusUnit(el, {
        enter(event) {
            // A root that takes focus itself has already had its own `focus` from the browser.
            if (event.type === 'focusin' && event.target === el) {
                return;
            }

            el.dispatchEvent(new FocusEvent('focus', { relatedTarget: event.relatedTarget ?? null }));
        },
        leave(focusOut) {
            // `x-model` reads the value of the element an ordinary `blur` arrives on, and a root
            // holds none: with `wire:model.blur` on it, the property would be written empty.
            if (! blurs || el._x_model || focusOut?.target === el) {
                return;
            }

            el.dispatchEvent(new FocusEvent('blur', { relatedTarget: focusOut?.relatedTarget ?? null }));
        },
    });
}

/**
 * Register `x-wk-focus-unit`. It goes on the root of a composite control, the element that takes
 * the developer's attributes, and fires `focus` there when the reader enters the control and `blur`
 * when they leave it. A listener written on the component's tag then hears the control as one
 * field, and a move between its own parts fires nothing.
 *
 * `.focus-only` is for a root that carries the component's value: its model events already fire a
 * `blur` there when the reader leaves (utils/model-events.js), and a second would run a listener
 * twice. Such a root has an `x-data` of its own, so Alpine starts it wherever it runs.
 *
 * On a morph's clone it does nothing (`skipDuringClone`). Idempotent: a second bundle registers the
 * same handler.
 *
 * @param {object} Alpine
 */
export function registerFocusUnitDirective(Alpine) {
    const skipDuringClone = typeof Alpine.skipDuringClone === 'function' ? Alpine.skipDuringClone : (callback) => callback;

    Alpine.directive('wk-focus-unit', skipDuringClone((el, { modifiers }, { cleanup }) => {
        cleanup(announceFocusUnit(el, modifiers));
    }));

    // Some of these roots have no `x-data` of their own: the two dates of a range and a row of
    // swatches need no script. A directive runs only inside a tree Alpine starts, so on a page that
    // is no Livewire component it would never run there. Registering the attribute as an init
    // selector starts it the way `x-init` starts itself (utils/indeterminate.js does the same), and
    // a listener written on the tag starts with it. Guarded: not every Alpine a page brings has it.
    if (typeof Alpine.addInitSelector === 'function') {
        Alpine.addInitSelector(() => '[x-wk-focus-unit]');
    }
}
