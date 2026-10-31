import { accessibleText } from './accessible-text.js';

/**
 * A label of the page's own names a picker the server could only name from its `name`.
 *
 * A picker that shows its value is named through `aria-labelledby`: first a name, then the value,
 * so a reader hears both. The name is the picker's label, a caller's `aria-label` or
 * `aria-labelledby`, or the label of the field around it. Without any of them the server makes one
 * from the field's `name` and writes it on a hidden element marked `data-wk-fallback-name`, whose
 * value is the id a label of the page's own would point at: the trigger's, or the first segment's
 * of a typed field. `aria-labelledby` comes before any `<label for>` when a browser computes a
 * name, so such a label opened the picker on a press and still named nothing.
 *
 * When the page has a `<label for>` with that id outside the picker, the label takes the hidden
 * name's place in every `aria-labelledby` of the picker that lists it, its teleported dialog
 * included, and gets an id of its own where it has none. A button that clears the value and
 * carries its wording in `data-wk-fallback-clear` (":label" standing for the name) is named after
 * the label's visible text. Without such a label nothing changes.
 *
 * A Livewire morph writes back what the server rendered, the references and the clear button's
 * name, and can take the id off a label it renders. The observers put them in again in the same
 * task, as utils/trigger-aria.js does for a trigger of the caller's.
 *
 * @param {Element|null} root  the picker's root
 * @param {() => (Element|null|undefined)} [panel]  the picker's teleported dialog, read when needed
 * @returns {Function} stops the observers; the caller keeps it under a `_` name and calls it from
 *          `destroy()`
 */
export function adoptPageLabel(root, panel = () => null) {
    const fallback = root?.querySelector?.('[data-wk-fallback-name]') ?? null;
    const controlId = fallback?.getAttribute('data-wk-fallback-name') ?? '';
    const fallbackId = fallback?.id ?? '';

    if (! fallback || controlId === '' || fallbackId === '' || typeof document === 'undefined') {
        return () => {};
    }

    // A label inside the picker is its own, and the page's label is read where it stands.
    const findLabel = () => [...document.querySelectorAll('label[for]')]
        .find((candidate) => candidate.getAttribute('for') === controlId && ! root.contains(candidate)) ?? null;
    let label = findLabel();

    if (! label) {
        return () => {};
    }

    const labelId = label.id !== '' ? label.id : uniqueId(controlId + '-page-label');
    const observer = typeof MutationObserver === 'undefined' ? null : new MutationObserver(() => {
        observePanel();
        apply();
    });
    const watched = new Set();
    const observe = (target, options) => {
        if (observer && target && ! watched.has(target)) {
            watched.add(target);
            observer.observe(target, options);
        }
    };
    const observePanel = () => observe(panel(), { attributes: true, subtree: true, attributeFilter: ['aria-labelledby', 'aria-label'] });

    // A label the server renders inside a Livewire component keeps the id given here only until
    // the next update: the morph keys an element by its id, the label the server renders has
    // none, and the morph puts that label in the place of this one. So the label is looked up on
    // every pass, the one on the page takes the same id, and its parent is watched for the swap,
    // which a picker the update leaves alone would not report.
    const watchLabel = () => {
        observe(label, { attributes: true, attributeFilter: ['id'] });
        observe(label.parentNode, { childList: true });
    };

    const apply = () => {
        const current = findLabel();

        if (current && current !== label) {
            label = current;
            watchLabel();
        }

        if (label.id !== labelId) {
            label.id = labelId;
        }

        for (const scope of [root, panel()]) {
            if (! scope?.querySelectorAll) {
                continue;
            }

            for (const element of [scope, ...scope.querySelectorAll('[aria-labelledby], [data-wk-fallback-clear]')]) {
                swapReference(element, fallbackId, labelId);
                nameClearButton(element, label);
            }
        }
    };

    apply();

    if (! observer) {
        return () => {};
    }

    observe(root, { attributes: true, subtree: true, attributeFilter: ['aria-labelledby', 'aria-label'] });
    watchLabel();
    observePanel();

    return () => observer.disconnect();
}

/** Put the label in the place of the hidden name, wherever the element names it. */
function swapReference(element, fallbackId, labelId) {
    const references = (element.getAttribute?.('aria-labelledby') ?? '').split(/\s+/).filter(Boolean);

    if (! references.includes(fallbackId)) {
        return;
    }

    element.setAttribute('aria-labelledby', references.map((id) => (id === fallbackId ? labelId : id)).join(' '));
}

/** A clear button says which field it clears, in its own wording. */
function nameClearButton(element, label) {
    const wording = element.getAttribute?.('data-wk-fallback-clear') ?? '';

    if (wording === '') {
        return;
    }

    const name = accessibleText(label);

    if (name === '') {
        return;
    }

    const wanted = wording.replace(':label', name);

    if (element.getAttribute('aria-label') !== wanted) {
        element.setAttribute('aria-label', wanted);
    }
}

/** An id no element of the page carries yet. */
function uniqueId(base) {
    let id = base;

    for (let n = 2; document.getElementById(id); n++) {
        id = base + '-' + n;
    }

    return id;
}

export default adoptPageLabel;
