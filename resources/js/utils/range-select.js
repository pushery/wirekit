/**
 * Pick a range of checkboxes with Shift held (`x-wk-range-select`).
 *
 * On the element that holds a list of checkboxes, a table body or a list of options: a click on a
 * checkbox with Shift held gives every checkbox from the one clicked last to this one the state
 * this click gives it, as a mail client or a file manager does. A click that clears a checked box
 * clears the range. Each checkbox that changes is clicked, so it sends exactly what a reader's
 * click sends, `input` and `change` included, and `wire:model` on an array, `x-model` and a
 * listener of your own hear it as they hear a click. A disabled checkbox keeps its state, and a
 * switch or a checkbox inside `[data-wk-range-skip]` is not part of the range (isRowBox()), nor is
 * one the reader cannot see, in a row a filter hides or a closed `<details>` (isShown()). Shift
 * and the Space key on a focused checkbox pick a range from the keyboard, and Shift held on a press
 * keeps the browser from selecting the text between the two clicks.
 *
 * A checkbox belongs to the nearest `x-wk-range-select` around it (rangeRootOf()), so lists inside
 * each other each pick in themselves: a table body that carries the directive inside a bulk
 * selection, which carries its own, picks a range once, where two handlers would each click the
 * range and the second round would clear what the first ticked.
 */

/**
 * The elements that carry `x-wk-range-select`. Held in a set rather than an attribute, which a
 * Livewire morph takes off again, and kept in the global symbol registry, so two bundles on one page
 * share it.
 */
const ROOTS = Symbol.for('wirekit.rangeSelectRoots');

function rangeRoots() {
    if (! (globalThis[ROOTS] instanceof WeakSet)) {
        globalThis[ROOTS] = new WeakSet();
    }

    return globalThis[ROOTS];
}

/**
 * The `x-wk-range-select` element a checkbox belongs to: the nearest one around it, or null.
 *
 * @param {Element} box
 * @param {(node: Element) => boolean} [isRoot]  whether an element carries the directive
 * @returns {?Element}
 */
export function rangeRootOf(box, isRoot = (node) => rangeRoots().has(node)) {
    for (let node = box.parentElement ?? null; node; node = node.parentElement ?? null) {
        if (isRoot(node)) {
            return node;
        }
    }

    return null;
}

/**
 * Whether a checkbox is one of a root's own: no other `x-wk-range-select` stands between them. A
 * checkbox outside every root, which no directive has claimed yet, counts as the root's.
 *
 * @param {Element} box
 * @param {Element} root
 * @param {(node: Element) => boolean} [isRoot]
 * @returns {boolean}
 */
function belongsTo(box, root, isRoot) {
    const owner = rangeRootOf(box, isRoot);

    return owner === null || owner === root;
}

/**
 * Whether a checkbox is one a range picks, and one a bulk selection counts as a row.
 *
 * A switch (`role="switch"`, as `toggle` renders it) is a setting that takes effect at once, not
 * the choice of a row: a range over the rows would turn it on or off for every record between the
 * two clicks. A checkbox inside `[data-wk-range-skip]` is left out as well, for any other box a
 * row carries.
 *
 * @param {Element} box
 * @returns {boolean}
 */
export function isRowBox(box) {
    const role = String(box.getAttribute?.('role') ?? '').trim().toLowerCase().split(/\s+/)[0];

    return role !== 'switch' && ! box.closest?.('[data-wk-range-skip]');
}

/**
 * Whether the reader can see a checkbox, or a label that stands for it.
 *
 * A range covers what stands between two clicks on the page. A checkbox in a row that a filter
 * hides (`x-show`, `hidden`) or inside a closed `<details>` has no layout box, and ticking it would
 * hand a bulk action a row nobody saw. A checkbox hidden on purpose behind a label drawn in its
 * place (`class="hidden peer"`) counts through that label, and one with `sr-only` keeps a box of
 * its own.
 *
 * @param {Element} box
 * @returns {boolean}
 */
export function isShown(box) {
    // `checkVisibility()` knows a closed `<details>` and a panel kept `hidden="until-found"`, which
    // newer engines hide with `content-visibility` rather than `display: none`, so a box inside
    // still reports rectangles. The engines of the baseline without it give neither a box, and
    // there the rectangles answer.
    const rendered = (node) => {
        if (typeof node?.checkVisibility === 'function') {
            return node.checkVisibility();
        }

        return typeof node?.getClientRects !== 'function' || node.getClientRects().length > 0;
    };

    return rendered(box) || [...(box.labels ?? [])].some((label) => rendered(label));
}

/**
 * The checkboxes of a range, in the order the page shows them: the root's own the reader can see,
 * without those of a list inside it that carries `x-wk-range-select` itself.
 *
 * @param {Element} root
 * @param {(node: Element) => boolean} [isRoot]  whether an element carries the directive
 * @returns {HTMLInputElement[]}
 */
