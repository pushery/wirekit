/**
 * `x-wk-unsaved`: a form that holds what its server does not have yet says so.
 *
 * Put it on the element that holds the fields, a `<form>` or a card. Every field inside it with a
 * deferred `wire:model` is compared with the value its Livewire component last received from the
 * server, the comparison `wire:dirty` makes. While a field differs:
 *
 *   - the field carries `data-wk-unsaved`, and the stylesheet frames it in
 *     `--color-wk-border-unsaved`, unless it is invalid;
 *   - the field's label carries `data-wk-unsaved-note`, the words the stylesheet writes beside
 *     it, unless the field is invalid, a radio button, or a checkbox of a list. By the room its
 *     line leaves, the label carries `data-wk-unsaved-form`: `words` while the field stays where
 *     it is, `dot` where the words would move it, with the words as the label's title, and `none`
 *     where the dot would move it too or the label does not stand above its control, decided
 *     again at every new width of the window;
 *   - the same field names the words in its description, `aria-describedby` pointing at a hidden
 *     element with them, beside any description it has, so a screen reader hears it on focus;
 *   - the scope carries `data-wk-has-unsaved`;
 *   - every `<x-wirekit::unsaved-hint>` in the scope shows its sentence, which is a polite live
 *     region, so the change from saved to unsaved is spoken once.
 *
 * `x-wk-unsaved.confirm` also asks before the reader leaves the page with unsaved fields: the
 * browser's own question on a reload, a closed tab or an address typed in, and before a
 * `wire:navigate` visit or an ordinary link to another page of the same site the hint's question,
 * in the alert dialog an unsaved hint ships with in a template. The visit is held while the dialog
 * is open and goes on, past the question, when the reader answers Leave, and only that visit: one
 * that brings no page, a download or no content, leaves the questions standing. A reader who stays
 * has the focus back on the link they pressed. A page that asks in its own way sends
 * `wirekit:unsaved-release` to `window`, and nothing asks again until the next page; the page
 * hears `wirekit:unsaved` on `window`, with `unsaved` in its detail, whenever it gains or loses an
 * unsaved field. Under the question the dialog lists what is unsaved: five fields at most,
 * each named by its label and placed by the tabs, cards and marked parts around it, and a line
 * with the number of the others; an entry closes the dialog, opens what hides its field and gives
 * the field the focus. A page without a hint asks with `confirm()`. A step through the history is
 * not stopped.
 *
 * What counts as saved: every field keeps the value it counts as saved with, at first what the
 * server rendered. A request of the component that comes back without a validation error saves
 * every deferred field, since any request sends them, not only the save; one that comes back with
 * a validation error saves nothing. A value the server writes into a field itself, loading,
 * resetting or rewriting it, is saved at once. `x-wk-unsaved.until-saved` counts only a save: a
 * `wire:submit` of the form around the field that comes back without a validation error, or a
 * `wirekit:saved` the component dispatches, with `scope` naming the scope
 * (`x-wk-unsaved.until-saved="name"`), `paths` naming bound properties and everything under them,
 * `trigger` for the part of the page around the element that sent the request, the one clicked or
 * else the one with the focus (its form, a `data-wk-unsaved-area`, its card, else its scope), or
 * none of them for every field of the
 * component. A save the server turns away without a validation error, over a conflict or an
 * edit someone else holds, says so with `wirekit:not-saved` in the same response, which names its
 * fields the same way: they stay unsaved although the request came back clean. A value the server
 * wrote, and a field a `wirekit:saved` of that response names, still count as saved. A field bound
 * with `.live` or `.lazy` sends its value itself and is not tracked; nor are file fields, nor
 * anything inside `data-wk-unsaved-off`. Without `.live`, `.blur` and `.change` send nothing:
 * they decide when the page writes the value, so a field bound with either is tracked.
 *
 * Why the component's own state: `wire:model` sits on a native field as often as on a component's
 * root, whose value lives in Alpine and never shows in the DOM. Livewire keeps both sides for every
 * component, the values the server sent and the values the page holds, and `wire:dirty` compares
 * exactly those two, and the value a field counts as saved with moves only from there. A response
 * is weighed the first time anything asks after Livewire merged it, its validation errors and its
 * dispatches included, so the question asked before a redirect that a save starts already counts
 * the save, although the redirect runs before the response is painted.
 *
 * A field in a dialog stands where the dialog's template was written: every panel is teleported to
 * the end of the page, out of the scope around it and out of the element of its Livewire component,
 * so the scope, the component, the form and the part around a field are each looked for through
 * the teleports (utils/teleport.js), and a scope counts the fields of the panels teleported out of
 * it with its own.
 *
 * Lifecycle: one Alpine effect per scope, released with the directive; one commit hook and the
 * page listeners it reads (leaving, a `wire:navigate` visit and its answer, the link pressed for it,
 * a submit, a click, a new width of the window and `wirekit:saved`), installed once per page and
 * reading the scopes that are registered.
 */

import { prefersReducedMotion, scrollBehavior } from './motion.js';
import { pluralizeGrouped } from './plural.js';
import { belongsThroughTeleports, closestThroughTeleports, teleportedClonesOf } from './teleport.js';

export const WK_UNSAVED_ATTRIBUTE = 'data-wk-unsaved';
export const WK_HAS_UNSAVED_ATTRIBUTE = 'data-wk-has-unsaved';
export const WK_UNSAVED_SCOPE_ATTRIBUTE = 'data-wk-unsaved-scope';
export const WK_UNSAVED_HINT_ATTRIBUTE = 'data-wk-unsaved-hint';
export const WK_UNSAVED_SHOWN_ATTRIBUTE = 'data-wk-unsaved-shown';
export const WK_UNSAVED_OFF_ATTRIBUTE = 'data-wk-unsaved-off';
// A part of a scope that one button saves, where it is neither a form nor a card: a `wirekit:saved`
// with `trigger` counts the fields of the part around the element that sent the request.
export const WK_UNSAVED_AREA_ATTRIBUTE = 'data-wk-unsaved-area';
export const WK_UNSAVED_QUESTION_ATTRIBUTE = 'data-wk-unsaved-confirm';
// The template an unsaved hint ships its dialog in, and the element of that dialog's title that
// takes the question.
export const WK_UNSAVED_DIALOG_ATTRIBUTE = 'data-wk-unsaved-question';
export const WK_UNSAVED_DIALOG_TEXT_ATTRIBUTE = 'data-wk-unsaved-question-text';
// The list of unsaved fields under the question, the template of one entry, and the line that
// names how many more there are, in the plural forms and the locale it carries.
export const WK_UNSAVED_DIALOG_LIST_ATTRIBUTE = 'data-wk-unsaved-question-list';
export const WK_UNSAVED_DIALOG_ITEM_ATTRIBUTE = 'data-wk-unsaved-question-item';
export const WK_UNSAVED_DIALOG_MORE_ATTRIBUTE = 'data-wk-unsaved-question-more';
/** The most unsaved fields the question names before it counts the rest. */
export const WK_UNSAVED_DIALOG_LIMIT = 5;
// What the dialog's Leave dispatches, from its button, bubbling. The listener below names it
// literally, so the event names it listens for can be read off the source.
export const WK_UNSAVED_LEAVE_EVENT = 'wirekit:unsaved-leave';
// What the page hears on `window` when it gains or loses an unsaved field, and what it sends there
// to be asked nothing more until the next page. The listeners name both literally.
export const WK_UNSAVED_STATE_EVENT = 'wirekit:unsaved';
export const WK_UNSAVED_RELEASE_EVENT = 'wirekit:unsaved-release';

