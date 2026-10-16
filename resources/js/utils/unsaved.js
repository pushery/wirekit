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
 *     it, unless the field is invalid, a radio button, or a checkbox of a list;
 *   - the scope carries `data-wk-has-unsaved`;
 *   - every `<x-wirekit::unsaved-hint>` in the scope shows its sentence, which is a polite live
 *     region, so the change from saved to unsaved is spoken once.
 *
 * `x-wk-unsaved.confirm` also asks before the reader leaves the page with unsaved fields: the
 * browser's own question on a reload, a closed tab or an address typed in, and before a
 * `wire:navigate` visit the hint's question, in the alert dialog an unsaved hint ships with in a
 * template. The visit is held while the dialog is open and goes on, past the question, when the
 * reader answers Leave. A page without a hint asks with `confirm()`. A step through the history
 * is not stopped.
 *
 * What counts as saved: every field keeps the value it counts as saved with, at first what the
 * server rendered. A request of the component that comes back without a validation error saves
 * every deferred field, since any request sends them, not only the save; one that comes back with
 * a validation error saves nothing. A value the server writes into a field itself, loading,
 * resetting or rewriting it, is saved at once. `x-wk-unsaved.until-saved` counts only a save: a
 * `wire:submit` of the form around the field that comes back without a validation error, or a
 * `wirekit:saved` the component dispatches, with `scope` naming the scope
 * (`x-wk-unsaved.until-saved="name"`), `paths` naming bound properties, or neither for every field
 * of the component. A save the server turns away without a validation error, over a conflict or an
 * edit someone else holds, says so with `wirekit:not-saved` in the same response, which names its
 * fields the same way: they stay unsaved although the request came back clean. A value the server
 * wrote, and a field a `wirekit:saved` of that response names, still count as saved. A field with
 * `.live`, `.blur`, `.change` or `.lazy` sends its value itself and is not tracked; nor are file
 * fields, nor anything inside `data-wk-unsaved-off`.
 *
 * Why the component's own state: `wire:model` sits on a native field as often as on a component's
 * root, whose value lives in Alpine and never shows in the DOM. Livewire keeps both sides for every
 * component, the values the server sent and the values the page holds, and `wire:dirty` compares
 * exactly those two, and the value a field counts as saved with moves only from there. A response
 * is weighed the first time anything asks after Livewire merged it, its validation errors and its
 * dispatches included, so the question asked before a redirect that a save starts already counts
 * the save, although the redirect runs before the response is painted.
 *
 * Lifecycle: one Alpine effect per scope, released with the directive; one commit hook and four
 * page listeners per page (leaving, a `wire:navigate` visit, a submit and `wirekit:saved`),
 * installed once and reading the scopes that are registered.
 */

export const WK_UNSAVED_ATTRIBUTE = 'data-wk-unsaved';
export const WK_HAS_UNSAVED_ATTRIBUTE = 'data-wk-has-unsaved';
export const WK_UNSAVED_SCOPE_ATTRIBUTE = 'data-wk-unsaved-scope';
export const WK_UNSAVED_HINT_ATTRIBUTE = 'data-wk-unsaved-hint';
export const WK_UNSAVED_SHOWN_ATTRIBUTE = 'data-wk-unsaved-shown';
export const WK_UNSAVED_OFF_ATTRIBUTE = 'data-wk-unsaved-off';
export const WK_UNSAVED_QUESTION_ATTRIBUTE = 'data-wk-unsaved-confirm';
// The template an unsaved hint ships its dialog in, and the element of that dialog's title that
// takes the question.
export const WK_UNSAVED_DIALOG_ATTRIBUTE = 'data-wk-unsaved-question';
export const WK_UNSAVED_DIALOG_TEXT_ATTRIBUTE = 'data-wk-unsaved-question-text';
// What the dialog's Leave dispatches, from its button, bubbling. The listener below names it
// literally, so the event names it listens for can be read off the source.
export const WK_UNSAVED_LEAVE_EVENT = 'wirekit:unsaved-leave';

