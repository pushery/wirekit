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
 * It goes as well when its form is reset, which puts the fields back without an `input` or a
 * `change` and takes their red away, and when its field leaves the check: disabled, read-only or
 * switched off, most often by another control of the form, which the next change or input there
 * brings to light.
 *
 * A field that shows a message of the server already, an `aria-invalid="true"` this did not set,
 * keeps that one alone. The message is no markup of the view: a Livewire update renders the field
 * from the server again and takes it away with the state that put it there.
 *
 * A message that comes or goes moves what stands below its field. A field the reader leaves by
 * pressing the pointer, most often on the button under it, therefore waits until that press is over
 * and its click is sent: shown at once, the message pushes the button from under the pointer, the
 * release lands beside it, and the browser sends no click at all.
 *
 * A form that says why in its own words keeps all of this away with `data-wk-field-validity-off`,
 * on the form or on anything around its fields. The red stays, so such a form has to say why itself.
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

/** What keeps this module away: from every field inside the element that carries it, and from every field of a form that does. */
export const WK_FIELD_VALIDITY_OFF_ATTRIBUTE = 'data-wk-field-validity-off';

/**
 * How long a field left during a tap waits for the click once the finger is up. A finger and a pen
 * send their click after the release, with the mouse events the browser makes up for them, and the
 * field loses the focus only then; a browser that waits for a second tap first takes about a third
 * of a second.
 */
const TAP_SETTLE_MS = 400;

/** The longest a press holds a message back. One that never reports its end must not keep a field silent. */
const PRESS_CEILING_MS = 10000;

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
        && el.willValidate === true
        && ! switchedOff(el);
}

/**
 * Whether the developer keeps this module away from the field: it stands inside
 * `data-wk-field-validity-off`, or it belongs to a form that carries it, which a field outside its
 * form (`form="…"`) does without standing inside.
 */
export function switchedOff(field) {
    const around = typeof field.closest === 'function' && field.closest(`[${WK_FIELD_VALIDITY_OFF_ATTRIBUTE}]`) !== null;
    const form = field.form;

    return around || (!! form && typeof form.hasAttribute === 'function' && form.hasAttribute(WK_FIELD_VALIDITY_OFF_ATTRIBUTE));
}