// The words beside the label of an unsaved field: on the label, where the stylesheet writes them,
// and on the scope or a hint, which name them in the reader's language. Two names, so that the
// hint, which names them, never shows them itself.
export const WK_UNSAVED_NOTE_ATTRIBUTE = 'data-wk-unsaved-note';
export const WK_UNSAVED_WORDS_ATTRIBUTE = 'data-wk-unsaved-words';
// The hidden element an unsaved field's description points at, one per wording, and the start of
// its id, which marks the ids this module puts among a field's descriptions.
export const WK_UNSAVED_DESCRIPTION_ATTRIBUTE = 'data-wk-unsaved-description';
// How much of the note a label shows, measured where it stands: `words`, `dot` or `none`.
export const WK_UNSAVED_FORM_ATTRIBUTE = 'data-wk-unsaved-form';
export const WK_UNSAVED_DESCRIPTION_ID = 'wk-unsaved-description-';

/**
 * The modifiers that send a value on their own, so the field is never unsaved for long: `.live`,
 * and `.lazy`, which Livewire still sends on `change` when it stands without `.live`. `.blur` and
 * `.change` are not among them. Without `.live` they only decide when the page writes the value,
 * which then waits for the next request like the value of any other field.
 */
const SELF_SENDING_MODIFIERS = ['live', 'lazy'];

/** Asked when neither the scope nor a hint in it names a question in the reader's language. */
export const WK_UNSAVED_DEFAULT_QUESTION = 'You have unsaved changes. Leave this page anyway?';

/** Beside the label when neither the scope nor a hint in it names the words in the reader's language. */
export const WK_UNSAVED_DEFAULT_NOTE = 'Unsaved change';

/**
 * Remembered on `window`, the one scope a second copy of this module on the page shares with the
 * first, for the same reason utils/cleared-field.js gives.
 */
const INSTALLED_FLAG = '__wirekitUnsavedChanges';
const REGISTRY_KEY = '__wirekitUnsavedScopes';
const SAVED_KEY = '__wirekitUnsavedSaved';
const SETTLED_KEY = '__wirekitUnsavedSettled';
const REQUESTS_KEY = '__wirekitUnsavedRequests';
const SUBMITS_KEY = '__wirekitUnsavedSubmits';
const CLICKS_KEY = '__wirekitUnsavedClicks';
const TRIGGERS_KEY = '__wirekitUnsavedTriggers';
const ENTRIES_KEY = '__wirekitUnsavedEntries';
const FIELDS_KEY = '__wirekitUnsavedFields';
const NOTED_KEY = '__wirekitUnsavedNoted';
const DESCRIBED_KEY = '__wirekitUnsavedDescribed';
const DESCRIPTIONS_KEY = '__wirekitUnsavedDescriptions';
const TITLED_KEY = '__wirekitUnsavedTitled';
const PAGE_KEY = '__wirekitUnsavedPage';

/** How long a submit or a click waits for the request it starts before it is taken for something else. */
const SUBMIT_WINDOW_MS = 2000;

/** A value on `window`, made once, which a second copy of this module shares with the first. */
function shared(key, make) {
    if (! window[key]) {
        window[key] = make();
    }

    return window[key];
}

/**
 * The model path a field binds when it is tracked, or null.
 *
 * @param {Element} el
 * @returns {string|null}
 */
export function trackedModelPath(el) {
    if (! el || ! el.attributes) {
        return null;
    }

    if (el.tagName === 'INPUT' && String(el.getAttribute('type') ?? '').toLowerCase() === 'file') {
        return null;
    }

    if (closestThroughTeleports(el, `[${WK_UNSAVED_OFF_ATTRIBUTE}]`)) {
        return null;
    }

    for (const attribute of Array.from(el.attributes)) {
        const [name, ...modifiers] = attribute.name.split('.');

        if (name !== 'wire:model') {
            continue;
        }

        if (modifiers.some((modifier) => SELF_SENDING_MODIFIERS.includes(modifier))) {
            return null;
        }

        const path = attribute.value.trim();

        return path === '' ? null : path;
    }

    return null;
}

/**
 * The value at a dotted path, as Livewire reads one.
 *
 * @param {unknown} object
 * @param {string} path
 */
export function valueAt(object, path) {
    return path.split('.').reduce((carry, key) => (carry === null || carry === undefined ? undefined : carry[key]), object);
}

/**
 * The Livewire component a field belongs to, with both sides of its state, or null outside one.
 *
 * @param {Element} el
 * @returns {{canonical: object, reactive: object}|null}
 */
function componentOf(el) {
    const root = closestThroughTeleports(el, '[wire\\:id]');
    const id = root ? root.getAttribute('wire:id') : null;
    const wire = id && typeof window !== 'undefined' ? window.Livewire?.find?.(id) : null;
    const component = wire ? wire.__instance : null;

    return component && component.canonical && component.reactive ? component : null;
}

/**
 * Whether the page holds a value for this path that the server does not have. Reads the reactive
 * side, so an Alpine effect that calls this runs again when the field changes.
 *
 * @param {{canonical: object, reactive: object}} component
 * @param {string} path
 */
export function differsFromServer(component, path) {
    return JSON.stringify(valueAt(component.reactive, path)) !== JSON.stringify(valueAt(component.canonical, path));
}

/** Whether two JSON texts hold the same value, a number and its text counting as one. */
function sameJson(a, b) {
    if (a === b) {
        return true;
    }

    try {
        const left = JSON.parse(a);
        const right = JSON.parse(b);
        const scalar = (value) => ['string', 'number', 'boolean'].includes(typeof value);

        return scalar(left) && scalar(right) && String(left) === String(right);
    } catch {
        return false;
    }
}

/** The scope a field answers to: the nearest one around it. */
function entryOf(el) {
    const scope = closestThroughTeleports(el, `[${WK_UNSAVED_SCOPE_ATTRIBUTE}]`);

    return scope ? shared(ENTRIES_KEY, () => new WeakMap()).get(scope) ?? null : null;
}

/**
 * Whether a saved signal names this field: by its scope, by its path or a path above it, or
 * neither, which names all. A path names everything under it, so `variants.3` names
 * `variants.3.sku`, and a save of several parts can say which parts went through.
 */
function names(signal, entry, path) {
    const scope = typeof signal.scope === 'string' ? signal.scope : null;
    const paths = Array.isArray(signal.paths) ? signal.paths : null;

    if (scope !== null && (entry === null || scope !== entry.name)) {
        return false;
    }

    return paths === null || paths.some((named) => typeof named === 'string' && (path === named || path.startsWith(`${named}.`)));
}

/**
 * The part of the page a request saves, for a signal with `trigger`: the form around the element
 * that sent it, else the nearest part marked `data-wk-unsaved-area`, else its card, else its scope.
 * Null without an element. Walked by hand, so it reads only the tag and the attributes, and from a
 * teleported clone on at the template it was written from.
 *
 * @param {Element|null} el
 * @returns {Element|null}
 */
export function areaAround(el) {
    for (let at = el && typeof el.hasAttribute === 'function' ? el : null; at; at = at._x_teleportBack ?? at.parentElement) {
        if (at.tagName === 'FORM' || at.hasAttribute(WK_UNSAVED_AREA_ATTRIBUTE) || at.hasAttribute('data-wk-card') || at.hasAttribute(WK_UNSAVED_SCOPE_ATTRIBUTE)) {
            return at;
        }
    }

    return null;
}

/**
 * The element of a component that has the focus, or null where the focus stands outside it: the
 * element a key sent a request from, such as Enter on a field bound with `wire:keydown.enter` or in
 * a form without a submit button, where no click started it.
 *
 * @param {{id: string}} component
 * @returns {Element|null}
 */
function focusedIn(component) {
    const active = typeof document !== 'undefined' ? document.activeElement ?? null : null;
    const root = active ? closestThroughTeleports(active, '[wire\\:id]') : null;

    return root && root.getAttribute('wire:id') === component.id ? active : null;
}

/**
 * Whether a field stands where a signal counts: anywhere for a signal without `trigger`, inside the
 * part around the element that sent the request for one with it, and nowhere for one with it but
 * no element to read the part from.
 *
 * @param {object} signal
 * @param {Element|null} area
 * @param {Element|null} el
 */