// The words beside the label of an unsaved field: on the label, where the stylesheet writes them,
// and on the scope or a hint, which name them in the reader's language. Two names, so that the
// hint, which names them, never shows them itself.
export const WK_UNSAVED_NOTE_ATTRIBUTE = 'data-wk-unsaved-note';
export const WK_UNSAVED_WORDS_ATTRIBUTE = 'data-wk-unsaved-words';

/** The modifiers that send a value on their own, so the field is never unsaved for long. */
const SELF_SENDING_MODIFIERS = ['live', 'blur', 'change', 'lazy'];

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
const ENTRIES_KEY = '__wirekitUnsavedEntries';
const FIELDS_KEY = '__wirekitUnsavedFields';
const NOTED_KEY = '__wirekitUnsavedNoted';

/** How long a submit waits for the request it starts before it is taken for something else. */
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

    if (typeof el.closest === 'function' && el.closest(`[${WK_UNSAVED_OFF_ATTRIBUTE}]`)) {
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
    const root = typeof el.closest === 'function' ? el.closest('[wire\\:id]') : null;
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
    const scope = typeof el.closest === 'function' ? el.closest(`[${WK_UNSAVED_SCOPE_ATTRIBUTE}]`) : null;

    return scope ? shared(ENTRIES_KEY, () => new WeakMap()).get(scope) ?? null : null;
}

