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