function standsWhereItCounts(signal, area, el) {
    if (signal.trigger !== true) {
        return true;
    }

    return area !== null && el !== null && typeof area.contains === 'function' && belongsThroughTeleports(el, area);
}

/**
 * The dispatches of a response whose name the test accepts, each as what it names. The name is
 * compared at the call, `(name) => name === 'wirekit:saved'`, which is where a reader looks for the
 * events this module listens for.
 */
function signalsNamed(effects, isNamed) {
    const dispatches = effects && Array.isArray(effects.dispatches) ? effects.dispatches : [];

    return dispatches
        .filter((dispatch) => dispatch && isNamed(dispatch.name))
        .map((dispatch) => (dispatch.params && typeof dispatch.params === 'object' && ! Array.isArray(dispatch.params) ? dispatch.params : {}));
}

/**
 * Weigh the response a component received since anything last asked, once. A field the server
 * wrote is saved; so is a field a `wirekit:saved` of the response names; and, unless the response
 * carries a validation error or a `wirekit:not-saved` that names the field, every field of a scope
 * without `.until-saved`, and the fields of a form whose `wire:submit` sent the request.
 *
 * @param {object} component
 */
function settle(component) {
    const settled = shared(SETTLED_KEY, () => new WeakMap());
    const snapshot = component.snapshot ?? null;

    if (! settled.has(component)) {
        settled.set(component, snapshot);

        return;
    }

    if (settled.get(component) === snapshot) {
        return;
    }

    settled.set(component, snapshot);

    const requests = shared(REQUESTS_KEY, () => new WeakMap());
    const request = requests.get(component) ?? { sent: new Map(), form: null, sender: null };
    const saved = shared(SAVED_KEY, () => new WeakMap()).get(component);

    requests.delete(component);

    if (! saved) {
        return;
    }

    const errors = snapshot && snapshot.memo ? snapshot.memo.errors : null;
    const failed = Boolean(errors) && typeof errors === 'object' && Object.keys(errors).length > 0;
    const signals = signalsNamed(component.effects, (name) => name === 'wirekit:saved');
    const refusals = signalsNamed(component.effects, (name) => name === 'wirekit:not-saved');
    const fields = shared(FIELDS_KEY, () => new WeakMap()).get(component) ?? new Map();
    const area = areaAround(request.sender ?? null);

    for (const path of Array.from(saved.keys())) {
        // The field last compared under this path, and the scope it answers to; none outside a
        // registered scope, which counts like a scope without `.until-saved`.
        const el = fields.get(path) ?? null;
        const entry = el ? entryOf(el) : null;
        const server = JSON.stringify(valueAt(component.canonical, path));
        const sent = request.sent.get(path);
        const wrote = sent !== undefined && ! sameJson(sent, server);
        const submitted = request.form !== null && el !== null && typeof request.form.contains === 'function' && belongsThroughTeleports(el, request.form);
        const signaled = signals.some((signal) => names(signal, entry, path) && standsWhereItCounts(signal, area, el));
        const refused = refusals.some((signal) => names(signal, entry, path) && standsWhereItCounts(signal, area, el));

        if (wrote || signaled || (! failed && ! refused && (entry === null || ! entry.untilSaved || submitted))) {
            saved.set(path, server);
        }
    }
}

/**
 * The JSON of the value a field counts as saved with: what the server held when the field was
 * first seen, until a response or a signal moves it.
 *
 * @param {object} component
 * @param {string} path
 */
export function savedValueOf(component, path) {
    settle(component);

    const store = shared(SAVED_KEY, () => new WeakMap());
    let saved = store.get(component);

    if (! saved) {
        saved = new Map();
        store.set(component, saved);
    }

    if (! saved.has(path)) {
        saved.set(path, JSON.stringify(valueAt(component.canonical, path)));
    }

    return saved.get(path);
}

/**
 * Whether the page holds a value for this path other than the one it counts as saved. Reads the
 * reactive side, so an Alpine effect that calls this runs again when the field changes. The field
 * is remembered under its path, so a later response is weighed by the scope it answers to.
 *
 * @param {object} component
 * @param {string} path
 * @param {Element|null} el
 */
export function differsFromSaved(component, path, el = null) {
    if (el) {
        const store = shared(FIELDS_KEY, () => new WeakMap());

        if (! store.has(component)) {
            store.set(component, new Map());
        }

        store.get(component).set(path, el);
    }

    return JSON.stringify(valueAt(component.reactive, path)) !== savedValueOf(component, path);
}

/**
 * Count the fields a saved signal names as saved with what their server holds: the signal's
 * `scope` and `paths`. One dispatched on an element counts only inside the Livewire component
 * around it, which is where a component's own dispatch and an Alpine `$dispatch` from a button
 * start, or inside the element when no component is around it; one sent to `window` counts on
 * the whole page. With `trigger` it counts the part around the element: the element an Alpine
 * `$dispatch` starts from, or, for the component's own dispatch, which Livewire sends from the
 * component's root, the element that sent its last request.
 *
 * @param {unknown} detail
 * @param {unknown} target
 */
export function markSaved(detail, target = null) {
    const signal = detail && typeof detail === 'object' && ! Array.isArray(detail) ? detail : {};
    const element = target && typeof target.closest === 'function' ? target : null;
    const within = element ? closestThroughTeleports(element, '[wire\\:id]') ?? element : null;
    const fromRoot = element !== null && typeof element.hasAttribute === 'function' && element.hasAttribute('wire:id');
    const component = fromRoot ? componentOf(element) : null;
    const area = signal.trigger === true
        ? areaAround(fromRoot ? (component ? shared(TRIGGERS_KEY, () => new WeakMap()).get(component) ?? null : null) : element)
        : null;

    for (const entry of registry()) {
        for (const field of trackedFields(entry.el)) {
            if (entryOf(field.el) !== entry || ! names(signal, entry, field.path) || (within !== null && ! belongsThroughTeleports(field.el, within)) || ! standsWhereItCounts(signal, area, field.el)) {
                continue;
            }

            savedValueOf(field.component, field.path);
            shared(SAVED_KEY, () => new WeakMap()).get(field.component).set(field.path, JSON.stringify(valueAt(field.component.canonical, field.path)));
        }

        entry.rerun();
    }
}

/**
 * Every tracked field in the scope, with the component that owns it.
 *
 * @param {Element} scope
 * @returns {Array<{el: Element, path: string, component: object}>}
 */
export function trackedFields(scope) {
    const fields = [];

    // The scope's own elements, and those of the panels teleported out of it, which stand at the
    // end of the page.
    const elements = [
        ...Array.from(scope.getElementsByTagName('*')),
        ...teleportedClonesOf(scope).flatMap((clone) => [clone, ...Array.from(clone.getElementsByTagName('*'))]),
    ];

    for (const el of elements) {
        const path = trackedModelPath(el);
        const component = path === null ? null : componentOf(el);

        if (component) {
            fields.push({ el, path, component });
        }
    }

    return fields;
}

/** Set or remove an empty attribute. */
function flag(el, attribute, on) {
    if (on && ! el.hasAttribute(attribute)) {
        el.setAttribute(attribute, '');
    } else if (! on && el.hasAttribute(attribute)) {
        el.removeAttribute(attribute);
    }
}

/** The hints whose nearest scope is this one, so a nested scope keeps its own. */
function hintsOf(scope) {
    const selector = `[${WK_UNSAVED_HINT_ATTRIBUTE}]`;

    // A hint in a panel teleported out of the scope is the scope's as well.
    return [scope, ...teleportedClonesOf(scope)]
        .flatMap((container) => Array.from(container.querySelectorAll(selector)))
        .filter((hint) => closestThroughTeleports(hint.parentElement, `[${WK_UNSAVED_SCOPE_ATTRIBUTE}]`) === scope);
}

