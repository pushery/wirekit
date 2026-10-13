/**
 * What a reading outline needs to follow the headings of a page that changes after it started.
 *
 * The spine and the TOC collect their headings in init(). A Livewire morph that adds, removes,
 * renames or re-ids a section later would otherwise leave the outline listing what is gone and
 * missing what came, and the active section would be measured against elements no longer on the
 * page. Each outline watches its target with these options and collects again, at most once a
 * frame, after a batch of changes `changesOutside()` lets through.
 */

/** Every node under the target, every text in it, and the `id` of every element. */
export const HEADING_CHANGES = {
    childList: true,
    subtree: true,
    characterData: true,
    attributes: true,
    attributeFilter: ['id'],
};

/**
 * Whether a batch of changes holds one outside the outline itself.
 *
 * An outline inside its own target renders its links again each time it collects, and collecting
 * again because of that would never end.
 *
 * @param {MutationRecord[]} records
 * @param {Element} outline
 */
export function changesOutside(records, outline) {
    return records.some((record) => !outline.contains(record.target));
}

/**
 * Whether a fresh collection lists the same headings as the outline, in the same order and under
 * the same text and `id`.
 *
 * @param {Array<{el: Element, text: string, id: string}>} fresh
 * @param {Array<{el: Element, text: string, id: string}>} listed
 */
export function sameHeadings(fresh, listed) {
    return fresh.length === listed.length
        && fresh.every((item, i) => item.el === listed[i].el && item.text === listed[i].text && item.id === listed[i].id);
}

/**
 * Where the heading at `index` of the listed entries stands in a fresh collection, or -1 when it
 * is gone or `index` names no entry.
 *
 * @param {Array<{el: Element}>} fresh
 * @param {Array<{el: Element}>} listed
 * @param {number} index
 */
export function indexInFresh(fresh, listed, index) {
    const el = listed[index]?.el;

    return el ? fresh.findIndex((item) => item.el === el) : -1;
}
