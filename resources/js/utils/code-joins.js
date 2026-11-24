/**
 * The joins of code that wraps, in the browser: the counterpart to `Support\CodeWrapJoins`.
 *
 * In a code block with `wrap`, and in a code block of a prose body, the server stands each run of
 * hyphens inside a token with the character after it in a `<span data-wk-join>`, which the
 * stylesheet does not wrap, so `->` or `--force` never ends one line and starts the next.
 *
 * A highlighter that runs in the browser writes the block anew from its text, and the joins go
 * with the markup it replaces. highlight.js also warns about every block that holds an element
 * when it starts, as it would about markup a page forgot to escape. `release()` takes the joins
 * out and leaves their text, `apply()` writes them into whatever markup the highlighter left, and
 * the two hooks of the object below run them around highlight.js:
 *
 *   hljs.addPlugin(window.wirekitCodeJoins);
 *
 * `apply()` writes joins only where they belong: into a code block with `wrap`, and into a block
 * `release()` took joins out of, which is how a code block of a prose body is told apart. So it can
 * run after every block a highlighter wrote. A join can hold the characters of two tokens, `-` in
 * one element and `>` in the next: it then holds a copy of each token's element with its own
 * character, so the colors stay. Only elements are added, so the text a copy reads stays the same.
 *
 * Importable on its own as `resources/js/utils/code-joins.js`: it has no dependencies.
 *
 * @module
 */

/**
 * A run of hyphens and the one character after it, unless that is a space, a digit or another
 * hyphen. The rule of `CodeWrapJoins::html()`, read here from text rather than from markup, so
 * an entity of the markup is the character it stands for. tests/fixtures/code-join-cases.json
 * holds both to the same answers.
 */
const JOIN = /-+[^\s\p{Nd}-]/gu;

/** `NodeFilter.SHOW_TEXT`, written out so the module also loads where `NodeFilter` is not defined. */
const SHOW_TEXT = 4;

/** The code elements `release()` took joins out of, until `apply()` writes them back. */
const released = new WeakSet();

/**
 * Where the joins of a text go, as `[start, end)` offsets in UTF-16 code units, which are the
 * offsets of a text node's data.
 *
 * @param {string} text
 * @returns {Array<[number, number]>}
 */
export function codeJoinRanges(text) {
    const ranges = [];

    for (const match of String(text ?? '').matchAll(JOIN)) {
        ranges.push([match.index, match.index + match[0].length]);
    }

    return ranges;
}

/** Replaces each join of an element with its contents. Answers whether there was one. */
function unwrap(code) {
    const joins = Array.from(code?.querySelectorAll?.('[data-wk-join]') ?? []);

    for (const join of joins) {
        join.replaceWith(...join.childNodes);
    }

    if (joins.length > 0) {
        code.normalize();
    }

    return joins.length > 0;
}

/**
 * Takes the joins out of a code element, leaves their text where it was, and remembers the
 * element for `applyCodeJoins()`.
 *
 * @param {Element} code
 * @returns {boolean} whether the element held a join
 */
export function releaseCodeJoins(code) {
    const held = unwrap(code);

    if (held) {
        released.add(code);
    }

    return held;
}

/**
 * Writes the joins into a code element with `wrap`, or into one `releaseCodeJoins()` took joins out
 * of, across whatever markup its text stands in. Any other element stays as it is. A second call
 * on the same block changes nothing, since it takes the joins of the first one out before it
 * writes them again.
 *
 * @param {Element} code
 * @returns {boolean} whether it wrote the joins
 */
export function applyCodeJoins(code) {
    if (! code || (! released.has(code) && ! code.matches?.('[data-wk-wrap]'))) {
        return false;
    }

    released.delete(code);

    const doc = code.ownerDocument;

    if (! doc) {
        return false;
    }

    unwrap(code);

    // Every text node in document order, with the offsets of the code's text it holds.
    const nodes = [];
    let length = 0;
    const walker = doc.createTreeWalker(code, SHOW_TEXT);

    for (let node = walker.nextNode(); node; node = walker.nextNode()) {
        nodes.push({ node, start: length, end: length + node.data.length });
        length += node.data.length;
    }

    const ranges = codeJoinRanges(nodes.map(({ node }) => node.data).join(''));
    const at = (offset) => nodes.find(({ start, end }) => offset >= start && offset < end);

    // From the last join to the first: extracting a join changes the text nodes from its start on,
    // so the offsets of every join before it still hold.
    for (let index = ranges.length - 1; index >= 0; index--) {
        const [start, end] = ranges[index];
        const first = at(start);
        const last = at(end - 1);

        if (! first || ! last) {
            continue;
        }

        const range = doc.createRange();
        range.setStart(first.node, start - first.start);
        range.setEnd(last.node, end - last.start);

        // A token the range cuts comes along as a copy of its element that holds the cut part.
        const join = doc.createElement('span');
        join.setAttribute('data-wk-join', '');
        join.appendChild(range.extractContents());
        range.insertNode(join);

        // A token the join took every character of stays behind empty.
        prune(first.node, code);
        prune(last.node, code);
    }

    return true;
}

/** Removes a text node left empty, and each element that holds nothing once it is gone, up to `stop`. */
function prune(node, stop) {
    let current = node;

    while (current && current !== stop && current.parentNode
        && (current.nodeType === 3 ? current.data === '' : current.childNodes.length === 0)) {
        const parent = current.parentNode;
        parent.removeChild(current);
        current = parent;
    }
}

/**
 * A highlight.js plugin, and the two operations for any other highlighter.
 *
 * Before highlight.js reads a block, the joins come out, so it finds text only and does not warn.
 * After it has written the block, `apply()` writes them into the colored one. highlight.js fires
 * its first hook for a block it highlighted before as well, then leaves that block alone and fires
 * no second one, so the first hook does not touch such a block.
 */
export const wirekitCodeJoins = {
    apply: applyCodeJoins,
    release: releaseCodeJoins,

    'before:highlightElement'({ el } = {}) {
        if (el && ! el.dataset?.highlighted) {
            releaseCodeJoins(el);
        }
    },

    'after:highlightElement'({ el } = {}) {
        if (el) {
            applyCodeJoins(el);
        }
    },
};