/** The last text of a node that holds more than white space, looked for from the end. */
function lastWords(node) {
    const children = node.childNodes ?? [];

    for (let index = children.length - 1; index >= 0; index--) {
        const child = children[index];

        if (child.nodeType === 3 && String(child.nodeValue).trim() !== '') {
            return child;
        }

        const inside = child.nodeType === 1 ? lastWords(child) : null;

        if (inside) {
            return inside;
        }
    }

    return null;
}

/**
 * Write the sentence of a hint again. The text was there, hidden; writing it changes the live
 * region, which is what a screen reader announces, where a change of `display` alone is not
 * reported by every one. The sentence stands in a badge, after the white space of its markup, so
 * the text written is the last that holds words.
 */
function announce(hint) {
    const text = hint.querySelector('[data-wk-unsaved-text]');
    const words = text ? lastWords(text) : null;

    if (words) {
        words.nodeValue = String(words.nodeValue);
    }
}

/** Whether the browser finds a field invalid after the reader touched it, where it can say. */
function userInvalid(el) {
    try {
        return typeof el.matches === 'function' && el.matches(':user-invalid');
    } catch {
        return false;
    }
}

/**
 * The control that says a field is unsaved, beside its labels and in its description: the field
 * itself, or the first field inside a component whose root carries `wire:model`. None for a radio
 * button or a checkbox bound to a list, since every option of the group would say it, and none
 * for an invalid field, whose error comes first.
 *
 * @param {{el: Element, path: string, component: object}} field
 * @returns {Element|null}
 */
function speakingControl(field) {
    const control = controlOf(field);

    if (! control || typeof control.getAttribute !== 'function') {
        return null;
    }

    const type = String(control.getAttribute('type') ?? '').toLowerCase();

    if (type === 'radio' || (type === 'checkbox' && Array.isArray(valueAt(field.component.reactive, field.path)))) {
        return null;
    }

    if (control.getAttribute('aria-invalid') === 'true' || field.el.getAttribute('aria-invalid') === 'true' || userInvalid(control)) {
        return null;
    }

    return control;
}

/**
 * The control a tracked field is: the field itself, or the first field inside a component whose
 * root carries `wire:model`.
 *
 * @param {{el: Element}} field
 * @returns {Element|null}
 */
function controlOf(field) {
    if (field.el.labels) {
        return field.el;
    }

    return typeof field.el.querySelector === 'function' ? field.el.querySelector('input:not([type="hidden"]), select, textarea') : null;
}

/**
 * The text a reader reads in an element: its text, without what is hidden from everyone. The word
 * a required group adds to its legend for a screen reader (`data-wk-required-word`) is left out
 * too: the name stands on screen in the question, where the group's own caption is the name.
 */
function spoken(el) {
    const read = (node) => Array.from(node && node.childNodes ? node.childNodes : []).map((child) => {
        if (child.nodeType === 3) {
            return String(child.nodeValue);
        }

        const hidden = child.nodeType !== 1 || child.tagName === 'TEMPLATE' || child.getAttribute('aria-hidden') === 'true' || child.hasAttribute('hidden') || child.hasAttribute('data-wk-required-word');

        return hidden ? '' : read(child);
    }).join('');

    return read(el).replace(/\s+/g, ' ').trim();
}

/**
 * The name the question gives an unsaved field: the legend of the group a radio button or a
 * checkbox of a list belongs to, else the control's own name, its label, else its `name`, else
 * the property it binds. Of a field set's legend only its caption counts (`data-wk-field-label`):
 * the question mark of its help and a mark after it stand in the legend as well.
 *
 * @param {{el: Element, path: string, component: object}} field
 * @returns {string}
 */
export function nameOf(field) {
    const control = controlOf(field) ?? field.el;
    const type = String(control.getAttribute('type') ?? '').toLowerCase();
    const grouped = type === 'radio' || (type === 'checkbox' && Array.isArray(valueAt(field.component.reactive, field.path)));

    if (grouped && typeof control.closest === 'function') {
        const legend = control.closest('fieldset')?.querySelector('legend') ?? null;
        const caption = legend && ! legend.hasAttribute('data-wk-field-label') ? legend.querySelector('[data-wk-field-label]') : null;
        const named = legend ? spoken(caption ?? legend) : '';

        if (named !== '') {
            return named;
        }
    }

    const own = String(control.getAttribute('aria-label') ?? '').trim();
    const label = control.labels && control.labels.length > 0 ? spoken(control.labels[0]) : '';

    return own || label || String(control.getAttribute('name') ?? '').trim() || field.path;
}

/**
 * Where an unsaved field stands, from the outermost: the tab of each tab panel around it, the
 * heading of each card around it, the header of each accordion item around it, and each part
 * marked `data-wk-unsaved-area` with a name. Empty where nothing around the field names a place.
 *
 * @param {{el: Element}} field
 * @returns {string}
 */
export function placeOf(field) {
    const control = controlOf(field) ?? field.el;
    const parts = [];

    for (let at = control.parentElement; at; at = at.parentElement) {
        let name = '';

        if (at.getAttribute('role') === 'tabpanel') {
            const tab = at.getAttribute('aria-labelledby') && typeof document.getElementById === 'function' ? document.getElementById(at.getAttribute('aria-labelledby')) : null;
            name = tab ? spoken(tab) : '';
        } else if (at.hasAttribute('data-wk-card')) {
            // The card's own head and heading. A card inside it has its own, which name that card,
            // and the first in the page's order can be one of them: only a collapsible card marks
            // its head, so a plain card holding collapsible ones found theirs first.
            const own = (node) => node.closest('[data-wk-card]') === at;
            const head = Array.from(at.querySelectorAll('[data-wk-card-head]')).find(own) ?? null;
            const heading = Array.from((head ?? at).querySelectorAll('h1, h2, h3, h4, h5, h6')).find(own) ?? null;
            name = heading ? spoken(heading) : (head ? spoken(head) : '');
        } else if (at.hasAttribute('data-wk-accordion-item')) {
            const head = at.querySelector('[aria-expanded]');
            name = head ? spoken(head) : '';
        } else if (at.hasAttribute(WK_UNSAVED_AREA_ATTRIBUTE)) {
            name = String(at.getAttribute(WK_UNSAVED_AREA_ATTRIBUTE)).trim();
        }

        if (name !== '') {
            parts.unshift(name);
        }
    }

    return parts.join(' › ');
}

/**
 * The unsaved fields of the scopes that ask before leaving, in the order of the page, each once
 * however many controls bind it.
 *
 * @param {Iterable<{el: Element, confirm: boolean}>} scopes
 * @returns {Array<{el: Element, path: string, component: object}>}
 */
export function unsavedFieldsOf(scopes) {
    const seen = new Set();
    const fields = [];

    for (const entry of scopes) {
        if (! entry.confirm || ! entry.el.isConnected) {
            continue;
        }

        for (const field of trackedFields(entry.el)) {
            const key = `${field.component.id ?? ''}|${field.path}`;

            if (entryOf(field.el) !== entry || seen.has(key) || ! differsFromSaved(field.component, field.path, field.el)) {
                continue;
            }

            seen.add(key);
            fields.push(field);
        }
    }

    return fields;
}

/**
 * Fill the list under the question from the dialog's own item template: the first fields, each a
 * button that calls `pick` with its field, and the line with the number of the rest in the
 * dialog's language. A dialog without the list leaves it at the question.
 *
 * @param {Element} panel
 * @param {Array<{el: Element, path: string, component: object}>} fields
 * @param {(field: object) => void} pick
 */