/** Whether a field still takes part: the browser checks it, and the module is not switched off for it. */
function takesPart(field) {
    return field.willValidate !== false && ! switchedOff(field);
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

/** A field the reader changed and left: painted red it says why, and a message it shows follows its value. */
function leave(field) {
    if (paintedInvalid(field)) {
        showValidity(field);
    } else {
        follow(field);
    }
}

/**
 * The fields of a form that left the check since their message came lose it: no input of the
 * reader's reaches a disabled or read-only field, and the browser no longer paints it red.
 *
 * @param {HTMLFormElement} form
 */
function sweepForm(form) {
    if (! form || ! form.elements) {
        return;
    }

    for (const field of Array.from(form.elements)) {
        if (shown.has(field) && ! takesPart(field)) {
            clearValidity(field);
        }
    }
}

/** The fields left while a press is under way. They say what they have to say once it is over. */
const waiting = new Set();

/** The forms changed while a press is under way. Their fields that left the check lose their messages once it is over. */
const waitingForms = new Set();

/**
 * A change or an input in a form: its fields that left the check lose their messages, after a press
 * under way, since a message that goes moves what stands below it as much as one that comes.
 *
 * @param {HTMLFormElement|null|undefined} form
 */
function sweepSoon(form) {
    if (! form) {
        return;
    }

    if (pressUnderWay()) {
        waitingForms.add(form);
    } else {
        sweepForm(form);
    }
}

/** Whether the primary pointer is down. */
let pressed = false;

/** The timer that ends the wait: after the click of the press, or once no click can follow it. */
let settling = null;

/** Whether a press is under way: the pointer is down, or it is up and its click is not sent yet. */
function pressUnderWay() {
    return pressed || settling !== null;
}

/** End the wait `delay` milliseconds from now, in a task of its own, so a click sent meanwhile is through. */
function settleIn(delay) {
    if (settling !== null) {
        clearTimeout(settling);
    }

    settling = setTimeout(pressOver, delay);
}

/** The press is over: every field left during it says what it has to say. */
function pressOver() {
    if (settling !== null) {
        clearTimeout(settling);
    }

    settling = null;
    pressed = false;

    const fields = [...waiting];
    waiting.clear();

    for (const field of fields) {
        if (field.isConnected && isHouseField(field)) {
            leave(field);
        }
    }

    const forms = [...waitingForms];
    waitingForms.clear();

    for (const form of forms) {
        sweepForm(form);
    }
}

/**
 * The primary button of the primary pointer goes down, the start of a click. A second finger and
 * the button of the context menu start none. The native list of a `select` opens on the press of a
 * mouse and may keep the release to itself, so such a press is not followed either.
 *
 * @param {PointerEvent} event
 */
function onPressStart(event) {
    if (event.isPrimary === false || (typeof event.button === 'number' && event.button > 0)) {
        return;
    }

    if (event.pointerType === 'mouse' && event.target && event.target.tagName === 'SELECT') {
        return;
    }

    pressed = true;
    settleIn(PRESS_CEILING_MS);
}

/**
 * The pointer is up. A mouse sends its click in the task of the release, so the wait ends in the
 * next one; a finger and a pen send theirs later, and the field they leave loses the focus only then.
 *
 * @param {PointerEvent} event
 */
function onPressEnd(event) {
    if (! pressed || event.isPrimary === false) {
        return;
    }

    pressed = false;
    settleIn(event.pointerType === 'mouse' ? 0 : TAP_SETTLE_MS);
}

/** The browser took the press for something else, a scroll or a drag: no click follows it. */
function onPressCanceled(event) {
    if (pressed && event.isPrimary !== false) {
        pressed = false;
        settleIn(0);
    }
}

/**
 * The click of the press is being sent: the wait ends once its handlers, and the submit it starts,
 * are through. A click a key or a script made (`detail` 0) ends no press.
 *
 * @param {MouseEvent} event
 */
function onClick(event) {
    if (pressUnderWay() && event.detail !== 0) {
        pressed = false;
        settleIn(0);
    }
}

/** The keys that go with a press of the pointer rather than taking the reader to the keyboard. */
const MODIFIER_KEYS = ['Shift', 'Control', 'Alt', 'Meta'];

/**
 * A key goes down: the reader is at the keyboard, so a press that never reported its end, behind a
 * context menu or a dialog of the browser, is over.
 *
 * @param {KeyboardEvent} event
 */
function onKey(event) {
    if (pressUnderWay() && event.repeat !== true && ! MODIFIER_KEYS.includes(event.key)) {
        pressOver();
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
 * A form is reset: every message of its fields goes, with the invalid state it gave them. The
 * browser puts the fields back without an `input` or a `change` and takes their red away, so a
 * message left standing would explain a red the field no longer shows. `reset` fires before the
 * fields are put back, and a listener may cancel it, so the messages go a task later and only where
 * the reset went through, as utils/form-reset.js waits for it.
 *
 * @param {Event} event
 */
export function onReset(event) {
    const form = event.target;

    if (! form || ! form.elements) {
        return;
    }

    setTimeout(() => {
        if (event.defaultPrevented) {
            return;
        }

        for (const field of Array.from(form.elements)) {
            clearValidity(field);
        }
    }, 0);
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

    // A field the reader changed and left, which the browser now paints red, says why at once,
    // unless a press of the pointer took the reader out of it: then it waits for that press to end.
    const left = (event) => {
        const field = event.target;

        if (! isHouseField(field)) {
            return;
        }

        if (pressUnderWay()) {
            waiting.add(field);
        } else {
            leave(field);
        }
    };

    // A change or an input anywhere in a form may take one of its fields out of the check, a switch
    // such as "same as the billing address" most often, and that field hears nothing itself.
    document.addEventListener('change', (event) => {
        left(event);
        sweepSoon(event.target?.form);
    }, true);
    document.addEventListener('focusout', left, true);
    document.addEventListener('pointerdown', onPressStart, true);
    document.addEventListener('pointerup', onPressEnd, true);
    document.addEventListener('pointercancel', onPressCanceled, true);
    document.addEventListener('click', onClick, true);
    document.addEventListener('keydown', onKey, true);
    document.addEventListener('input', (event) => {
        if (isHouseField(event.target)) {
            follow(event.target);
        }

        sweepSoon(event.target?.form);
    }, true);
    document.addEventListener('reset', onReset, true);
}
