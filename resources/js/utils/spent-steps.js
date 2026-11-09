/**
 * The step controls beside a splitter's grip that a click would not move.
 *
 * Each of the two controls moves the line toward one bound. Once the value stands on that bound
 * a click on it does nothing, so the control is taken away, and it comes back as soon as the value
 * leaves the bound. The other control stays: it still moves the line.
 *
 * The controls are named by where they are drawn, `-1` before the line and `1` after it, and
 * `grows` names the one that makes the value larger. That is `1`, except where the row runs the
 * other way: a horizontal split on a right-to-left page, a sidebar attached at the right.
 *
 * The resizable handle and the sidebar's width handle share this, so both decide the same way,
 * and the stylesheet hides a control the handle lists in `data-wk-steps-spent`.
 */

/** The attribute on the handle that lists the spent controls. */
export const SPENT_STEPS_ATTRIBUTE = 'data-wk-steps-spent';

/**
 * The spent controls, as the space-separated list the attribute holds: `''` while both still
 * move the line, `'-1 1'` when the range has no room at all.
 *
 * @param {Object} state
 * @param {number} state.current    the value now
 * @param {number} state.lower      the smallest value the handle allows
 * @param {number} state.upper      the largest value the handle allows
 * @param {1|-1} state.grows        the control that makes the value larger
 * @param {number} [state.tolerance] how close to a bound counts as on it, in the value's unit
 * @returns {string}
 */
export function spentSteps({ current, lower, upper, grows, tolerance = 0 }) {
    if (! [current, lower, upper].every(Number.isFinite)) {
        return '';
    }

    const larger = grows === -1 ? -1 : 1;
    const spent = [];

    if (current <= lower + tolerance) {
        spent.push(-larger);
    }

    if (current >= upper - tolerance) {
        spent.push(larger);
    }

    return spent.sort((a, b) => a - b).join(' ');
}

/**
 * Write the list onto the handle, or take the attribute off when the list is empty. Only a change
 * is written: the resizable handle watches its own attributes to put back what a Livewire update
 * takes off, and a write that changes nothing would wake that watch for nothing.
 *
 * @param {?Element} handle
 * @param {string} spent  from `spentSteps()`
 */
export function markSpentSteps(handle, spent) {
    if (! handle || typeof handle.setAttribute !== 'function') {
        return;
    }

    const now = handle.getAttribute?.(SPENT_STEPS_ATTRIBUTE) ?? null;

    if (spent === '') {
        if (now !== null) {
            handle.removeAttribute?.(SPENT_STEPS_ATTRIBUTE);
        }

        return;
    }

    if (now !== spent) {
        handle.setAttribute(SPENT_STEPS_ATTRIBUTE, spent);
    }
}
