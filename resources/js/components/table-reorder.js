/**
 * Table reorder: puts the focus back on the arrow a reader pressed, once the server has moved
 * the row.
 *
 * `table.reorder` moves a row by calling a Livewire method, and the render that answers arrives
 * as a morph. A morph that moves the focused row detaches it on the way, and a detached element
 * loses the focus to the page. It is the moving-up arrow that pays for it: the morph walks the
 * new order front to back and pulls the row that moved up forward, so the focused row is the one
 * that travels. A reader moving a row three places pressed the arrow once and then had to find
 * it again, twice.
 *
 * So the cell remembers which arrow was pressed, by the row's key and the direction, and after
 * the next Livewire commit that succeeds it focuses the same arrow in the row's new place, or
 * the other one where the row reached an end and its arrow is disabled. It does so only when the
 * focus was lost: a reader who has moved on during the round trip keeps where they went.
 *
 * ONE SLOT PER PAGE, as module state: one keyboard presses one arrow at a time, and the cell
 * that pressed it may itself be replaced by the morph. The Livewire hook is registered once.
 *
 * The row is found in its own list. `table.reorder` and `reorder` both mount this, and two lists
 * on one page can share row keys, or one can sit inside a row of the other. The list is the
 * element that holds the row marked `wire:sort:item`, or the table where the rows carry no mark,
 * and a control is only taken from the list the pressed arrow was in.
 *
 * Lifecycle: no observer, no timer, no listener of its own; the commit hook is page-wide and
 * lives as long as Livewire does.
 *
 * @param {Object} config
 * @param {string} config.key - the row's key, as the control's `data-wk-reorder-key` carries it
 */

/** @type {{ key: string, direction: string, list: Element | null } | null} */
let pending = null;
let hooked = false;

/** The list an element's row belongs to: the holder of its `wire:sort:item` row, or its table. */
function listOf(element) {
    const row = element?.closest?.('[wire\\:sort\\:item]');

    return row?.parentElement ?? element?.closest?.('table') ?? null;
}

/**
 * The control for a key, in the list the arrow was pressed in while that list is still there.
 * Without it, the first control on the page with that key.
 */
function findCell(list, key) {
    const scope = list?.isConnected ? list : (typeof document !== 'undefined' ? document : null);
    if (! scope) {
        return null;
    }

    const cells = [...scope.querySelectorAll('[data-wk-reorder-key]')].filter((cell) => cell.getAttribute('data-wk-reorder-key') === key);

    // A list's descendants include the rows of a list nested in one of its rows.
    return (scope === list ? cells.find((cell) => listOf(cell) === list) : cells[0]) ?? null;
}

/** After a commit: focus the remembered arrow again, if the focus was lost on the way. */
export function restoreReorderFocus() {
    if (! pending) {
        return;
    }

    const { key, direction, list } = pending;
    pending = null;

    const active = typeof document !== 'undefined' ? document.activeElement : null;
    if (active && active !== document.body && active.isConnected) {
        return;
    }

    const cell = findCell(list, key);
    if (! cell) {
        return;
    }

    const arrow = (name) => cell.querySelector(`[data-wk-reorder-arrow="${name}"]`);
    const other = direction === 'up' ? 'down' : 'up';
    const target = [arrow(direction), arrow(other)].find((button) => button && ! button.disabled);

    target?.focus();
}

export default function wirekitTableReorder(config = {}) {
    return {
        key: config.key === undefined || config.key === null ? '' : String(config.key),

        init() {
            if (hooked || typeof window === 'undefined' || typeof window.Livewire?.hook !== 'function') {
                return;
            }

            hooked = true;
            window.Livewire.hook('commit', ({ succeed }) => {
                succeed(() => queueMicrotask(restoreReorderFocus));
            });
        },

        /**
         * An arrow was pressed. Remembered only while it holds the focus, which is what a keyboard
         * press does; a pointer that did not focus it has nothing to lose.
         */
        remember(direction, button) {
            if (! button || typeof document === 'undefined' || document.activeElement !== button) {
                return;
            }

            pending = { key: this.key, direction, list: listOf(button) };
        },
    };
}