/** Whether a saved signal names this field: by its scope, by its path, or neither, which names all. */
function names(signal, entry, path) {
    const scope = typeof signal.scope === 'string' ? signal.scope : null;
    const paths = Array.isArray(signal.paths) ? signal.paths : null;

    if (scope !== null && (entry === null || scope !== entry.name)) {
        return false;
    }

    return paths === null || paths.includes(path);
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
    const request = requests.get(component) ?? { sent: new Map(), form: null };
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

    for (const path of Array.from(saved.keys())) {
        // The field last compared under this path, and the scope it answers to; none outside a
        // registered scope, which counts like a scope without `.until-saved`.
        const el = fields.get(path) ?? null;
        const entry = el ? entryOf(el) : null;
        const server = JSON.stringify(valueAt(component.canonical, path));
        const sent = request.sent.get(path);
        const wrote = sent !== undefined && ! sameJson(sent, server);
        const submitted = request.form !== null && el !== null && typeof request.form.contains === 'function' && request.form.contains(el);
        const signaled = signals.some((signal) => names(signal, entry, path));
        const refused = refusals.some((signal) => names(signal, entry, path));

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
 * the whole page.
 *
 * @param {unknown} detail
 * @param {unknown} target
 */
export function markSaved(detail, target = null) {
    const signal = detail && typeof detail === 'object' && ! Array.isArray(detail) ? detail : {};
    const element = target && typeof target.closest === 'function' ? target : null;
    const within = element ? element.closest('[wire\\:id]') ?? element : null;

    for (const entry of registry()) {
        for (const field of trackedFields(entry.el)) {
            if (entryOf(field.el) !== entry || ! names(signal, entry, field.path) || (within !== null && ! within.contains(field.el))) {
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

    for (const el of Array.from(scope.getElementsByTagName('*'))) {
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
    return Array.from(scope.querySelectorAll(`[${WK_UNSAVED_HINT_ATTRIBUTE}]`))
        .filter((hint) => hint.parentElement?.closest(`[${WK_UNSAVED_SCOPE_ATTRIBUTE}]`) === scope);
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
 * The labels that say a field is unsaved: the field's own, or those of the first field inside a
 * component whose root carries `wire:model`. None for a radio button or a checkbox bound to a
 * list, since every option of the group would say it, and none for an invalid field, whose error
 * comes first.
 *
 * @param {{el: Element, path: string, component: object}} field
 * @returns {Element[]}
 */
function labelsToNote(field) {
    const control = field.el.labels
        ? field.el
        : (typeof field.el.querySelector === 'function' ? field.el.querySelector('input:not([type="hidden"]), select, textarea') : null);

    if (! control || ! control.labels || control.labels.length === 0) {
        return [];
    }

    const type = String(control.getAttribute('type') ?? '').toLowerCase();

    if (type === 'radio' || (type === 'checkbox' && Array.isArray(valueAt(field.component.reactive, field.path)))) {
        return [];
    }

    if (control.getAttribute('aria-invalid') === 'true' || field.el.getAttribute('aria-invalid') === 'true' || userInvalid(control)) {
        return [];
    }

    return Array.from(control.labels);
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
        }
    }

    for (const label of noted) {
        if (label.getAttribute(WK_UNSAVED_NOTE_ATTRIBUTE) !== words) {
            label.setAttribute(WK_UNSAVED_NOTE_ATTRIBUTE, words);
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

    // Every field is compared and marked, no early exit: an effect that calls this reads each
    // field's value, and that read is what makes it run again when the field changes.
    for (const field of trackedFields(scope)) {
        const differs = differsFromSaved(field.component, field.path, field.el);

        flag(field.el, WK_UNSAVED_ATTRIBUTE, differs);
        unsaved = unsaved || differs;

        if (differs) {
            for (const label of labelsToNote(field)) {
                labels.add(label);
            }
        }
    }

    writeNotes(scope, labels);
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

/** The commit hook and the four page listeners, once per page. */
function install() {
    if (typeof window === 'undefined' || typeof document === 'undefined' || window[INSTALLED_FLAG]) {
        return;
    }

    window[INSTALLED_FLAG] = true;

    // A reload, a closed tab, an address typed in, a plain link: the browser asks in its own
    // words, and only after the reader has interacted with the page.
    window.addEventListener('beforeunload', (event) => {
        if (scopeHoldingUnsaved(registry())) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

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

    document.addEventListener('livewire:navigate', (event) => {
        if (event.detail && event.detail.history) {
            return;
        }

        if (leaving) {
            leaving = false;

            return;
        }

        const scope = scopeHoldingUnsaved(registry());

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
        heldVisit = { dialog, url, preserveScroll: linkPreservesScroll(pressedLink, url) };

        const text = dialog.$refs?.panel?.querySelector?.(`[${WK_UNSAVED_DIALOG_TEXT_ATTRIBUTE}]`);

        if (text) {
            text.textContent = questionFor(scope);
        }

        dialog.show();
    });

    // Leave (WK_UNSAVED_LEAVE_EVENT): the dialog closes, and the visit goes on past the question.
    document.addEventListener('wirekit:unsaved-leave', () => {
        const visit = heldVisit;
        heldVisit = null;

        if (! visit || ! visit.url) {
            return;
        }

        visit.dialog.close();

        const navigate = window.Alpine && window.Alpine.navigate;

        if (typeof navigate !== 'function') {
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
        const root = form && typeof form.closest === 'function' ? form.closest('[wire\\:id]') : null;

        if (root) {
            shared(SUBMITS_KEY, () => new Map()).set(root.getAttribute('wire:id'), { form, at: Date.now() });
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
            const calls = commit && Array.isArray(commit.calls) ? commit.calls : [];

            submits.delete(component.id);
            shared(REQUESTS_KEY, () => new WeakMap()).set(component, {
                sent,
                form: submit && calls.length > 0 && Date.now() - submit.at < SUBMIT_WINDOW_MS ? submit.form : null,
            });
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
                refreshScope(el);
            });
        });

        cleanup(() => {
            registry().delete(entry);
            shared(ENTRIES_KEY, () => new WeakMap()).delete(el);
            // The words go with the scope that wrote them.
            writeNotes(el, new Set());
        });
    }, (el) => {
        el.setAttribute(WK_UNSAVED_SCOPE_ATTRIBUTE, '');
    }));
}
