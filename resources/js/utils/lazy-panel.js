/**
 * The panel of an overlay, built the first time it is needed (`lazy`).
 *
 * An overlay draws its panel in a `<template x-teleport>`, and Alpine clones that template's
 * content into its target the moment the directive starts: every panel on a page starts with the
 * page, whether its overlay ever opens or not. An overlay drawn with `lazy` wraps that template in
 * a `<template data-wk-lazy-panel>`. The browser parses a template's content without making it
 * part of the page, so the teleport inside does not start, and `#wk-overlay-root` stays empty.
 *
 * Built means: a copy of the outer template's content stands directly after it, Alpine has started
 * that copy, so the teleport has placed the panel in its target, and the outer template carries
 * `_wkBuilt`. A panel builds once and stays when its overlay closes. The outer template stays in
 * place, because a Livewire render compares the overlay with the server's markup, which holds it;
 * `keepLazyEntriesThroughRenders()` in `lazy-entries.js` carries a built panel through a render as
 * it carries built entries.
 *
 * The flag is a property of the element rather than an attribute: a render makes every attribute
 * match the server's markup, so an attribute would be gone after the first render and the next
 * opening would build the panel a second time.
 */

/**
 * The element whose children hold an overlay's lazy panel template: the overlay's root, or, where
 * the panels stand inside a nested scope of the overlay, the child of the root that names itself
 * with `data-wk-lazy-panel-host` (a combobox's optimistic layer). Null for an overlay that builds
 * its panel with the page.
 *
 * @param {Element|null|undefined} root
 * @returns {Element|null}
 */
export function lazyPanelHost(root) {
    if (! root || root.nodeType !== 1 || typeof root.hasAttribute !== 'function') {
        return null;
    }

    if (root.hasAttribute('data-wk-lazy-panel-host')) {
        return root;
    }

    return Array.from(root.children ?? []).find((child) => typeof child.hasAttribute === 'function' && child.hasAttribute('data-wk-lazy-panel-host')) ?? null;
}

/**
 * The lazy panel template among the direct children of an overlay's root, or null for an overlay
 * that builds its panel with the page.
 *
 * @param {Element|null|undefined} root
 * @returns {HTMLTemplateElement|null}
 */
export function lazyPanelTemplate(root) {
    // Anything that is not an element with children answers null: a factory may run before its
    // root exists, or against a stand-in that has none.
    if (! root || root.nodeType !== 1 || ! root.children) {
        return null;
    }

    for (const child of Array.from(root.children)) {
        if (child.tagName === 'TEMPLATE' && typeof child.hasAttribute === 'function' && child.hasAttribute('data-wk-lazy-panel')) {
            return child;
        }
    }

    return null;
}

/**
 * Build the panel a template holds, once, and start it at once, so the panel stands in its target
 * when this returns and the caller can place it. Returns whether this call built it.
 *
 * Alpine is asked to start the copy here rather than left to find it: its mutation observer reports
 * the copy after the current microtasks, too late for an opening in the same tick. The insertion
 * goes through `mutateDom()`, which keeps that observer from starting the copy a second time.
 *
 * @param {HTMLTemplateElement|null} template
 * @param {object} [alpine] Alpine; `window.Alpine` when left out
 * @returns {boolean}
 */
export function buildLazyPanel(template, alpine = typeof window === 'undefined' ? undefined : window.Alpine) {
    if (! template || template._wkBuilt === true) {
        return false;
    }

    template._wkBuilt = true;

    const copy = template.content.cloneNode(true);
    const roots = Array.from(copy.children);
    const insert = () => template.after(copy);

    if (alpine && typeof alpine.mutateDom === 'function') {
        alpine.mutateDom(insert);
    } else {
        insert();
    }

    if (alpine && typeof alpine.initTree === 'function') {
        roots.forEach((root) => alpine.initTree(root));
    }

    forgetKeptRefs(template);

    return true;
}

/**
 * Let the scopes around a built panel read their refs afresh.
 *
 * Alpine builds an element's `$refs` once, on its first read, from the ref objects that element
 * and its ancestors hold at that moment, and keeps it on the element (`_x_refs_proxy`, Alpine
 * 3.17.5). A root whose only refs stand in its lazy panel holds no ref object before the build,
 * as a dropdown does, whose trigger is a scope of its own. The ref object the panel's refs create
 * comes after every `$refs` kept until then and never reaches them, so `$refs.panel` stays
 * undefined there. Dropping the kept `$refs` of the elements above the template and of those beside
 * and below it makes the next read collect the ref objects again, the panel's included. A scope
 * that held refs before the build reads nothing new.
 *
 * @param {Element} template
 */
function forgetKeptRefs(template) {
    const forget = (el) => {
        if (el && el._x_refs_proxy !== undefined) {
            delete el._x_refs_proxy;
        }
    };
    const below = (el) => {
        Array.from(el.children ?? []).forEach((child) => {
            forget(child);
            below(child);
        });
    };

    for (let el = template.parentElement; el; el = el.parentElement) {
        forget(el);
    }

    if (template.parentElement) {
        below(template.parentElement);
    }
}
