/**
 * Whether a disabled fieldset disables a component, as it disables a native control.
 *
 * `<fieldset disabled>` disables every native control it contains: a button, an input, a select
 * and a textarea leave the tab order, take no input and are not submitted. It does nothing to an
 * element that is not one of them, such as a `div[role="slider"]` with a tab stop, a
 * contenteditable surface or a listener on a frame. A component built from those stayed operable
 * in a section the page had locked, and with `wire:model` its change reached the server.
 *
 * A control does not say so itself either: `disabled` reflects the control's own attribute and
 * stays false inside a disabled fieldset. `:disabled` is the state the browser applies, the
 * fieldset included.
 */

/**
 * Whether a native control is disabled, by its own attribute or by a fieldset around it.
 *
 * @param {Element|null|undefined} control
 * @returns {boolean}
 */
export function controlIsDisabled(control) {
    if (! control) {
        return false;
    }

    if (control.disabled === true) {
        return true;
    }

    return typeof control.matches === 'function' && control.matches(':disabled');
}

/**
 * Whether a disabled fieldset around an element disables it.
 *
 * HTML's rule: a disabled fieldset disables what it contains except the content of its first
 * `legend`, where a control that unlocks the section can sit. An element in that legend is still
 * disabled by a disabled fieldset further out, so every one around it is read.
 *
 * @param {Element|null|undefined} el
 * @returns {boolean}
 */
export function disabledByFieldset(el) {
    if (! el || typeof el.closest !== 'function') {
        return false;
    }

    for (let set = el.closest('fieldset[disabled]'); set; set = set.parentElement?.closest?.('fieldset[disabled]') ?? null) {
        const legend = [...(set.children ?? [])].find((child) => child.tagName === 'LEGEND');

        if (! legend || ! legend.contains(el)) {
            return true;
        }
    }

    return false;
}

/**
 * Report whether the fieldsets around an element disable it, now and whenever that changes.
 *
 * A page locks a section for a moment as well: `wire:loading.attr="disabled"` sets the attribute
 * for the length of a request, and a Livewire render can set or remove it. The fieldsets followed
 * are the ones around the element when this runs. Without `MutationObserver` the state is
 * reported once.
 *
 * @param {Element|null|undefined} el
 * @param {(disabled: boolean) => void} onChange  called at once, then each time the state changes
 * @returns {Function} stops following; call it from destroy()
 */
export function watchFieldsetDisabled(el, onChange) {
    if (! el || typeof el.closest !== 'function' || typeof onChange !== 'function') {
        return () => {};
    }

    let disabled = disabledByFieldset(el);
    onChange(disabled);

    const sets = [];

    for (let set = el.closest('fieldset'); set; set = set.parentElement?.closest?.('fieldset') ?? null) {
        sets.push(set);
    }

    if (sets.length === 0 || typeof MutationObserver !== 'function') {
        return () => {};
    }

    const observer = new MutationObserver(() => {
        const now = disabledByFieldset(el);

        if (now !== disabled) {
            disabled = now;
            onChange(now);
        }
    });

    sets.forEach((set) => observer.observe(set, { attributes: true, attributeFilter: ['disabled'] }));

    return () => observer.disconnect();
}
