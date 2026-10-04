/**
 * `x-wk-unsaved`: a form that holds what its server does not have yet says so.
 *
 * Put it on the element that holds the fields, a `<form>` or a card. Every field inside it with a
 * deferred `wire:model` is compared with the value its Livewire component last received from the
 * server, the comparison `wire:dirty` makes. While a field differs:
 *
 *   - the field carries `data-wk-unsaved`, and the stylesheet frames it in
 *     `--color-wk-border-unsaved`, unless it is invalid;
 *   - the scope carries `data-wk-has-unsaved`;
 *   - every `<x-wirekit::unsaved-hint>` in the scope shows its sentence, which is a polite live
 *     region, so the change from saved to unsaved is spoken once.
 *
 * `x-wk-unsaved.confirm` also asks before the reader leaves the page with unsaved fields: the
 * browser's own question on a reload, a closed tab or an address typed in, and `confirm()` with
 * the hint's question before a `wire:navigate` visit. A step through the history is not stopped.
 *
 * WHAT COUNTS AS SAVED is the moment the server has the value: any request of the component sends
 * every deferred change with it, not only the save. A field with `.live`, `.blur`, `.change` or
 * `.lazy` sends its value itself and is not tracked; nor are file fields, nor anything inside
 * `data-wk-unsaved-off`.
 *
 * WHY THE COMPONENT'S OWN STATE: `wire:model` sits on a native field as often as on a component's
 * root, whose value lives in Alpine and never shows in the DOM. Livewire keeps both sides for every
 * component, the values the server sent and the values the page holds, and `wire:dirty` compares
 * exactly those two. The question asked here is the same one, so the answer cannot differ from it.
 * At the moment a redirect runs after a save, the server's values have already been merged, so
 * the question asked before leaving is answered from the same state rather than from the last
 * paint.
 *
 * Lifecycle: one Alpine effect per scope, released with the directive; one commit hook and two
 * page listeners per page, installed once and reading the scopes that are registered.
 */

export const WK_UNSAVED_ATTRIBUTE = 'data-wk-unsaved';
export const WK_HAS_UNSAVED_ATTRIBUTE = 'data-wk-has-unsaved';
export const WK_UNSAVED_SCOPE_ATTRIBUTE = 'data-wk-unsaved-scope';
export const WK_UNSAVED_HINT_ATTRIBUTE = 'data-wk-unsaved-hint';
export const WK_UNSAVED_SHOWN_ATTRIBUTE = 'data-wk-unsaved-shown';
export const WK_UNSAVED_OFF_ATTRIBUTE = 'data-wk-unsaved-off';
export const WK_UNSAVED_QUESTION_ATTRIBUTE = 'data-wk-unsaved-confirm';

/** The modifiers that send a value on their own, so the field is never unsaved for long. */
const SELF_SENDING_MODIFIERS = ['live', 'blur', 'change', 'lazy'];

/** Asked when neither the scope nor a hint in it names a question in the reader's language. */
export const WK_UNSAVED_DEFAULT_QUESTION = 'You have unsaved changes. Leave this page anyway?';

/**
 * Remembered on `window`, the one scope a second copy of this module on the page shares with the
 * first, for the same reason utils/cleared-field.js gives.
 */
const INSTALLED_FLAG = '__wirekitUnsavedChanges';
const REGISTRY_KEY = '__wirekitUnsavedScopes';

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

/**
 * Write the sentence of a hint again. The text was there, hidden; writing it changes the live
 * region, which is what a screen reader announces, where a change of `display` alone is not
 * reported by every one.
 */
function announce(hint) {
    const text = hint.querySelector('[data-wk-unsaved-text]');

    if (text && text.lastChild && text.lastChild.nodeType === 3) {
        text.lastChild.nodeValue = String(text.lastChild.nodeValue);
    }
}

/**
 * Compare every field of the scope and write the result. Returns whether any field is unsaved.
 *
 * @param {Element} scope
 */
export function refreshScope(scope) {
    let unsaved = false;

    // Every field is compared and marked, no early exit: an effect that calls this reads each
    // field's value, and that read is what makes it run again when the field changes.
    for (const field of trackedFields(scope)) {
        const differs = differsFromServer(field.component, field.path);

        flag(field.el, WK_UNSAVED_ATTRIBUTE, differs);
        unsaved = unsaved || differs;
    }

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

        if (trackedFields(entry.el).some((field) => differsFromServer(field.component, field.path))) {
            return entry.el;
        }
    }

    return null;
}

/** The commit hook and the two page listeners, once per page. */
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
    document.addEventListener('livewire:navigate', (event) => {
        if (event.detail && event.detail.history) {
            return;
        }

        const scope = scopeHoldingUnsaved(registry());

        if (scope && ! window.confirm(questionFor(scope))) {
            event.preventDefault();
        }
    });

    const hook = (livewire) => livewire.hook('commit', ({ succeed }) => {
        // After the response has rendered, on a timer as Livewire's own `wire:dirty` refreshes:
        // the values the server sent are now the saved ones, and the effect reads them again.
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
    Alpine.directive('wk-unsaved', (el, { modifiers }, { effect, cleanup }) => {
        install();

        el.setAttribute(WK_UNSAVED_SCOPE_ATTRIBUTE, '');

        // A counter the effect reads, so a commit can make it compare again: the server's side of
        // the state is not reactive, and a field the response added is not tracked until then.
        const state = Alpine.reactive({ runs: 0 });
        const entry = {
            el,
            confirm: modifiers.includes('confirm'),
            rerun: () => {
                state.runs++;
            },
        };

        registry().add(entry);

        // After Livewire has set up the component this scope sits in.
        Alpine.nextTick(() => {
            effect(() => {
                void state.runs;
                refreshScope(el);
            });
        });

        cleanup(() => {
            registry().delete(entry);
        });
    });
}
