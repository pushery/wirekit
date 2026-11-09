/**
 * A field the browser holds invalid says why, under the red it turns.
 *
 * The house fields turn red through `:user-invalid` (dist/wirekit.css, "A required field the reader
 * left invalid is red") once the reader has changed a field and left it, or tried to send its form.
 * A form whose submit the browser stops never reaches Livewire, so no message of the server arrives,
 * and the browser points its own bubble at the first field only, for a moment: every other red
 * field stood without a word, which tells the eye that something is wrong and nobody what
 * (WCAG 3.3.1).
 *
 * So the browser's own message, already in the reader's language, stands under each field it holds
 * invalid, as utils/required-check.js does for a component that sends through a hidden field, and
 * the field is described by it and marked invalid while it stands there. It comes when the browser
 * checks the form (`invalid`) and when the reader leaves a field the browser paints red, follows the
 * value as the reader types, and goes once the field is valid. The browser's bubble gives way to it,
 * so the first invalid field of the form takes the focus here, with what hides it opened
 * (utils/focus-invalid.js).
 *
 * A field that shows a message of the server already, an `aria-invalid="true"` this did not set,
 * keeps that one alone. The message is no markup of the view: a Livewire update renders the field
 * from the server again and takes it away with the state that put it there.
 *
 * Installed once per page.
 */
import { focusWhenReady, revealField } from './focus-invalid.js';

/**
 * The installation is remembered on `window`, the one scope a second bundle on the page, or a
 * second evaluation of this one, shares with the first.
 */
const INSTALLED_FLAG = '__wirekitFieldValidity';
const IDS_KEY = '__wirekitFieldValidityIds';

/** The attribute the message carries; its look is the class `wk-field-validity` (dist/wirekit.css). */
export const WK_FIELD_VALIDITY_ATTRIBUTE = 'data-wk-field-validity';

/** What a field carries while a message of this module describes it, so its `aria-invalid` is known as ours. */
export const WK_FIELD_VALIDITY_SHOWN_ATTRIBUTE = 'data-wk-field-validity-shown';

/** How many animation frames the focus waits for a field that is still hidden. */
const FRAMES_TO_WAIT = 10;

/** The message standing under each field. */
const shown = new WeakMap();

/**
 * A house field the browser validates: a form control with the kit's field class, or the kit's
 * checkbox, whose control carries no field class because the box beside it draws its state. The
 * browser stops a form at an unchecked required box as at any other field, so it says why as well.
 */
export function isHouseField(el) {
    return !! el
        && typeof el.matches === 'function'
        && (el.matches('.wk-field') || isHouseBox(el))
        && typeof el.checkValidity === 'function'
        && el.willValidate === true;
}

/** The control of the kit's checkbox, which always carries `data-wk-indeterminate`. */
function isHouseBox(el) {
    return el.type === 'checkbox' && typeof el.hasAttribute === 'function' && el.hasAttribute('data-wk-indeterminate');
}

/** Whether the browser paints the field red: `:user-invalid`, which a field the server emptied is spared. */
export function paintedInvalid(field) {
    if (field.hasAttribute('data-wk-cleared')) {
        return false;
    }

    try {
        return field.matches(':user-invalid');
    } catch {
        // A browser without the pseudo-class paints nothing red, so there is nothing to explain.
        return false;
    }
}

/** Whether a message of the server describes the field already. */
export function rejectedByServer(field) {
    return field.getAttribute('aria-invalid') === 'true' && ! field.hasAttribute(WK_FIELD_VALIDITY_SHOWN_ATTRIBUTE);
}

/** Where the message stands: under the frame a field sits in, under the label a box sits in, or under the field. */
export function anchorOf(field) {
    const frame = field.closest('.wk-field-frame');

    if (frame) {
        return frame;
    }

    const box = field.type === 'checkbox' || field.type === 'radio' ? field.closest('label') : null;

    return box ?? field;
}

/** An id for the message of a field without one of its own. */
function nextId() {
    window[IDS_KEY] = (window[IDS_KEY] ?? 0) + 1;

    return `wk-field-${window[IDS_KEY]}`;
}

