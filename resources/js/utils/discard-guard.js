/**
 * Whether a panel holds input its reader would lose: the question a modal or a drawer asks before
 * the reader closes it by Escape, a click beside it or its close button.
 *
 * When the state is taken. Not when the panel opens. A dialog is often filled by its server after it
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
 *
 * Fields that come and go. Each field is compared with its own value at the state, not the panel
 * as a whole: a server that shows another step after the first touch adds fields nobody typed
 * into, and a whole-panel comparison read them as changes. A field that was not there when the
 * state was taken joins it at the reader's next press or key, with the value it holds then; until
 * that, or after a close from beside the panel, it counts only once it differs from the value its
 * markup gave it. A field that has gone counts no more.
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
    return JSON.stringify(fieldsOf(root).map(({ key, value }) => [key, value]));
}

/**
 * The fields of a panel that count, each with its key and its value.
 *
 * @param {Element|null} root
 * @returns {Array<{ field: Element, key: string, value: string|string[] }>}
 */
function fieldsOf(root) {
    if (! root || typeof root.querySelectorAll !== 'function') {
        return [];
    }

    const fields = [];

    root.querySelectorAll(FIELD_SELECTOR).forEach((field, index) => {
        const type = String(field.type || '').toLowerCase();

        if (field.disabled || ACTION_TYPES.has(type)) {
            return;
        }

        if (field.getAttribute('role') === 'combobox' || field.closest('[data-wk-discard-ignore]')) {
            return;
        }

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

        fields.push({ field, key: field.name || field.id || `#${index}`, value });
    });

    return fields;
}

/**
 * The value a field's markup gave it, read the way `fieldsOf()` reads its value: the default of a
 * text field, the default check of a box, the options a select had selected in its markup, or its
 * first enabled option when none had.
 *
 * @param {Element} field
 * @returns {string|string[]}
 */
function markupValueOf(field) {
    const type = String(field.type || '').toLowerCase();

    if (type === 'checkbox' || type === 'radio') {
        return field.defaultChecked ? 'on' : 'off';
    }

    if (type === 'file') {
        return '0';
    }

    if (field.tagName === 'SELECT') {
        const options = Array.from(field.options || []);
        const selected = options.filter((option) => option.defaultSelected).map((option) => option.value);

        if (field.multiple) {
            return selected;
        }

        return selected.length > 0 ? selected[0] : (options.find((option) => ! option.disabled)?.value ?? '');
    }

    return field.defaultValue ?? '';
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

    // The state is each field's value, by the field. The first press or key takes every field; a
    // later one adds the fields that appeared since, with the value they hold before it acts.
    const take = (event) => {
        if (! event || ! event.isTrusted) {
            return;
        }

        if (taken === null) {
            taken = new Map();
        }

        for (const { field, value } of fieldsOf(armedOn)) {
            if (! taken.has(field)) {
                taken.set(field, JSON.stringify(value));
            }
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

            if (taken === null) {
                return false;
            }

            // A field taken that has gone is not in this list, and counts no more.
            return fieldsOf(root).some(({ field, value }) => JSON.stringify(value) !== (taken.has(field) ? taken.get(field) : JSON.stringify(markupValueOf(field))));
        },
    };
}
