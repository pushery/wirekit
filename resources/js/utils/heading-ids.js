/**
 * Give every collected heading an anchor id, and every outline entry a key of its own.
 *
 * An id a heading already carries stays as it is: a link from elsewhere or a bookmark points at
 * it, and an outline that renamed it would break that silently. Two headings with one id are still
 * two entries, so the key that `x-for` needs to be unique is the id with a suffix where it repeats,
 * and each entry keeps its own element in `item.el` to jump to.
 *
 * An id the outline generates is checked against every id the document already holds, as well as
 * against the ones it generated before, so a slug never duplicates an id on the page.
 *
 * @param {Array<{el: Element, text: string, id?: string, key?: string}>} items
 * @param {(text: string) => string} slugify
 * @param {() => number} nextSequence a counter for headings whose text gives no slug
 * @param {Document|undefined} doc
 */
export function assignHeadingIds(items, slugify, nextSequence, doc = globalThis.document) {
    const generated = new Set();
    const keys = new Set();
    const takenOnThePage = (candidate, el) => {
        if (!doc || typeof doc.getElementById !== 'function') {
            return false;
        }

        const found = doc.getElementById(candidate);

        return found !== null && found !== el;
    };

    for (const item of items) {
        let id = item.el.id;

        if (!id) {
            const base = slugify(item.text) || `section-${nextSequence()}`;
            id = base;

            for (let n = 2; generated.has(id) || takenOnThePage(id, item.el); n++) {
                id = `${base}-${n}`;
            }

            generated.add(id);
            item.el.id = id;
        }

        item.id = id;

        let key = id;

        for (let n = 2; keys.has(key); n++) {
            key = `${id}--${n}`;
        }

        keys.add(key);
        item.key = key;
    }
}
