/**
 * Name the cells of a table that stacks its rows (`stack-below` on `<x-wirekit::table>`) by their
 * columns.
 *
 * Stacked below its breakpoint, every row is a card and every cell a line that starts with the
 * name of its column, which the stylesheet reads from the cell's `data-label`. A cell with a label
 * of its own from the server (`label` on `table.td`) keeps it; every other cell takes the label of
 * the header above its column, counting a `colspan`. A header's `data-wk-th-label` is its words as
 * the page shows them, without the state a sort button adds for a screen reader alone. Rows a
 * Livewire update adds are named as they arrive.
 */

/**
 * The label of each column of a table, by index, from the last row of its head.
 *
 * @param {HTMLTableElement} table
 * @returns {string[]}
 */
export function columnLabels(table) {
    const head = table.tHead;
    const row = head && head.rows.length > 0 ? head.rows[head.rows.length - 1] : null;
    const labels = [];

    if (! row) {
        return labels;
    }

    for (const cell of row.cells) {
        const label = (cell.getAttribute('data-wk-th-label') ?? cell.textContent ?? '').trim();

        for (let span = 0; span < Math.max(1, cell.colSpan || 1); span++) {
            labels.push(label);
        }
    }

    return labels;
}

/**
 * Label every cell of the body and the foot that has no label of its own.
 *
 * @param {HTMLTableElement} table
 */
export function labelCells(table) {
    const labels = columnLabels(table);
    const groups = [...table.tBodies, ...(table.tFoot ? [table.tFoot] : [])];

    for (const group of groups) {
        for (const row of group.rows) {
            let column = 0;

            for (const cell of row.cells) {
                if (! cell.hasAttribute('data-wk-label-own')) {
                    const label = labels[column] ?? '';

                    if (label !== '') {
                        cell.setAttribute('data-label', label);
                    } else {
                        cell.removeAttribute('data-label');
                    }
                }

                column += Math.max(1, cell.colSpan || 1);
            }
        }
    }
}

/**
 * Register the `x-wk-table-labels` directive, which goes on the `<table>` of a table with
 * `stack-below`. Idempotent: a second bundle registers the same handler.
 *
 * @param {object} Alpine
 */
export function registerTableLabelsDirective(Alpine) {
    Alpine.directive('wk-table-labels', (el, directive, { cleanup }) => {
        if (typeof el.tHead === 'undefined') {
            return;
        }

        labelCells(el);

        // Only rows and cells coming and going: the labels this writes are attributes, which
        // the observer does not watch, so it never answers itself.
        const observer = typeof MutationObserver === 'function' ? new MutationObserver(() => labelCells(el)) : null;
        observer?.observe(el, { childList: true, subtree: true });

        cleanup(() => observer?.disconnect());
    });
}
