/**
 * The entries of a folding sidebar section, built the first time they are needed (`lazy`).
 *
 * A section the server draws closed with `lazy` holds its entries in a
 * `<template data-wk-lazy-entries>`, the first element of its panel. The browser parses a
 * template's content without making it part of the page: none of its elements is styled or laid
 * out, and no Alpine component in it starts. On a sidebar with many closed sections, whose every
 * row starts a tooltip, that is most of what the page's start spends on the sidebar.
 *
 * Built means: a copy of the template's content stands directly after the template, and the
 * template carries `_wkBuilt`. A section builds once, the first time it shows its entries, and
 * keeps them when it closes again. The template stays in the panel, because a Livewire render
 * compares the panel with the server's markup, which holds the template (see
 * keepLazyEntriesThroughRenders below).
 *
 * The flag is a property of the element rather than an attribute: a render makes every attribute
 * match the server's markup, so an attribute would be gone after the first render and the next
 * opening would build the entries a second time.
 *
 * `partials/disclosure-persist-seed.blade.php` builds the same way, before the first paint, for a
 * section the reader opened on an earlier visit. Its script is a copy, not an import, because it
 * runs while the page is still being parsed. Alpine has not started then, so `x-wk-current` marks
 * those entries as it starts.
 */

import { WK_CURRENT_SCOPE_ATTRIBUTE, markCurrent } from './current.js';
import { lazyPanelTemplate } from './lazy-panel.js';

/**
 * The template of a panel, where the panel is one of a lazy section: its first element, a
 * `<template data-wk-lazy-entries>`. Null otherwise.
 *
 * @param {Element|null|undefined} panel
 * @returns {HTMLTemplateElement|null}
 */
export function panelEntriesTemplate(panel) {
    const first = panel && panel.nodeType === 1 ? panel.firstElementChild : null;

    return first && first.tagName === 'TEMPLATE' && first.hasAttribute('data-wk-lazy-entries') ? first : null;
}

/**
 * The template of the section whose root element is `root`. The panel is a direct child of the
 * root, so a section nested in its entries, whose template is deeper, is never found here.
 *
 * @param {Element|null|undefined} root
 * @returns {HTMLTemplateElement|null}
 */
export function sectionEntriesTemplate(root) {
    if (! root || typeof root.children === 'undefined') {
        return null;
    }

    for (const child of Array.from(root.children)) {
        const template = panelEntriesTemplate(child);

        if (template) {
            return template;
        }
    }

    return null;
}

/**
 * Build the entries a template holds, once. Returns whether this call built them.
 *
 * In a navigation that follows the address (`track-current`, `x-wk-current`), the scope around
 * the section is marked again once the entries stand in the page. The copies carry the state of
 * the render the template came from, and the directive marked the page without them, since a
 * template's content is not part of the page: without this the page's own entry would open idle,
 * or the entry of a page the reader has since left would open as current.
 *
 * @param {HTMLTemplateElement|null} template
 * @returns {boolean}
 */
export function buildLazyEntries(template) {
    if (! template || template._wkBuilt === true) {
        return false;
    }

    template._wkBuilt = true;
    template.after(template.content.cloneNode(true));

    const scope = typeof template.closest === 'function' ? template.closest(`[${WK_CURRENT_SCOPE_ATTRIBUTE}]`) : null;

    if (scope && typeof window !== 'undefined' && window.location) {
        markCurrent(scope, new URL(window.location.href));
    }

    return true;
}

let morphHookInstalled = false;

/**
 * Keep the entries of lazy sections, and the panels of lazy overlays (`lazy-panel.js`), in step
 * with Livewire's renders: one hook per page, installed by the first of them that starts.
 *
 * The server cannot know that a reader opened a section or an overlay, so its markup always holds
 * the template and nothing built from it, and a morph never walks into a template's content.
 * Before the morph compares the element that holds such a template, the hook gives the template in
 * the page the server's new content, for a build that comes later, and, where the page built it,
 * puts the same copy into the server's markup after its template. The morph then updates the built
 * copy as it updates any other element, instead of removing it. For a panel that copy is the
 * `<template x-teleport>`, and the morph carries its update on to the teleported panel.
 */
export function keepLazyEntriesThroughRenders() {
    if (morphHookInstalled || typeof window === 'undefined' || typeof window.Livewire?.hook !== 'function') {
        return;
    }

    morphHookInstalled = true;

    window.Livewire.hook('morph.updating', ({ el, toEl }) => {
        keepBuiltThroughRender(panelEntriesTemplate(el), panelEntriesTemplate(toEl));

        // The root of a lazy overlay names itself, so any other element costs one attribute read.
        if (el?.nodeType === 1 && el.hasAttribute('data-wk-lazy-panel-host')) {
            keepBuiltThroughRender(lazyPanelTemplate(el), lazyPanelTemplate(toEl));
        }
    });
}

/**
 * One template in the page and its counterpart in the server's markup, as the hook above keeps
 * them. A pair with a missing half is left alone.
 *
 * @param {HTMLTemplateElement|null} template  the template in the page
 * @param {HTMLTemplateElement|null} incoming  the template in the server's markup
 */
function keepBuiltThroughRender(template, incoming) {
    if (! template || ! incoming) {
        return;
    }

    const fresh = incoming.content.cloneNode(true);
    keepNestedComponents(fresh, template.content);
    template.content.replaceChildren(fresh);

    // The bare roots stay in this copy: the morph keeps a nested component the page holds.
    if (template._wkBuilt === true) {
        incoming.after(incoming.content.cloneNode(true));
    }
}

/**
 * A render draws each Livewire component nested in the rendered one as a bare root, the root tag
 * with its `wire:id` and without its `wire:snapshot`, because the morph keeps the component the
 * page holds. A template's content is not on the page, so a bare root there would be built as an
 * empty element Livewire cannot start. Each one is replaced by the component as the template held
 * it, which starts when the entries are built.
 *
 * @param {DocumentFragment} fresh     the server's new entries, changed in place
 * @param {DocumentFragment} previous  the entries the template holds
 */
function keepNestedComponents(fresh, previous) {
    const held = Array.from(previous.querySelectorAll('[wire\\:id]'));

    for (const bare of Array.from(fresh.querySelectorAll('[wire\\:id]:not([wire\\:snapshot])'))) {
        const id = bare.getAttribute('wire:id');
        const kept = held.find((component) => component.getAttribute('wire:id') === id);

        if (kept) {
            bare.replaceWith(kept.cloneNode(true));
        }
    }
}
