/**
 * The steps undo and redo walk through in `<x-wirekit::image-editor>`: a bounded list of the states
 * before each change, and the states undone since the last change.
 *
 * A state is kept as a copy, so a later change to the editor does not reach back into it. Each
 * change the editor commits records one step: a drag ends in one write, so it undoes in one go, and
 * a key press, a field and a button are a step each. A new change after an undo drops what could
 * have been redone, as every editor does.
 *
 * Nothing here touches the DOM.
 */

/** How many steps undo goes back unless the editor is given another number. */
export const DEFAULT_HISTORY_LIMIT = 50;

const copy = (state) => JSON.parse(JSON.stringify(state));

/**
 * A history of at most `limit` steps back. A limit that is no positive number is the default, and
 * a fraction is cut to a whole step.
 *
 * @param {number} [limit]
 */
export function createHistory(limit = DEFAULT_HISTORY_LIMIT) {
    const number = Math.floor(Number(limit));
    const steps = Number.isFinite(number) && number >= 1 ? number : DEFAULT_HISTORY_LIMIT;
    const back = [];
    const forward = [];

    return {
        /** The limit this history keeps to. */
        limit: steps,

        /** A change happened, and `before` is the state it changed. The oldest step falls off past the limit. */
        record(before) {
            back.push(copy(before));

            if (back.length > steps) {
                back.shift();
            }

            forward.length = 0;
        },

        /** The state to go back to from `current`, which redo then returns to; null with nothing to undo. */
        undo(current) {
            if (back.length === 0) {
                return null;
            }

            forward.push(copy(current));

            return back.pop();
        },

        /** The state to go forward to from `current`; null with nothing to redo. */
        redo(current) {
            if (forward.length === 0) {
                return null;
            }

            back.push(copy(current));

            return forward.pop();
        },

        canUndo() {
            return back.length > 0;
        },

        canRedo() {
            return forward.length > 0;
        },

        /** Forget every step, as a new image or a value from the server starts afresh. */
        clear() {
            back.length = 0;
            forward.length = 0;
        },
    };
}
