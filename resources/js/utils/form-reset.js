/**
 * Put a component back to its starting value when its form is reset.
 *
 * A reset returns every native control of a form to its default value. A hidden field has no
 * default apart from its value, so a component that keeps its value in its own state and
 * mirrors it into a hidden field stays where the reader put it: it submits the changed value,
 * and where it also shows a native field, the browser puts that field back and the two
 * disagree.
 *
 * `reset` fires before the browser resets the controls, so the restore waits until it has. A
 * component that also writes a visible native field then writes it last, and the field shows
 * what the component submits. A reset that a listener cancels resets nothing, and nothing is
 * restored.
 *
 * The form is the one the component's own field belongs to, as the browser resolves it, which
 * includes a form the field joins through its `form` attribute. It is read again at every reset,
 * because a component that renders one hidden field per chosen value has none while it is
 * empty. The form read once the component has rendered covers a component the reader emptied
 * before resetting.
 *
 * @param {Element}  root     the component root; nothing is restored once it has left the document
 * @param {Function} fieldOf  returns a form-associated field of the component, or null
 * @param {Function} restore  puts the starting value back
 * @returns {Function} stops listening; call it from destroy()
 */
export function onFormReset(root, fieldOf, restore) {
    const doc = typeof document !== 'undefined' ? document : null;

    if (! doc || typeof doc.addEventListener !== 'function' || typeof fieldOf !== 'function' || typeof restore !== 'function') {
        return () => {};
    }

    let known = null;
    let timer = null;
    let stopped = false;

    // The field present now, or null when there is none or it cannot be read; its form is
    // remembered, so a reset after the last field is gone still finds it.
    const current = () => {
        let field;

        try {
            field = fieldOf();
        } catch {
            return null;
        }

        if (! field || typeof field !== 'object' || ! ('form' in field)) {
            return null;
        }

        known = field.form || null;

        return field;
    };

    current();

    // A field rendered from a template (one per chosen value) appears once the directives inside
    // the component have run, which is after its own init and before this task ends.
    if (typeof queueMicrotask === 'function') {
        queueMicrotask(() => {
            if (! stopped) {
                current();
            }
        });
    }

    const onReset = (event) => {
        const field = current();
        const form = field ? field.form : known;

        if (! form || event.target !== form) {
            return;
        }

        clearTimeout(timer);
        timer = setTimeout(() => {
            timer = null;

            if (stopped || event.defaultPrevented || (root && root.isConnected === false)) {
                return;
            }

            restore();
        }, 0);
    };

    doc.addEventListener('reset', onReset);

    return () => {
        stopped = true;
        clearTimeout(timer);
        timer = null;

        if (typeof doc.removeEventListener === 'function') {
            doc.removeEventListener('reset', onReset);
        }
    };
}