export function listUnsaved(panel, fields, pick) {
    const list = panel.querySelector(`[${WK_UNSAVED_DIALOG_LIST_ATTRIBUTE}]`);
    const item = panel.querySelector(`[${WK_UNSAVED_DIALOG_ITEM_ATTRIBUTE}]`);
    const more = panel.querySelector(`[${WK_UNSAVED_DIALOG_MORE_ATTRIBUTE}]`);
    const blueprint = item && item.content ? item.content.firstElementChild : null;

    if (! list || ! blueprint) {
        return;
    }

    list.textContent = '';

    for (const field of fields.slice(0, WK_UNSAVED_DIALOG_LIMIT)) {
        const entry = blueprint.cloneNode(true);
        const button = entry.querySelector('[data-wk-unsaved-question-field]') ?? entry;
        const name = entry.querySelector('[data-wk-unsaved-question-name]');
        const place = entry.querySelector('[data-wk-unsaved-question-place]');
        const comma = entry.querySelector('[data-wk-unsaved-question-comma]');
        const where = placeOf(field);

        if (name) {
            name.textContent = nameOf(field);
        }

        if (where === '') {
            place?.remove();
            comma?.remove();
        } else if (place) {
            place.textContent = where;
        }

        button.addEventListener('click', () => pick(field));
        list.appendChild(entry);
    }

    list.hidden = fields.length === 0;

    if (more) {
        const rest = fields.length - WK_UNSAVED_DIALOG_LIMIT;
        let forms;
        let marks;

        try {
            forms = JSON.parse(more.getAttribute('data-wk-unsaved-question-forms') ?? '{}');
        } catch {
            forms = {};
        }

        // The application's own marks, where it has them: the line is text a reader sees.
        try {
            marks = JSON.parse(more.getAttribute('data-wk-unsaved-question-separators') ?? 'null');
        } catch {
            marks = null;
        }

        more.textContent = rest > 0
            ? pluralizeGrouped(forms, rest, more.getAttribute('data-wk-unsaved-question-locale') || 'en', marks && typeof marks === 'object' ? marks : null)
            : '';
        more.hidden = rest <= 0;
    }
}

/**
 * Bring an unsaved field to the reader: open the tab of each tab panel around it, each closed
 * accordion item, each closed `details` and each collapsible card, then, two frames later, once the
 * opened parts are laid out, scroll it into view and give it the focus. A field in a closed part is
 * not laid out, so without opening it the focus would go nowhere.
 *
 * @param {{el: Element}} field
 */
export function goToField(field) {
    const control = controlOf(field) ?? field.el;
    const panels = [];
    const items = [];

    for (let at = control.parentElement; at; at = at.parentElement) {
        if (at.getAttribute('role') === 'tabpanel') {
            panels.unshift(at);
        }

        if (at.hasAttribute('data-wk-accordion-item')) {
            items.unshift(at);
        }

        if (at.tagName === 'DETAILS' && ! at.open) {
            at.open = true;
        }
    }

    for (const panel of panels) {
        const id = panel.getAttribute('aria-labelledby');
        const tab = id && typeof document.getElementById === 'function' ? document.getElementById(id) : null;

        if (tab && tab.getAttribute('aria-selected') !== 'true') {
            tab.click();
        }
    }

    // An item opens through its header, as a reader opens it: the first element in the item that
    // says whether it is expanded, since an accordion nested in it stands in its panel, after it.
    for (const item of items) {
        const head = item.querySelector('[aria-expanded]');

        if (head && head.getAttribute('aria-expanded') !== 'true' && typeof head.click === 'function') {
            head.click();
        }
    }

    if (typeof control.closest === 'function' && control.closest('[data-wk-card-collapsible]')) {
        control.dispatchEvent(new CustomEvent('wirekit:card-open', { bubbles: true }));
    }

    const later = typeof window.requestAnimationFrame === 'function'
        ? (fn) => window.requestAnimationFrame(() => window.requestAnimationFrame(fn))
        : (fn) => setTimeout(fn, 32);

    later(() => {
        if (typeof control.scrollIntoView === 'function') {
            // Smooth unless the reader asked for reduced motion, then a jump, whatever the page's own
            // `scroll-behavior` says.
            control.scrollIntoView({ block: 'center', behavior: scrollBehavior(! prefersReducedMotion()) });
        }

        if (typeof control.focus === 'function') {
            control.focus({ preventScroll: true });
        }
    });
}

/**
 * The labels that say a field is unsaved: those of its speaking control.
 *
 * @param {{el: Element, path: string, component: object}} field
 * @returns {Element[]}
 */
function labelsToNote(field) {
    const control = speakingControl(field);

    return control && control.labels ? Array.from(control.labels) : [];
}

/**
 * The hidden element a description of these words points at: one per wording, in the body and
 * outside every component, where no morph takes it out, and made again once a visit replaced the
 * body. Null where the page has no body to hold it.
 *
 * @param {string} words
 * @returns {Element|null}
 */
function descriptionOf(words) {
    if (typeof document === 'undefined' || ! document.body || typeof document.createElement !== 'function') {
        return null;
    }

    const store = shared(DESCRIPTIONS_KEY, () => ({ made: 0, byWords: new Map() }));
    let description = store.byWords.get(words);

    if (! description || ! description.isConnected) {
        store.made += 1;
        description = document.createElement('span');
        description.setAttribute('id', `${WK_UNSAVED_DESCRIPTION_ID}${store.made}`);
        description.setAttribute('hidden', '');
        description.setAttribute(WK_UNSAVED_DESCRIPTION_ATTRIBUTE, '');
        description.textContent = words;
        document.body.appendChild(description);
        store.byWords.set(words, description);
    }

    return description;
}

/**
 * Put an id of this module among a control's descriptions, or take every one of them off, and
 * leave every other description where it stands.
 *
 * @param {Element} control
 * @param {string|null} id
 */
function describe(control, id) {
    const others = String(control.getAttribute('aria-describedby') ?? '')
        .split(/\s+/)
        .filter((token) => token !== '' && ! token.startsWith(WK_UNSAVED_DESCRIPTION_ID));
    const next = (id === null ? others : [...others, id]).join(' ');

    if (next === '') {
        if (control.hasAttribute('aria-describedby')) {
            control.removeAttribute('aria-describedby');
        }
    } else if (control.getAttribute('aria-describedby') !== next) {
        control.setAttribute('aria-describedby', next);
    }
}

/**
 * Name the words in the description of every control that says its field is unsaved, and take
 * them off every control the scope described before that no longer does. Remembered per scope,
 * as the notes on the labels are.
 *
 * @param {Element} scope
 * @param {Set<Element>} controls
 */
function writeDescriptions(scope, controls) {
    const store = shared(DESCRIBED_KEY, () => new WeakMap());
    const words = controls.size > 0 ? noteFor(scope) : '';
    const description = words === '' ? null : descriptionOf(words);
    const described = description === null ? new Set() : controls;

    for (const control of store.get(scope) ?? []) {
        if (! described.has(control)) {
            describe(control, null);
        }
    }

    for (const control of described) {
        describe(control, description.getAttribute('id'));
    }

    store.set(scope, described);
}

/** The control a label names: the one it is for, else the first field inside it. */
function controlOfLabel(label) {
    if (label.control) {
        return label.control;
    }

    return typeof label.querySelector === 'function' ? label.querySelector('input:not([type="hidden"]), select, textarea') : null;
}

/** Give a label the words as its title, unless it has a title of its own. */
function entitle(label, words) {
    if (label.hasAttribute('title')) {
        return;
    }

    label.setAttribute('title', words);
    shared(TITLED_KEY, () => new WeakSet()).add(label);
}

/** Take off the title this module gave a label, and only that one. */
function untitle(label) {
    const titled = shared(TITLED_KEY, () => new WeakSet());

    if (titled.has(label)) {
        label.removeAttribute('title');
        titled.delete(label);
    }
}

/**
 * Choose how much of the note a label shows, measured where it stands: the words while its field
 * stays where it is and the label holds them, else a dot with the words as the label's title,
 * else nothing. A label that does not stand above its control, around a switch or beside a field
 * in a toolbar, shows nothing, since words in its line push what stands beside it. Each form is
 * measured against the place the field has without a note and the width the label's content
 * takes without one; on a page without layout to measure, the words stay.
 *
 * @param {Element} label
 * @param {string} words
 */
