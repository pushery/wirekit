/**
 * A toggle, a checkbox or a radio that sends a Livewire action of its own shows the state the server
 * rendered once each request of its component has ended.
 *
 * The browser flips such a field on the press, before the server has answered. A server that
 * refuses the change, with an authorization error or a failed validation, renders nothing, and one
 * that keeps its state renders an unchanged `checked`. Without this module the field stays as the
 * press left it either way: once a field was pressed, the browser stops following its `checked` attribute, so a render
 * that writes the attribute does not reach what the field shows. After every request of the Livewire
 * component the field stands in, answered or refused, the field takes the state its `checked`
 * attribute holds, which is the one the server rendered.
 *
 * Only a field that sends an action of its own, `wire:click` or `wire:change`, is put back. A field
 * bound with `wire:model` follows its model through Livewire, one under the optimistic layer
 * (`x-bind:checked`) follows that layer, and a field that sends nothing holds the reader's choice
 * until a form sends it. The kit's fields are told apart by the frame they draw beside the native
 * one, `wk-choice-frame`, so a field of the application's own is left alone.
 *
 * A request puts back only what it went out with. Livewire holds an action back while a request of
 * its component is out and sends it once that one has ended, so a toggle pressed in the meantime
 * still holds its old `checked` attribute when the first answer is written. So the state each field
 * shows is noted as a request goes out, and a field that shows a state of its own, one no render
 * wrote yet, counts as carried by that request until it ends. At its end, a field the reader changed
 * since is left to the request its press sent, and one that another request still out carries is
 * left to the last of them to end.
 *
 * Installed once per page, one hook on Livewire's requests that lives as long as the page.
 */

/**
 * The installation is remembered on `window`, the one scope a second bundle on the page, or a second
 * evaluation of this one, shares with the first.
 */
const INSTALLED_FLAG = '__wirekitChoicesFollowTheServer';

/** The fields the module looks at. */
const FIELDS = 'input[type="checkbox"], input[type="radio"]';

/**
 * How many requests that are still out each field went out with while it showed a state of its own.
 *
 * @type {WeakMap<Element, number>}
 */
const carriedBy = new WeakMap();

/**
 * Whether a field is a toggle, a checkbox or a radio of the kit: the frame it draws stands after it
 * among its siblings.
 *
 * @param {Element} input
 * @returns {boolean}
 */
function isKitChoice(input) {
    for (let el = input.nextElementSibling; el; el = el.nextElementSibling) {
        if (el.classList && el.classList.contains('wk-choice-frame')) {
            return true;
        }
    }

    return false;
}

/**
 * Whether a field sends an action of its own and leaves its state to the server's render.
 *
 * @param {Element} input
 * @returns {boolean}
 */
function leavesItsStateToTheServer(input) {
    const names = typeof input.getAttributeNames === 'function' ? input.getAttributeNames() : [];
    const sends = names.some((name) => /^wire:(click|change)(\.|$)/.test(name));
    const bound = names.some((name) => name.startsWith('wire:model') || name === 'x-bind:checked' || name === ':checked');

    return sends && ! bound;
}

/**
 * Put every such field in `root` back on the state its `checked` attribute holds.
 *
 * With `shown`, what the fields showed when the request went out, a field the reader changed since
 * is left alone: the press sent an action of its own, and the end of that request decides. So is a
 * field that another request still out went out with.
 *
 * @param {Element|null|undefined} root  the element of the Livewire component whose request ended
 * @param {Map<Element, boolean>|null} [shown]  each field's `checked` as the request went out
 */
export function followTheServer(root, shown = null) {
    if (! root || typeof root.querySelectorAll !== 'function') {
        return;
    }

    root.querySelectorAll(FIELDS).forEach((input) => {
        if (input.checked === input.defaultChecked || ! isKitChoice(input) || ! leavesItsStateToTheServer(input)) {
            return;
        }

        const changedSince = shown !== null && shown.has(input) && shown.get(input) !== input.checked;

        if (! changedSince && ! carriedBy.has(input)) {
            input.checked = input.defaultChecked;
        }
    });
}

/**
 * Note what each field in `root` shows as a request goes out, and count every such field that shows
 * a state of its own as carried by the request.
 *
 * @param {Element|null|undefined} root
 * @returns {{ shown: Map<Element, boolean>, carried: Element[] }}
 */
export function noteTheSend(root) {
    const shown = new Map();
    const carried = [];

    if (! root || typeof root.querySelectorAll !== 'function') {
        return { shown, carried };
    }

    root.querySelectorAll(FIELDS).forEach((input) => {
        shown.set(input, input.checked);

        if (input.checked !== input.defaultChecked && isKitChoice(input) && leavesItsStateToTheServer(input)) {
            carried.push(input);
            carriedBy.set(input, (carriedBy.get(input) ?? 0) + 1);
        }
    });

    return { shown, carried };
}

/**
 * The request that went out with these fields has ended.
 *
 * @param {Element[]} carried
 */
function letGo(carried) {
    carried.forEach((input) => {
        const left = (carriedBy.get(input) ?? 1) - 1;

        if (left > 0) {
            carriedBy.set(input, left);
        } else {
            carriedBy.delete(input);
        }
    });
}

/**
 * Hook the requests of every Livewire component on the page. The hook runs as a request goes out.
 *
 * @param {{ hook: Function }} livewire
 */
function hookLivewire(livewire) {
    livewire.hook('commit', ({ component, respond, succeed, fail }) => {
        const root = component ? component.el : null;
        const sent = noteTheSend(root);
        let ended = false;

        const end = () => {
            if (! ended) {
                ended = true;
                letGo(sent.carried);
            }
        };

        // Every request ends here, one that renders nothing or never reaches the server included.
        if (typeof respond === 'function') {
            respond(end);
        }

        // After the morph has written the answer, and at once when there is none.
        succeed(() => queueMicrotask(() => {
            end();
            followTheServer(root, sent.shown);
        }));
        fail(() => {
            end();
            followTheServer(root, sent.shown);
        });
    });
}

/**
 * Install the hook, now if Livewire has started and on its `livewire:init` otherwise.
 *
 * @param {Window} [win]
 */
export function installChoicesFollowTheServer(win = typeof window === 'undefined' ? undefined : window) {
    if (! win || win[INSTALLED_FLAG]) {
        return;
    }

    win[INSTALLED_FLAG] = true;

    if (win.Livewire && typeof win.Livewire.hook === 'function') {
        hookLivewire(win.Livewire);

        return;
    }

    if (win.document && typeof win.document.addEventListener === 'function') {
        win.document.addEventListener('livewire:init', () => {
            if (win.Livewire && typeof win.Livewire.hook === 'function') {
                hookLivewire(win.Livewire);
            }
        }, { once: true });
    }
}
