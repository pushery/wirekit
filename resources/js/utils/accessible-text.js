/**
 * The VISIBLE text of an element, with `aria-hidden` descendants left out.
 *
 * ⚠️ The summary line used to say "the text as a reader of its ACCESSIBLE content would see
 * it", which reads as the whole accessible-name computation and is one rule of it. The
 * paragraphs below always scoped it correctly; the first line did not, and the first line is
 * what a caller reads.
 *
 * `textContent` walks straight through `aria-hidden="true"`, which is the one attribute
 * whose entire meaning is "this is not part of the accessible content". Anything building a
 * label from a heading — a table of contents, a spine, a minimap — is exactly the kind of
 * reader that should honor it.
 *
 * WHAT THIS FIXES. Every automatic anchor tool puts the permalink marker inside the
 * heading as a real text node:
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
 * entry COUNTS notices, and the panel simply reads badly — it is only ever visible to a
 * person looking at the rendered panel.
 *
 * WHY A CLONE. Removing the nodes in place would mutate the page — the marker would
 * disappear from the heading itself. Cloning costs one shallow tree copy per heading, and
 * headings are counted in tens.
 *
 * WHAT THIS DELIBERATELY DOES NOT DO, because the omission looks like a gap and is not.
 * `aria-label` and `aria-labelledby` ON THE ELEMENT ITSELF override its content in the
 * accessible-name computation, and this function ignores them. Every caller renders VISIBLE
 * navigation — the table of contents, the spine, the minimap. Resolving an author's
 * `aria-label` first would make the entry read "Pricing for teams" beside a heading that
 * says "Pricing", so a sighted reader would get a panel that disagrees with the page it
 * describes: a real defect traded for a hypothetical one.
 *
 * Whether those panels should ever show an accessible name instead of the visible text is a
 * design question about the panels, not a missing branch here. If it is ever answered yes,
 * the answer is a second function, so the visible-label callers keep this one.
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