export function rangeBoxes(root, isRoot) {
    return [...root.querySelectorAll('input[type="checkbox"]')].filter((box) => isRowBox(box) && isShown(box) && belongsTo(box, root, isRoot));
}

/**
 * The checkboxes a click with Shift held changes: those from the anchor to the target, both
 * included, whose state differs from the one the target now has and that can change. The target
 * itself is left out, its own click has already set it.
 *
 * @param {HTMLInputElement[]} boxes
 * @param {HTMLInputElement|null} anchor
 * @param {HTMLInputElement} target
 * @returns {HTMLInputElement[]}
 */
export function boxesToChange(boxes, anchor, target) {
    const from = anchor === null ? -1 : boxes.indexOf(anchor);
    const to = boxes.indexOf(target);

    if (from === -1 || to === -1 || from === to) {
        return [];
    }

    const [start, end] = from < to ? [from, to] : [to, from];

    return boxes.slice(start, end + 1).filter((box) => box !== target && ! box.disabled && box.checked !== target.checked);
}

/**
 * Register the `x-wk-range-select` directive. Idempotent: a second bundle registers the same
 * handler.
 *
 * @param {object} Alpine
 */
export function registerRangeSelectDirective(Alpine) {
    Alpine.directive('wk-range-select', (el, directive, { cleanup }) => {
        let anchor = null;
        let applying = false;
        let shiftFromKeyboard = false;
        let shiftFromLabel = false;

        rangeRoots().add(el);

        // A checkbox of a list inside this one that carries the directive itself is that list's:
        // its press, its keys and its clicks, the ones its own range sends included, pass by here.
        const isRangeBox = (node) => node instanceof HTMLInputElement
            && node.type === 'checkbox'
            && el.contains(node)
            && isRowBox(node)
            && belongsTo(node, el);

        // The checkbox a press lands on: the box itself, or the one its label names.
        const boxOf = (node) => {
            if (isRangeBox(node)) {
                return node;
            }

            const label = node instanceof Element ? node.closest('label') : null;

            return label && isRangeBox(label.control) ? label.control : null;
        };

        // A press with Shift held extends the text selection to the press, which would mark the
        // text of every row between the two clicks. The click that follows the press still lands.
        const onMouseDown = (event) => {
            if (event.shiftKey && boxOf(event.target)) {
                event.preventDefault();
            }
        };

        // Shift and Space on a focused checkbox: the click the key sends does not report Shift in
        // every engine, so the key says it. The flag goes once the click has had its turn.
        const onKeyDown = (event) => {
            if (event.key === ' ' && event.shiftKey && isRangeBox(event.target)) {
                shiftFromKeyboard = true;
            }
        };

        const onKeyUp = (event) => {
            if (event.key === ' ') {
                setTimeout(() => {
                    shiftFromKeyboard = false;
                }, 0);
            }
        };

        // A label hands its click to its checkbox as a second click, so only the one on the
        // checkbox counts, and the clicks this sends itself count not at all. The label's click
        // says whether Shift was held, for an engine that leaves it off the second one; the
        // second click comes before any timer, so the note goes after it.
        const onClick = (event) => {
            if (applying) {
                return;
            }

            if (! isRangeBox(event.target)) {
                if (event.shiftKey && boxOf(event.target)) {
                    shiftFromLabel = true;
                    setTimeout(() => {
                        shiftFromLabel = false;
                    }, 0);
                }

                return;
            }

            const target = event.target;
            const shift = event.shiftKey || shiftFromKeyboard || shiftFromLabel;
            shiftFromKeyboard = false;
            shiftFromLabel = false;

            if (shift) {
                const changing = boxesToChange(rangeBoxes(el), anchor, target);

                // Once the target's own click has run its course, its `change` included. Boxes bound
                // to one array (`wire:model`, `x-model`) take their state from the array again after
                // a script changes it; a click sent while the target's click is still dispatched
                // changes the array first, the target is set back to an array without it, and its
                // `change` then reads it as cleared.
                if (changing.length > 0) {
                    setTimeout(() => {
                        applying = true;

                        try {
                            for (const box of changing) {
                                box.click();
                            }
                        } finally {
                            applying = false;
                        }
                    }, 0);
                }

                window.getSelection?.()?.removeAllRanges();
            }

            anchor = target;
        };

        el.addEventListener('mousedown', onMouseDown);
        el.addEventListener('keydown', onKeyDown);
        el.addEventListener('keyup', onKeyUp);
        el.addEventListener('click', onClick);

        cleanup(() => {
            rangeRoots().delete(el);
            el.removeEventListener('mousedown', onMouseDown);
            el.removeEventListener('keydown', onKeyDown);
            el.removeEventListener('keyup', onKeyUp);
            el.removeEventListener('click', onClick);
        });
    });
}