export function placeNote(label, words) {
    const control = controlOfLabel(label);

    if (! control || typeof label.getBoundingClientRect !== 'function' || typeof control.getBoundingClientRect !== 'function') {
        return;
    }

    const show = (form) => {
        if (label.getAttribute(WK_UNSAVED_FORM_ATTRIBUTE) !== form) {
            label.setAttribute(WK_UNSAVED_FORM_ATTRIBUTE, form);
        }
    };

    show('none');
    untitle(label);

    if (label.getBoundingClientRect().bottom > control.getBoundingClientRect().top + 1) {
        return;
    }

    const top = control.getBoundingClientRect().top;

    // The note follows the label's last word with no space between, so where that word stands
    // alone on its line the two cannot part: the note runs out of the label sideways instead of
    // onto a line of its own, and the field stays where it is. The label's content then grows wider
    // than it was without the note.
    const measurable = typeof label.scrollWidth === 'number' && typeof label.clientWidth === 'number' && label.clientWidth > 0;
    const width = measurable ? Math.max(label.scrollWidth, label.clientWidth) : 0;
    const fits = () => Math.abs(control.getBoundingClientRect().top - top) < 0.5 && (! measurable || label.scrollWidth <= width + 1);

    show('words');

    if (fits()) {
        return;
    }

    show('dot');

    if (fits()) {
        entitle(label, words);

        return;
    }

    show('none');
}

/** The words beside the label: the scope's own, a hint's, or the English default. An empty value shows none. */
export function noteFor(scope) {
    if (scope.hasAttribute(WK_UNSAVED_WORDS_ATTRIBUTE)) {
        return scope.getAttribute(WK_UNSAVED_WORDS_ATTRIBUTE) ?? '';
    }

    const hint = hintsOf(scope).find((candidate) => candidate.hasAttribute(WK_UNSAVED_WORDS_ATTRIBUTE));

    return hint ? hint.getAttribute(WK_UNSAVED_WORDS_ATTRIBUTE) ?? '' : WK_UNSAVED_DEFAULT_NOTE;
}

/**
 * Put the words on the labels that say their field is unsaved, and take them off every label the
 * scope wrote them on before that no longer does. Remembered per scope, so a label outside the
 * scope, which a `for` points at, is cleared as well, and a label a morph cleared is written again.
 *
 * @param {Element} scope
 * @param {Set<Element>} labels
 */
function writeNotes(scope, labels) {
    const store = shared(NOTED_KEY, () => new WeakMap());
    const words = labels.size > 0 ? noteFor(scope) : '';
    const noted = words === '' ? new Set() : labels;

    for (const label of store.get(scope) ?? []) {
        if (! noted.has(label)) {
            label.removeAttribute(WK_UNSAVED_NOTE_ATTRIBUTE);
            label.removeAttribute(WK_UNSAVED_FORM_ATTRIBUTE);
            untitle(label);
        }
    }

    // A label is measured when it takes the words, when they change, and when a morph took its
    // form off; otherwise it keeps the form it was given, until the window takes a new width.
    for (const label of noted) {
        const fresh = label.getAttribute(WK_UNSAVED_NOTE_ATTRIBUTE) !== words || ! label.hasAttribute(WK_UNSAVED_FORM_ATTRIBUTE);

        if (label.getAttribute(WK_UNSAVED_NOTE_ATTRIBUTE) !== words) {
            label.setAttribute(WK_UNSAVED_NOTE_ATTRIBUTE, words);
        }

        if (fresh) {
            placeNote(label, words);
        }
    }

    store.set(scope, noted);
}

/**
 * Compare every field of the scope and write the result. Returns whether any field is unsaved.
 *
 * @param {Element} scope
 */
export function refreshScope(scope) {
    let unsaved = false;
    const labels = new Set();
    const controls = new Set();

    // Every field is compared and marked, no early exit: an effect that calls this reads each
    // field's value, and that read is what makes it run again when the field changes.
    for (const field of trackedFields(scope)) {
        const differs = differsFromSaved(field.component, field.path, field.el);

        flag(field.el, WK_UNSAVED_ATTRIBUTE, differs);
        unsaved = unsaved || differs;

        if (differs) {
            const control = speakingControl(field);

            if (control) {
                controls.add(control);
            }

            for (const label of labelsToNote(field)) {
                labels.add(label);
            }
        }
    }

    writeNotes(scope, labels);
    writeDescriptions(scope, controls);
    flag(scope, WK_HAS_UNSAVED_ATTRIBUTE, unsaved);

    for (const hint of hintsOf(scope)) {
        const wasShown = hint.hasAttribute(WK_UNSAVED_SHOWN_ATTRIBUTE);

        flag(hint, WK_UNSAVED_SHOWN_ATTRIBUTE, unsaved);

        if (unsaved && ! wasShown) {
            announce(hint);
        }
    }

    return unsaved;
}

/** The question asked before leaving: the scope's own, a hint's, or the English default. */
export function questionFor(scope) {
    const own = scope.getAttribute(WK_UNSAVED_QUESTION_ATTRIBUTE);

    if (own) {
        return own;
    }

    const hint = hintsOf(scope).find((candidate) => candidate.getAttribute(WK_UNSAVED_QUESTION_ATTRIBUTE));

    return hint ? hint.getAttribute(WK_UNSAVED_QUESTION_ATTRIBUTE) : WK_UNSAVED_DEFAULT_QUESTION;
}

/** @returns {Set<{el: Element, confirm: boolean, rerun: Function}>} */
function registry() {
    if (! window[REGISTRY_KEY]) {
        window[REGISTRY_KEY] = new Set();
    }

    return window[REGISTRY_KEY];
}

/**
 * The first scope that asks before leaving and holds an unsaved field right now. Answered from
 * the components' state at this moment rather than from the attributes, which follow it a frame
 * late: when a save redirects, the server's values are merged before the redirect runs.
 */
export function scopeHoldingUnsaved(scopes) {
    for (const entry of scopes) {
        if (! entry.confirm || ! entry.el.isConnected) {
            continue;
        }

        if (trackedFields(entry.el).some((field) => differsFromSaved(field.component, field.path, field.el))) {
            return entry.el;
        }
    }

    return null;
}

/** The dialog each hint's template was put into the page as, once it was needed. */
const mountedDialogs = new WeakMap();

/**
 * The alert dialog the question before a visit is asked in, by its Alpine data: the one the
 * scope's own hint ships with, else the one of a hint elsewhere on the page, put into the page
 * the first time it is needed and kept there. Null without a hint, and without Alpine.
 */
export function questionDialogFor(scope) {
    const selector = `[${WK_UNSAVED_DIALOG_ATTRIBUTE}]`;
    const template = scope.querySelector(selector)
        ?? (typeof document.querySelector === 'function' ? document.querySelector(selector) : null);
    const blueprint = template?.content?.firstElementChild;

    if (! blueprint) {
        return null;
    }

    let root = mountedDialogs.get(template);

    if (! root || ! root.isConnected) {
        const alpine = window.Alpine;

        if (! alpine || typeof alpine.initTree !== 'function') {
            return null;
        }

        // Asked during a visit, which only a page with a body starts; without one there is no
        // place for the dialog, and the browser asks instead.
        if (! document.body) {
            return null;
        }

        root = typeof document.importNode === 'function' ? document.importNode(blueprint, true) : blueprint.cloneNode(true);
        document.body.appendChild(root);
        // At once rather than a microtask later, when Alpine's observer would: the dialog is
        // opened in the same turn. Alpine marks what it started, so the observer skips it.
        alpine.initTree(root);
        mountedDialogs.set(template, root);
    }

    return root._x_dataStack?.[0] ?? null;
}

/**
 * Whether the link a reader pressed goes to `url` with `wire:navigate.preserve-scroll`, so the
 * visit a question held back goes on the way that link would have gone.
 */
