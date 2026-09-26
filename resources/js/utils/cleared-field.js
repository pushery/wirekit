/**
 * A field Livewire empties after a successful action stops reading as a mistake.
 *
 * The house fields turn red through `:user-invalid`, which fires only after the reader has
 * interacted, so an empty required field is not red on load. But the browser's memory of that
 * interaction outlives a value set by script: only a form reset clears it. The usual
 * add-something row ("watch a repository", "add a role") submits, the action stores the value
 * and sets the property back to '', and Livewire writes '' into a field the reader has already
 * touched. The field is now empty, required and "interacted with", so the browser paints it red
 * over a success message, with no error text beside it.
 *
 * A form reset would clear the memory, and it would also clear every other field of the form,
 * which Livewire still holds. So the red is gated on an attribute instead: a field the SERVER
 * emptied carries `data-wk-cleared`, and the stylesheet and the field utilities read
 * `:user-invalid:not([data-wk-cleared])`. The browser's validity is untouched; only the paint is.
 *
 * Which fields the server emptied is measured, not guessed: the fields that held text when a
 * request left, and are empty once its response has rendered. Two things keep a reader's own
 * emptying out of that set:
 *   - a field the reader typed into after the request left is skipped, so clearing it by hand
 *     while the answer is in flight stays red, as it should;
 *   - the attribute comes off on the next `input` event, and on `invalid`, which the browser
 *     fires when a submit attempt finds the field empty — so trying to submit it empty turns it
 *     red again, exactly as before.
 *
 * Installed once per page. Outside Livewire nothing hooks, and the two listeners have nothing to
 * remove, because nothing ever sets the attribute.
 */
export const WK_CLEARED_ATTRIBUTE = 'data-wk-cleared';

let installed = false;

/** When the reader last typed into a field, so their own emptying is not taken for the server's. */
const typedAt = new WeakMap();

/**
 * The text fields of a component that held something when its request left.
 *
 * @param {Element|null|undefined} root  the Livewire component's element
 * @returns {Array<HTMLInputElement|HTMLTextAreaElement>}
 */
function filledFields(root) {
    if (! root || typeof root.querySelectorAll !== 'function') {
        return [];
    }

    return Array.from(root.querySelectorAll('input.wk-field, textarea.wk-field'))
        .filter((field) => typeof field.value === 'string' && field.value !== '');
}

/**
 * The response has rendered; mark each field the server emptied.
 *
 * On a timer rather than straight away: Livewire hands the new value to Alpine, and Alpine writes
 * it into the field in its own flush, which has not run yet when the render callback fires.
 */
function markServerEmptied(fields, leftAt) {
    setTimeout(() => {
        for (const field of fields) {
            if (! field.isConnected || field.value !== '') {
                continue;
            }

            if ((typedAt.get(field) ?? -Infinity) >= leftAt) {
                continue;
            }

            field.setAttribute(WK_CLEARED_ATTRIBUTE, '');
        }
    }, 0);
}

/** @param {{hook: Function}} livewire */
function hookLivewire(livewire) {
    livewire.hook('commit', ({ component, succeed }) => {
        const fields = filledFields(component?.el);

        if (fields.length === 0) {
            return;
        }

        const leftAt = performance.now();

        succeed(() => markServerEmptied(fields, leftAt));
    });
}

/**
 * Install the memory once. Safe to call from every bundle: the second call does nothing.
 */
export function registerClearedFieldMemory() {
    if (installed || typeof document === 'undefined' || typeof window === 'undefined') {
        return;
    }

    installed = true;

    // Capture phase: `invalid` does not bubble, and a field's own handler may stop `input`.
    document.addEventListener('input', (event) => {
        const field = event.target;

        if (field && typeof field.removeAttribute === 'function') {
            typedAt.set(field, performance.now());
            field.removeAttribute(WK_CLEARED_ATTRIBUTE);
        }
    }, true);

    document.addEventListener('invalid', (event) => {
        event.target?.removeAttribute?.(WK_CLEARED_ATTRIBUTE);
    }, true);

    if (window.Livewire?.hook) {
        hookLivewire(window.Livewire);

        return;
    }

    document.addEventListener('livewire:init', () => {
        if (window.Livewire?.hook) {
            hookLivewire(window.Livewire);
        }
    }, { once: true });
}
