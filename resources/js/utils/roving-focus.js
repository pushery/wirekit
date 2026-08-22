/**
 * Roving focus across a composite widget's items, resolved from the DOM at call time.
 *
 * Reading the items on every keypress rather than holding an index is not a style
 * choice, and it is the whole reason a server-driven tablist can share this code with
 * the client-side one: Livewire REPLACES the markup on every round trip. An index
 * captured at init would survive the morph and point into a list that no longer exists,
 * and the failure is quiet — focus lands on the wrong item, or on nothing.
 *
 * `document.activeElement` is the only honest starting point for the same reason. The
 * item that has focus is a fact about the page right now; anything remembered is a claim
 * about a page that may have been rebuilt since.
 *
 * @param {Element|null} root      the container holding the items
 * @param {string} direction       'next' | 'prev' | 'first' | 'last'
 * @param {string} [selector]      what counts as an item
 * @returns {boolean}              whether focus actually moved
 */
export function moveRovingFocus(root, direction, selector = '[role=tab]:not([disabled])') {
    if (! root || typeof root.querySelectorAll !== 'function') {
        return false;
    }

    const items = Array.from(root.querySelectorAll(selector));

    if (items.length === 0) {
        return false;
    }

    const current = items.indexOf(document.activeElement);

    // Focus is somewhere else entirely — another widget, or the body after a morph
    // stole it. Moving "next" from nowhere would be a guess about which item the
    // reader meant, and `first`/`last` are absolute, so those two still answer.
    if (current === -1 && (direction === 'next' || direction === 'prev')) {
        return false;
    }

    const index = {
        next: current + 1,
        prev: current - 1,
        first: 0,
        last: items.length - 1,
    }[direction];

    if (index === undefined) {
        return false;
    }

    // Modulo twice: JS keeps the sign of the dividend, so -1 % 3 is -1 rather than 2
    // and the backwards wrap would land nowhere.
    items[((index % items.length) + items.length) % items.length].focus();

    return true;
}

/**
 * Which item a type-ahead buffer selects — the WAI-ARIA menu behavior, as arithmetic.
 *
 * Pure and DOM-free on purpose: this is the part worth testing, and it is the part that is
 * awkward to test through a browser. It lives beside `moveRovingFocus` because every
 * composite widget that already imports that one needs this one next, and each of those is
 * then a few lines rather than a re-derivation.
 *
 * THE ONE SUBTLETY IS THE REPEATED CHARACTER, and getting it wrong is what makes a
 * type-ahead feel broken. Pressing `a` repeatedly must CYCLE through every row starting
 * with `a`, so a buffer of all-identical characters searches for that single character
 * beginning AFTER the current row. A genuine refinement — `co` growing to `com` — must
 * start AT the current row instead, or the row the reader has already narrowed to would be
 * skipped and `comp` could never reach it.
 *
 * @param {string[]} labels    Visible text of each item, in DOM order.
 * @param {string}   buffer    What the reader has typed so far.
 * @param {number}   currentIndex  Index of the focused item, or -1 for none.
 * @returns {number} Index to focus, or -1 when nothing matches.
 */
export function typeAheadIndex(labels, buffer, currentIndex) {
    if (! buffer || ! labels || labels.length === 0) {
        return -1;
    }

    const typed = String(buffer).toLowerCase();
    const sameChar = [...typed].every((c) => c === typed[0]);
    const term = sameChar ? typed[0] : typed;

    // Repeated character cycles, so start one past the current row; a refinement stays
    // put, so the already-matched row remains eligible.
    const offset = sameChar ? 1 : 0;
    const from = currentIndex < 0 ? 0 : currentIndex;

    for (let i = 0; i < labels.length; i += 1) {
        const index = (from + offset + i) % labels.length;

        if (String(labels[index] ?? '').trim().toLowerCase().startsWith(term)) {
            return index;
        }
    }

    return -1;
}
