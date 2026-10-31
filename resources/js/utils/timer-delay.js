/**
 * The delay a timer waits, held to what a timer can wait.
 *
 * `setTimeout` and `setInterval` read their delay as a 32-bit signed integer, so in a browser a
 * delay from 2^31 ms on, about 24.9 days, and `Infinity` wait no time at all: a toast meant to
 * stay would close at once, and a form's guard against a second submit would hold for nothing.
 * A delay that comes from the configuration is read through here before it reaches a timer.
 */

/** The longest delay a timer waits as given: 2^31 - 1 milliseconds. */
export const MAX_TIMER_DELAY = 2147483647;

/**
 * A delay for `setTimeout`: one beyond the longest wait is that wait, a negative one is none,
 * and a value that is no number is `fallback`.
 *
 * @param {unknown} ms
 * @param {number} [fallback=0]
 * @returns {number}
 */
export function timerDelay(ms, fallback = 0) {
    const delay = Number(ms);

    if (Number.isNaN(delay)) {
        return fallback;
    }

    return Math.min(Math.max(delay, 0), MAX_TIMER_DELAY);
}

/**
 * A delay for `setInterval`, as timerDelay() reads it, except that one of zero or below is
 * `fallback`: an interval without a delay runs as often as the browser lets it.
 *
 * @param {unknown} ms
 * @param {number} fallback
 * @returns {number}
 */
export function intervalDelay(ms, fallback) {
    const delay = timerDelay(ms, fallback);

    return delay > 0 ? delay : fallback;
}
