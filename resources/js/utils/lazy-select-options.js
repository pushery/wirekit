/**
 * The list of a lazy `select`, built the first time the field is used.
 *
 * A lazy field carries its placeholder and the option it shows, each marked
 * `data-wk-lazy-option`, and its whole list waits in the `<template data-wk-select-options>` right
 * after it, which the browser neither styles nor lays out and Alpine does not walk
 * (resources/views/components/select.blade.php, Support\SelectOptions::chosen()). One listener for
 * the page builds a field's list on its first focus, pointer press or mouse over it: the marked
 * options give way to a copy of the list, the field keeps the value it had, and a built field is
 * left alone after that. Focus comes before the first key and a press before the browser opens its
 * list, so the whole list is there for both. The caller's own options in the slot are not marked
 * and stay where they are.
 *
 * Livewire renders a lazy field as the server knows it, with its chosen option alone. Before the
 * morph compares a built field, the hook gives the server's markup the whole new list, so the morph
 * updates the built options rather than removing them, and a list the server changed arrives. A
 * template the morph compares takes the server's new content for a build that comes later, since a
 * morph never walks into a template's content.
 */

/** The flag on `window` that keeps a second bundle on the page from listening twice. */
const INSTALLED_FLAG = '__wirekitLazySelectOptions';

/** The property a built field carries. */
const BUILT = '_wkOptionsBuilt';

/**
 * The template that holds the list of a lazy field: the element right after it, or for a field
 * that shares a list (`data-wk-options-from`) the template of that key, in the Livewire component
 * around the field first, since a render writes the list again inside its component, then in the
 * whole tree the field stands in.
 *
 * @param {Element|null} select
 * @returns {HTMLTemplateElement|null}
 */
export function lazySelectTemplate(select) {
    const next = select?.nextElementSibling;

    if (next && next.tagName === 'TEMPLATE' && next.hasAttribute('data-wk-select-options')) {
        return next;
    }

    const key = select?.getAttribute?.('data-wk-options-from');

    if (! key) {
        return null;
    }

    const selector = `template[data-wk-select-options="${key.replace(/["\\]/g, '\\$&')}"]`;
    const component = select.closest?.('[wire\\:id]');

    return component?.querySelector(selector) ?? rootOf(select)?.querySelector?.(selector) ?? null;
}

/** The node at the top of the tree an element stands in: the document, or a detached root. */
function rootOf(element) {
    let node = element;

    while (node?.parentNode) {
        node = node.parentNode;
    }

    return node;
}

/**
 * The options of a lazy field that stand in for its list.
 *
 * @param {Element} select
 * @returns {Element[]}
 */
function standIns(select) {
    return Array.from(select.children).filter((option) => option.hasAttribute('data-wk-lazy-option'));
}

/**
 * Build the list of a lazy field once, keeping the value it has.
 *
 * @param {HTMLSelectElement|null} select
 * @returns {boolean} whether the list was built now
 */
export function buildLazySelectOptions(select) {
    if (! select || select[BUILT] === true || ! select.hasAttribute?.('data-wk-lazy-options')) {
        return false;
    }

    const template = lazySelectTemplate(select);

    if (! template) {
        return false;
    }

    const value = select.value;

    for (const option of standIns(select)) {
        option.remove();
    }

    select.insertBefore(template.content.cloneNode(true), select.firstChild);
    select.value = value;
    select[BUILT] = true;

    return true;
}

/** Build the field an event reached, if it is a lazy one. */
function onUse(event) {
    const select = event.target?.closest?.('select[data-wk-lazy-options]');

    if (select) {
        buildLazySelectOptions(select);
    }
}

/** A mouse over a field builds it a moment before it is pressed; a touch builds on the press. */
function onPointerOver(event) {
    if (event.pointerType === 'mouse') {
        onUse(event);
    }
}

/**
 * Give the server's markup of a built field its whole new list, and a template its new content.
 *
 * @param {{ el: Element, toEl: Element }} update
 */
function keepThroughRender({ el, toEl }) {
    if (el?.nodeType !== 1 || toEl?.nodeType !== 1) {
        return;
    }

    if (el.tagName === 'TEMPLATE' && el.hasAttribute('data-wk-select-options') && toEl.tagName === 'TEMPLATE') {
        el.content.replaceChildren(toEl.content.cloneNode(true));

        return;
    }

    if (el.tagName !== 'SELECT' || el[BUILT] !== true || ! toEl.hasAttribute('data-wk-lazy-options')) {
        return;
    }

    const incoming = lazySelectTemplate(toEl) ?? lazySelectTemplate(el);

    if (! incoming) {
        return;
    }

    // The list marks no option chosen; the server's choice is the option it wrote into the field.
    const chosen = standIns(toEl).find((option) => option.hasAttribute('selected'))?.getAttribute('value') ?? null;

    for (const option of standIns(toEl)) {
        option.remove();
    }

    // Only the options of the list take the choice; the caller's own options keep their marks.
    const list = incoming.content.cloneNode(true);
    const listed = Array.from(list.querySelectorAll('option'));

    toEl.insertBefore(list, toEl.firstChild);

    for (const option of listed) {
        if (chosen !== null && option.getAttribute('value') === chosen) {
            option.setAttribute('selected', '');
        } else {
            option.removeAttribute('selected');
        }
    }
}

/** @param {{ hook: Function }} livewire */
function hookLivewire(livewire) {
    livewire.hook('morph.updating', keepThroughRender);
}

/**
 * One listener for the page, on the way down so a component that stops an event cannot keep a
 * field from its list, and the hook that keeps built lists through Livewire's renders.
 */
export function installLazySelectOptions() {
    if (typeof document === 'undefined' || typeof window === 'undefined' || window[INSTALLED_FLAG]) {
        return;
    }

    window[INSTALLED_FLAG] = true;

    document.addEventListener('focusin', onUse, true);
    document.addEventListener('pointerdown', onUse, true);
    document.addEventListener('pointerover', onPointerOver, true);

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

export default installLazySelectOptions;