export function linkPreservesScroll(link, url) {
    if (! link || ! url || typeof link.getAttributeNames !== 'function') {
        return false;
    }

    const preserves = link.getAttributeNames().some((name) => /^(?:wire:navigate|x-navigate)(?:\.[\w-]+)*\.preserve-scroll(?:\.|$)/.test(name));

    if (! preserves) {
        return false;
    }

    try {
        const base = typeof document.baseURI === 'string' ? document.baseURI : undefined;

        return new URL(link.getAttribute('href'), base).href === new URL(String(url), base).href;
    } catch {
        return false;
    }
}

/**
 * The address a link leads to, resolved against the page as the browser resolves it. Read from
 * the attribute: on a link inside an SVG, `href` is an SVGAnimatedString rather than the address.
 *
 * @param {Element} link
 * @returns {string}
 */
export function addressOf(link) {
    const base = typeof document.baseURI === 'string' ? document.baseURI : window.location.href;

    return new URL(String(link.getAttribute('href')), base).href;
}

/**
 * Whether an ordinary link leaves for another page of the same site: no `wire:navigate`, which
 * asks on its own way, no `download`, no other window, the same origin, and another path or query
 * than the page's, since a link to a fragment of this page leaves nothing behind.
 *
 * @param {Element|null} link
 */
export function leavesForAnotherPage(link) {
    if (! link || typeof link.getAttribute !== 'function' || link.hasAttribute('download')) {
        return false;
    }

    // A link without a `target` of its own opens where the page's first `<base target>` says, as the
    // browser reads it: under `<base target="_blank">` it leaves this page standing. An empty
    // `target=""` of its own is this window.
    const own = link.getAttribute('target');
    const base = own === null && typeof document.querySelector === 'function' ? document.querySelector('base[target]') : null;
    const target = String(own ?? base?.getAttribute('target') ?? '').trim().toLowerCase();
    // `_top` and `_parent` name this window on a page no frame holds, as `_self` does.
    const thisWindow = target === '' || target === '_self'
        || (target === '_top' && window.top === window)
        || (target === '_parent' && window.parent === window);

    if (! thisWindow) {
        return false;
    }

    if (typeof link.getAttributeNames === 'function' && link.getAttributeNames().some((name) => /^(?:wire:navigate|x-navigate)(?:\.|$)/.test(name))) {
        return false;
    }

    try {
        const base = typeof document.baseURI === 'string' ? document.baseURI : window.location.href;
        const url = new URL(String(link.getAttribute('href')), base);

        return url.origin === window.location.origin && (url.pathname !== window.location.pathname || url.search !== window.location.search);
    } catch {
        return false;
    }
}

/**
 * Tell the page whether it holds an unsaved field, once each time that changes: every registered
 * scope counts, since a closed tab loses them all.
 */
function announcePageState() {
    const page = shared(PAGE_KEY, () => ({ holds: false }));
    const holds = Array.from(registry()).some((entry) => entry.unsaved === true && entry.el.isConnected);

    if (holds === page.holds) {
        return;
    }

    page.holds = holds;

    if (typeof window.dispatchEvent === 'function' && typeof CustomEvent === 'function') {
        window.dispatchEvent(new CustomEvent('wirekit:unsaved', { detail: { unsaved: holds } }));
    }
}

