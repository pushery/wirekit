/**
 * The text of an element as a reader of its ACCESSIBLE content would see it.
 *
 * `textContent` walks straight through `aria-hidden="true"`, which is the one attribute
 * whose entire meaning is "this is not part of the accessible content". Anything building a
 * label from a heading — a table of contents, a spine, a minimap — is exactly the kind of
 * reader that should honor it.
 *
 * WHAT THIS FIXES, reported from the documentation site on 2026-08-29. Every automatic
 * anchor tool puts the permalink marker inside the heading as a real text node:
 *
 *     <h2 id="basic-usage">
 *       <a href="#basic-usage" class="heading-permalink">
 *         <span class="heading-anchor" aria-hidden="true">#</span>Basic Usage
 *       </a>
 *     </h2>
 *
 * So every entry rendered as "#Basic Usage". It is invisible on screen (the span is
 * transparent until hover) and invisible to assistive technology (the `aria-hidden` does
 * its job), which is what made it easy to ship: nothing counts wrong, no test that measures
 * entry COUNTS notices, and the panel simply reads badly. It was found in a screenshot.
 *
 * WHY A CLONE. Removing the nodes in place would mutate the page — the marker would
 * disappear from the heading itself. Cloning costs one shallow tree copy per heading, and
 * headings are counted in tens.
 *
 * @param {Element|null} el
 * @returns {string}
 */
export function accessibleText(el) {
    if (!el) return '';

    // No hidden descendants is the ordinary case, so it is worth not cloning for it.
    if (typeof el.querySelector !== 'function' || !el.querySelector('[aria-hidden="true"]')) {
        return (el.textContent || '').trim();
    }

    const clone = el.cloneNode(true);
    clone.querySelectorAll('[aria-hidden="true"]').forEach((node) => node.remove());

    return (clone.textContent || '').trim();
}
