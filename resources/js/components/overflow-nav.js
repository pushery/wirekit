/**
 * wirekitOverflowNav — a wrapping row of links kept to a number of lines.
 *
 * The entries the rows have no room for wait in a menu at the end of the last row, behind a button
 * that shows their count. The entry marked current never goes into the menu.
 *
 * How many entries fit is a question about the width, and only the browser can answer it. The row
 * lays every entry out once, reads each width, and replays the wrapping of its flex row in
 * arithmetic (`rowsFor`), so one measurement decides it instead of a round of rendering per entry.
 * Entries come off the end one at a time (`overflowFor`) until the rest and the button fit, so every
 * row is full before anything moves into the menu.
 *
 * Three things the measurement has to respect:
 *
 * - It is synchronous. Showing everything through the component's state and measuring on the next
 *   tick lets a frame be drawn in between, the resize observer sees the row grow and shrink, and the
 *   row measures itself again on every frame. So `fit()` shows every entry by hand, reads the widths,
 *   puts the styles back and only then sets the state, all in one task.
 * - `x-show` hides at once but shows on the next animation frame, so an entry shown through state
 *   and measured right after would still read as hidden. The inline styles `fit()` sets are what the
 *   measurement reads.
 * - Only a change of WIDTH asks for a new measurement, together with entries being added or removed.
 *   The height changes whenever an entry moves into the menu, and answering it would loop.
 */

/**
 * The number of rows a wrapping flex row takes for items of these widths, in this order.
 *
 * Half a pixel of slack: widths are fractional, and a sum of them can land a hair over a width
 * that the browser, rounding differently, still fits.
 *
 * @param {number[]} widths
 * @param {number} gap
 * @param {number} capacity
 * @returns {number}
 */
export function rowsFor(widths, gap, capacity) {
    if (widths.length === 0) {
        return 0;
    }

    let rows = 1;
    let used = 0;

    widths.forEach((width, index) => {
        if (index > 0 && used + gap + width > capacity + 0.5) {
            rows += 1;
            used = width;
        } else {
            used = index === 0 ? width : used + gap + width;
        }
    });

    return rows;
}

/**
 * The indices of the entries that go into the menu.
 *
 * Taken off the end, never a pinned one, until the entries that are left and the menu's button fit
 * in `lines` rows. When everything fits without the button, nothing goes. When only pinned entries
 * are left, the removal stops there, and the rows may then exceed `lines`: a pinned entry is never
 * hidden to meet the limit.
 *
 * @param {{widths: number[], moreWidth: number, gap: number, capacity: number, lines: number, pinned?: number[]}} measured
 * @returns {number[]}
 */
export function overflowFor({ widths, moreWidth, gap, capacity, lines, pinned = [] }) {
    const visible = widths.map((width, index) => index);

    if (rowsFor(widths, gap, capacity) <= lines) {
        return [];
    }

    const hidden = [];
    const rowsWithButton = () => rowsFor([...visible.map((index) => widths[index]), moreWidth], gap, capacity);

    while (rowsWithButton() > lines) {
        let last = -1;

        for (let position = visible.length - 1; position >= 0; position -= 1) {
            if (! pinned.includes(visible[position])) {
                last = position;

                break;
            }
        }

        if (last === -1) {
            break;
        }

        hidden.push(visible[last]);
        visible.splice(last, 1);
    }

    return hidden.sort((a, b) => a - b);
}

/** A positive whole number of lines, 2 when the value is not one. */
function normalizeLines(value) {
    const lines = Number.parseInt(value, 10);

    return Number.isFinite(lines) && lines > 0 ? lines : 2;
}

/** An element's width with its horizontal margins, the room it takes in a flex row. */
function outerWidth(el) {
    const style = getComputedStyle(el);

    return el.getBoundingClientRect().width
        + (Number.parseFloat(style.marginLeft) || 0)
        + (Number.parseFloat(style.marginRight) || 0);
}

export default function wirekitOverflowNav(options = {}) {
    return {
        // The server-side index of every entry that is in the menu rather than in the rows.
        overflowIndexes: [],

        _lines: normalizeLines(options.lines),
        _moreOne: String(options.moreOne || ''),
        _moreMany: String(options.moreMany || ''),
        _rowWidth: null,
        _pendingFrame: null,
        _resizes: null,
        _changes: null,

        init() {
            const row = this.$refs.row;

            if (! row) {
                return;
            }

            if (typeof ResizeObserver === 'function') {
                this._resizes = new ResizeObserver((entries) => {
                    const width = entries[0]?.contentRect?.width;

                    if (width !== this._rowWidth) {
                        this._rowWidth = width;
                        this._schedule();
                    }
                });
                this._resizes.observe(row);
            }

            if (typeof MutationObserver === 'function') {
                this._changes = new MutationObserver(() => this._schedule());
                this._changes.observe(row, { childList: true });
            }

            this.fit();
        },

        destroy() {
            this._resizes?.disconnect();
            this._changes?.disconnect();
            this._resizes = null;
            this._changes = null;

            if (this._pendingFrame !== null) {
                cancelAnimationFrame(this._pendingFrame);
                this._pendingFrame = null;
            }
        },

        _schedule() {
            if (this._pendingFrame !== null) {
                return;
            }

            this._pendingFrame = requestAnimationFrame(() => {
                this._pendingFrame = null;
                this.fit();
            });
        },

        /** Measure every entry once and decide which ones go into the menu. */
        fit() {
            const row = this.$refs.row;
            const more = this.$refs.more;

            if (! row || ! more) {
                return;
            }

            const count = more.querySelector('[data-wk-overflow-count]');
            const items = Array.from(row.children).filter((el) => el !== more && el.hasAttribute('data-wk-overflow-index'));
            const all = [...items, more];
            const displays = all.map((el) => el.style.display);
            const text = count ? count.textContent : '';

            // Everything on screen at once, and the button with the widest count it will carry, so
            // the widths read below are the ones the row has with nothing hidden.
            all.forEach((el) => {
                el.style.display = '';
            });

            if (count) {
                count.textContent = '+99';
            }

            const style = getComputedStyle(row);
            const measured = {
                widths: items.map(outerWidth),
                moreWidth: outerWidth(more),
                gap: Number.parseFloat(style.columnGap) || 0,
                capacity: row.clientWidth
                    - (Number.parseFloat(style.paddingLeft) || 0)
                    - (Number.parseFloat(style.paddingRight) || 0),
                lines: this._lines,
                pinned: items
                    .map((el, index) => (el.hasAttribute('data-wk-overflow-current') ? index : -1))
                    .filter((index) => index >= 0),
            };

            all.forEach((el, index) => {
                el.style.display = displays[index];
            });

            if (count) {
                count.textContent = text;
            }

            this.overflowIndexes = overflowFor(measured)
                .map((position) => Number(items[position].dataset.wkOverflowIndex));
        },

        shownHere(index) {
            return ! this.overflowIndexes.includes(index);
        },

        menuHere(index) {
            return this.overflowIndexes.includes(index);
        },

        get overflowing() {
            return this.overflowIndexes.length > 0;
        },

        get moreText() {
            return '+' + this.overflowIndexes.length;
        },

        // What a screen reader hears on the button: how many links the menu holds.
        get moreName() {
            const total = this.overflowIndexes.length;
            const form = total === 1 ? this._moreOne : this._moreMany;

            return form.replace('__COUNT__', String(total));
        },
    };
}