/** The commit hook and the page listeners, once per page. */
function install() {
    if (typeof window === 'undefined' || typeof document === 'undefined' || window[INSTALLED_FLAG]) {
        return;
    }

    window[INSTALLED_FLAG] = true;

    // Set by the page's own release, until the next page.
    let released = false;

    // The one unload the reader has answered in the kit's dialog already: Leave on its way to an
    // ordinary link, or to a visit that goes as one. The next `beforeunload` takes it. A browser
    // fires that event when the visit starts, before its request goes out, so it is taken even
    // where the answer is a download or no content and the page stays; the question then stands
    // again for whatever comes after.
    let answeredUnload = false;

    // A reload, a closed tab, an address typed in, a plain link: the browser asks in its own
    // words, and only after the reader has interacted with the page.
    window.addEventListener('beforeunload', (event) => {
        if (answeredUnload) {
            answeredUnload = false;

            return;
        }

        if (! released && scopeHoldingUnsaved(registry())) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    // A page that asks in its own way, a bar of tabs that asks before it closes one, says so, and
    // nothing here asks again until the next page.
    window.addEventListener('wirekit:unsaved-release', () => {
        released = true;
    });

    document.addEventListener('livewire:navigated', () => {
        released = false;
    });

    // A new width of the window gives a label's line another room, so every note is placed again,
    // once a frame however many resize events the frame brings.
    let placing = false;
    const placeAgain = () => {
        if (placing) {
            return;
        }

        placing = true;
        const later = typeof window.requestAnimationFrame === 'function' ? (fn) => window.requestAnimationFrame(fn) : (fn) => setTimeout(fn, 16);

        later(() => {
            placing = false;

            for (const entry of registry()) {
                for (const label of shared(NOTED_KEY, () => new WeakMap()).get(entry.el) ?? []) {
                    placeNote(label, label.getAttribute(WK_UNSAVED_NOTE_ATTRIBUTE) ?? '');
                }
            }
        });
    };

    window.addEventListener('resize', placeAgain, { passive: true });

    // A `wire:navigate` visit never unloads the page, so the browser does not ask. A step back or
    // forward through the history is let through: it cannot be held without breaking the history.
    // Any other visit is held while the dialog asks, and goes on when the reader answers Leave.
    let heldVisit = null;
    // The visit the reader agreed to, let through once on its way.
    let leaving = false;
    // The link the reader pressed last, for the way a held visit goes on.
    let pressedLink = null;
    const remember = (event) => {
        pressedLink = event.target && typeof event.target.closest === 'function' ? event.target.closest('[href]') : null;
    };

    document.addEventListener('pointerdown', remember, true);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            remember(event);
        }
    }, true);

    /**
     * Ask in the dialog, holding the visit: its question, the list of what is unsaved, and, once
     * the dialog closes without the visit going on, the focus back on the element pressed for it,
     * two frames later, since WebKit hands it to the page otherwise.
     */
    const ask = (scope, dialog, visit) => {
        heldVisit = visit;

        const panel = dialog.$refs?.panel;
        const text = panel?.querySelector?.(`[${WK_UNSAVED_DIALOG_TEXT_ATTRIBUTE}]`);

        if (text) {
            text.textContent = questionFor(scope);
        }

        // What is unsaved and where. An entry gives up the visit, as Stay does, and goes to its
        // field: the dialog lets go of the focus without handing it back, since the field takes it.
        if (panel && typeof panel.querySelector === 'function') {
            listUnsaved(panel, unsavedFieldsOf(registry()), (field) => {
                const held = heldVisit;
                heldVisit = null;

                if (held) {
                    if (typeof held.dialog._closeLeavingFocus === 'function') {
                        held.dialog._closeLeavingFocus();
                    } else {
                        held.dialog.close();
                    }
                }

                goToField(field);
            });
        }

        dialog.show();

        const alpine = window.Alpine;

        if (! alpine || typeof alpine.effect !== 'function') {
            return;
        }

        const runner = alpine.effect(() => {
            if (dialog.isOpen) {
                return;
            }

            setTimeout(() => {
                if (typeof alpine.release === 'function') {
                    alpine.release(runner);
                }
            });

            // Left, or gone to a field: nothing to hand back.
            if (heldVisit !== visit) {
                return;
            }

            heldVisit = null;

            const from = visit.from;

            if (from && from.isConnected && typeof from.focus === 'function') {
                const later = typeof window.requestAnimationFrame === 'function'
                    ? (fn) => window.requestAnimationFrame(() => window.requestAnimationFrame(fn))
                    : (fn) => setTimeout(fn, 32);

                later(() => from.focus({ preventScroll: true }));
            }
        });
    };

    document.addEventListener('livewire:navigate', (event) => {
        if (event.detail && event.detail.history) {
            return;
        }

        if (leaving) {
            leaving = false;

            return;
        }

        const scope = released ? null : scopeHoldingUnsaved(registry());

        if (! scope) {
            return;
        }

        const dialog = questionDialogFor(scope);

        if (! dialog) {
            if (! window.confirm(questionFor(scope))) {
                event.preventDefault();
            }

            return;
        }

        event.preventDefault();

        // Asked once: the page behind an open dialog is inert, and a second visit while it is
        // open changes nothing.
        if (dialog.isOpen) {
            return;
        }

        const url = event.detail ? event.detail.url : null;

        ask(scope, dialog, { dialog, url, preserveScroll: linkPreservesScroll(pressedLink, url), from: pressedLink });
    });

    // An ordinary link to another page of the same site, pressed with the main button and no key
    // held: asked in the same dialog, in the reader's language, where the browser would ask in its
    // own words and name nothing. Heard after the link's own listeners, so a link the page or
    // Livewire handles itself is left to them. Without a hint the browser asks, in its own words.
    document.addEventListener('click', (event) => {
        if (released || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        const link = event.target && typeof event.target.closest === 'function' ? event.target.closest('a[href]') : null;

        if (! leavesForAnotherPage(link)) {
            return;
        }

        const scope = scopeHoldingUnsaved(registry());
        const dialog = scope ? questionDialogFor(scope) : null;

        if (! dialog) {
            return;
        }

        event.preventDefault();

        if (! dialog.isOpen) {
            ask(scope, dialog, { dialog, url: addressOf(link), plain: true, from: link });
        }
    });

    // Leave (WK_UNSAVED_LEAVE_EVENT): the dialog closes, and the visit goes on past the question.
    document.addEventListener('wirekit:unsaved-leave', () => {
        const visit = heldVisit;
        heldVisit = null;

        if (! visit || ! visit.url) {
            return;
        }

        visit.dialog.close();

        // An ordinary link leaves the page past the browser's own question for this one visit,
        // which has nothing left to ask once the reader answered the kit's.
        if (visit.plain) {
            answeredUnload = true;
            window.location.assign(String(visit.url));

            return;
        }

        const navigate = window.Alpine && window.Alpine.navigate;

        if (typeof navigate !== 'function') {
            answeredUnload = true;
            window.location.assign(String(visit.url));

            return;
        }

        // The visit fires `livewire:navigate` again inside this call, where the flag lets it
        // through; it is taken back however the call ends.
        leaving = true;

        try {
            navigate(String(visit.url), { preserveScroll: visit.preserveScroll });
        } finally {
            leaving = false;
        }
    });

    // A `wire:submit` sends its form's fields with the method it calls: the form is remembered, in
    // the capture phase before Livewire's own listener, so the request it starts saves the fields
    // inside it.
    document.addEventListener('submit', (event) => {
        const form = event.target;
        const root = form ? closestThroughTeleports(form, '[wire\\:id]') : null;

        if (root) {
            shared(SUBMITS_KEY, () => new Map()).set(root.getAttribute('wire:id'), { form, at: Date.now() });
        }
    }, true);

    // A click inside a component, remembered the same way, so a `wirekit:saved` with `trigger` in
    // the response to the request it starts counts the part of the page around the clicked element.
    document.addEventListener('click', (event) => {
        const clicked = event.target;
        const root = clicked ? closestThroughTeleports(clicked, '[wire\\:id]') : null;

        if (root) {
            shared(CLICKS_KEY, () => new Map()).set(root.getAttribute('wire:id'), { el: clicked, at: Date.now() });
        }
    }, true);

    // A save the component dispatches is weighed with its response already. This is for a save
    // the page makes known itself, and for the dispatch arriving after that.
    window.addEventListener('wirekit:saved', (event) => {
        markSaved(event.detail, event.target);
    });

    const hook = (livewire) => livewire.hook('commit', ({ component, commit, succeed }) => {
        if (component) {
            // The previous response first, so its request is not mixed with this one; then what
            // each tracked field holds as this request leaves, and the form a submit sent it from.
            settle(component);

            const saved = shared(SAVED_KEY, () => new WeakMap()).get(component);
            const sent = new Map();

            for (const path of saved ? saved.keys() : []) {
                sent.set(path, JSON.stringify(valueAt(component.reactive, path)));
            }

            const submits = shared(SUBMITS_KEY, () => new Map());
            const submit = submits.get(component.id);
            const clicks = shared(CLICKS_KEY, () => new Map());
            const click = clicks.get(component.id);
            const calls = commit && Array.isArray(commit.calls) ? commit.calls : [];
            // The element that sent the request: the one clicked inside the component a moment
            // before, else the one there that has the focus as the request leaves. Enter on a field
            // bound with `wire:keydown.enter`, or in a form without a submit button, sends a
            // request no click started, and a save with `trigger` would otherwise count nothing.
            const sender = calls.length === 0 ? null : (click && Date.now() - click.at < SUBMIT_WINDOW_MS ? click.el : focusedIn(component));

            submits.delete(component.id);
            clicks.delete(component.id);
            shared(REQUESTS_KEY, () => new WeakMap()).set(component, {
                sent,
                form: submit && calls.length > 0 && Date.now() - submit.at < SUBMIT_WINDOW_MS ? submit.form : null,
                sender,
            });
            // The component's own dispatch reaches the page from its root after the response, so
            // the element that sent the request is kept for it until the next request.
            shared(TRIGGERS_KEY, () => new WeakMap()).set(component, sender);
        }

        // After the response has rendered, on a timer as Livewire's own `wire:dirty` refreshes:
        // the effect reads the fields again and weighs the response.
        succeed(() => setTimeout(() => registry().forEach((entry) => entry.rerun())));
    });

    if (window.Livewire && window.Livewire.hook) {
        hook(window.Livewire);
    } else {
        document.addEventListener('livewire:init', () => {
            if (window.Livewire && window.Livewire.hook) {
                hook(window.Livewire);
            }
        }, { once: true });
    }
}

/**
 * Register the `x-wk-unsaved` directive.
 *
 * @param {object} Alpine
 */
export function registerUnsavedDirective(Alpine) {
    // On a clone for a Livewire morph, only the scope attribute. The morph compares the clone with
    // the element and would take the attribute off the element without it. The rest registers the
    // scope, and a clone is thrown away without a cleanup, so its entry would stay in the registry
    // and be compared again after every request.
    const skipDuringClone = typeof Alpine.skipDuringClone === 'function' ? Alpine.skipDuringClone : (callback) => callback;

    Alpine.directive('wk-unsaved', skipDuringClone((el, { modifiers, expression }, { effect, cleanup }) => {
        install();

        el.setAttribute(WK_UNSAVED_SCOPE_ATTRIBUTE, '');

        // A counter the effect reads, so a commit can make it compare again: the server's side of
        // the state is not reactive, and a field the response added is not tracked until then.
        const state = Alpine.reactive({ runs: 0 });
        const entry = {
            el,
            confirm: modifiers.includes('confirm'),
            // Only a save counts: a `wire:submit` of the form around a field, or `wirekit:saved`.
            untilSaved: modifiers.includes('until-saved'),
            // The name a `wirekit:saved` gives as `scope`, read as written rather than evaluated.
            name: typeof expression === 'string' && expression.trim() !== '' ? expression.trim() : null,
            rerun: () => {
                state.runs++;
            },
        };

        registry().add(entry);
        shared(ENTRIES_KEY, () => new WeakMap()).set(el, entry);

        // After Livewire has set up the component this scope sits in.
        Alpine.nextTick(() => {
            effect(() => {
                void state.runs;
                entry.unsaved = refreshScope(el);
                announcePageState();
            });
        });

        cleanup(() => {
            registry().delete(entry);
            announcePageState();
            shared(ENTRIES_KEY, () => new WeakMap()).delete(el);
            // The words go with the scope that wrote them, from the labels and the descriptions.
            writeNotes(el, new Set());
            writeDescriptions(el, new Set());
        });
    }, (el) => {
        el.setAttribute(WK_UNSAVED_SCOPE_ATTRIBUTE, '');
    }));
}
