/**
 * Whether a panel holds input its reader would lose: the question a modal or a drawer asks before
 * the reader closes it by Escape, a click beside it or its close button.
 *
 * WHEN THE STATE IS TAKEN. Not when the panel opens. A dialog is often filled by its server after it
 * opened: `wire:click="edit(42)"` opens it and the answer brings the record's values a moment later.
 * Taken at the open, those values would read as the reader's changes, and a dialog nobody touched
 * would ask. So the state is taken at the reader's first touch of the panel, a press or a key the
 * browser marks as trusted, which comes before any change of theirs and after whatever the server
 * put in. Not a focus: the focus a dialog moves into itself on opening is trusted as well, and it
 * would take the state before the server's values arrived.
 *
 * WHAT COUNTS. Every field in the panel, by its name or its id, with its value, a checked state or
 * the number of files chosen. Left out: buttons, disabled fields, and the search box of a combobox,
 * a multi-select or a scope switcher (`role="combobox"`), where a filter typed and abandoned is no
 * input; those components keep their value in a field of their own, which does count. A field the
 * caller marks `data-wk-discard-ignore`, or that sits in such an element, is left out as well.
 *
 * AND `x-wk-unsaved`. A field inside the panel that the unsaved directive marks holds a value its
 * server does not have, so the panel holds changes whatever the state taken here says.
 */

/** What a reader does to a panel before changing a field in it: a press, or a key. */
const READER_EVENTS = ['pointerdown', 'keydown'];

/** Fields a reader fills in, without the controls that only act. */
const FIELD_SELECTOR = 'input, select, textarea';
const ACTION_TYPES = new Set(['button', 'submit', 'reset', 'image']);

/**
 * The fields of a panel and their values, as one string two states can be compared by.
 *
 * @param {Element|null} root
 * @returns {string}
 */
export function fieldState(root) {
    if (! root || typeof root.querySelectorAll !== 'function') {
        return '';
    }

    const pairs = [];

    root.querySelectorAll(FIELD_SELECTOR).forEach((field, index) => {
        const type = String(field.type || '').toLowerCase();

        if (field.disabled || ACTION_TYPES.has(type)) {
            return;
        }

        if (field.getAttribute('role') === 'combobox' || field.closest('[data-wk-discard-ignore]')) {
            return;
        }

        const key = field.name || field.id || `#${index}`;
        let value;

        if (type === 'checkbox' || type === 'radio') {
            value = field.checked ? 'on' : 'off';
        } else if (type === 'file') {
            value = String(field.files ? field.files.length : 0);
        } else if (field.tagName === 'SELECT' && field.multiple) {
            value = Array.from(field.selectedOptions || []).map((option) => option.value);
        } else {
            value = field.value;
        }

        pairs.push([key, value]);
    });

    return JSON.stringify(pairs);
}

/**
 * A guard for one overlay. `arm()` on each opening, `disarm()` on each close, and `holdsChanges()`
 * when the reader is about to close it.
 *
 * @returns {{ arm: (panel: Element|null) => void, disarm: () => void, holdsChanges: (panel: Element|null) => boolean }}
 */
export function createDiscardGuard() {
    let armedOn = null;
    let taken = null;
    let listening = null;

    const take = (event) => {
        if (taken === null && event && event.isTrusted) {
            taken = fieldState(armedOn);
        }
    };

    const stopListening = () => {
        if (listening) {
            for (const type of READER_EVENTS) {
                listening.removeEventListener(type, take, true);
            }
        }

        listening = null;
    };

    return {
        arm(panel) {
            if (! panel || panel === armedOn) {
                return;
            }

            stopListening();
            armedOn = panel;
            taken = null;
            listening = panel;

            // Capture, so the state is taken before the field the reader pressed or typed into has
            // handled the event. Passive, because the guard only reads: it never cancels the
            // press or the key it listens to.
            for (const type of READER_EVENTS) {
                panel.addEventListener(type, take, { capture: true, passive: true });
            }
        },

        disarm() {
            stopListening();
            armedOn = null;
            taken = null;
        },

        holdsChanges(panel) {
            const root = panel || armedOn;

            if (! root) {
                return false;
            }

            if (typeof root.querySelector === 'function' && root.querySelector('[data-wk-unsaved]')) {
                return true;
            }

            return taken !== null && fieldState(root) !== taken;
        },
    };
}