/** Add or take away one id in the field's `aria-describedby`, the message's first while it stands. */
function describe(field, id, on) {
    const ids = String(field.getAttribute('aria-describedby') ?? '').split(/\s+/).filter((part) => part !== '' && part !== id);

    if (on) {
        ids.unshift(id);
    }

    if (ids.length > 0) {
        field.setAttribute('aria-describedby', ids.join(' '));
    } else {
        field.removeAttribute('aria-describedby');
    }
}

/**
 * Write the browser's message for the field under it, once, and keep it in step with the value.
 *
 * @param {HTMLInputElement|HTMLSelectElement|HTMLTextAreaElement} field
 */
export function showValidity(field) {
    const text = typeof field.validationMessage === 'string' ? field.validationMessage : '';

    if (text === '' || rejectedByServer(field)) {
        return;
    }

    let message = shown.get(field);

    if (! message || ! message.isConnected) {
        message = field.ownerDocument.createElement('p');
        message.setAttribute(WK_FIELD_VALIDITY_ATTRIBUTE, '');
        message.setAttribute('data-wk-prose-skip', '');
        message.className = 'wk-field-validity';
        message.id = `${field.id || nextId()}-validity`;
        anchorOf(field).after(message);
        shown.set(field, message);
        describe(field, message.id, true);
        field.setAttribute('aria-invalid', 'true');
        field.setAttribute(WK_FIELD_VALIDITY_SHOWN_ATTRIBUTE, '');
    }

    message.textContent = text;
}

/**
 * Take the message away, and with it the description and the invalid state it gave the field.
 *
 * @param {Element} field
 */
export function clearValidity(field) {
    const message = shown.get(field);

    if (! message) {
        return;
    }

    shown.delete(field);
    message.remove();
    describe(field, message.id, false);

    if (field.hasAttribute(WK_FIELD_VALIDITY_SHOWN_ATTRIBUTE)) {
        field.removeAttribute(WK_FIELD_VALIDITY_SHOWN_ATTRIBUTE);
        field.removeAttribute('aria-invalid');
    }
}

/** A field the reader changed: the message follows the value, and goes once the field is valid. */
function follow(field) {
    if (! shown.has(field)) {
        return;
    }

    if (field.validity && field.validity.valid) {
        clearValidity(field);
    } else {
        showValidity(field);
    }
}

/**
 * The browser checks a field and finds it invalid: its message, no bubble, and the focus on the
 * first invalid field of the form, as the browser gives it to the first.
 *
 * @param {Event} event
 */
export function onInvalid(event) {
    const field = event.target;

    if (! isHouseField(field) || rejectedByServer(field)) {
        return;
    }

    event.preventDefault();
    showValidity(field);

    const form = field.form;
    const first = form && form.elements
        ? Array.from(form.elements).find((el) => el.willValidate && el.validity && ! el.validity.valid)
        : field;

    if (first === field && field.ownerDocument.activeElement !== field) {
        revealField(field);
        focusWhenReady(field, FRAMES_TO_WAIT, (fn) => window.requestAnimationFrame(fn));
    }
}

/**
 * Install once per page. Safe to call from every bundle, and from a bundle evaluated again: every
 * call after the first does nothing.
 */
export function registerFieldValidity() {
    if (typeof document === 'undefined' || typeof window === 'undefined' || window[INSTALLED_FLAG]) {
        return;
    }

    window[INSTALLED_FLAG] = true;

    // Heard on the way down: `invalid` does not bubble, and a field's own handler may stop the
    // others.
    document.addEventListener('invalid', onInvalid, true);

    // A field the reader changed and left, which the browser now paints red, says why at once.
    const left = (event) => {
        const field = event.target;

        if (isHouseField(field) && paintedInvalid(field)) {
            showValidity(field);
        } else if (isHouseField(field)) {
            follow(field);
        }
    };

    document.addEventListener('change', left, true);
    document.addEventListener('focusout', left, true);
    document.addEventListener('input', (event) => {
        if (isHouseField(event.target)) {
            follow(event.target);
        }
    }, true);
}
