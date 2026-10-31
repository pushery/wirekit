/**
 * Two taps on one element, as a touch screen and a mouse send them (`x-wk-double-tap`).
 *
 * The expression runs once for two clicks on the element that land within `DOUBLE_TAP_MS` of each
 * other, with `$event` the second click. It is read from the clicks rather than from `dblclick`,
 * which a touch screen does not send in every engine, so a phone, a tablet and a mouse answer the
 * same way; a third click starts a new pair. A double tap is a shortcut a screen reader cannot
 * make, since it activates an element with one click, so whatever it opens needs a second way in
 * that the keyboard reaches. Where it stands, `touch-action: manipulation` keeps a phone from
 * zooming on the second tap, and `user-select: none` keeps a double click from marking the text.
 */

/** The longest gap between the two clicks of a double tap, in milliseconds. */
export const DOUBLE_TAP_MS = 400;

/**
 * Whether a click completes a double tap: it lands within the window of the click before it.
 *
 * @param {number|null} last  When the click before landed, or null where none is waiting.
 * @param {number} now  When this click landed, on the same clock.
 * @returns {boolean}
 */
export function completesDoubleTap(last, now) {
    return last !== null && now >= last && now - last < DOUBLE_TAP_MS;
}

/**
 * Register the `x-wk-double-tap` directive. Idempotent: a second bundle registers the same
 * handler.
 *
 * @param {object} Alpine
 */
export function registerDoubleTapDirective(Alpine) {
    Alpine.directive('wk-double-tap', (el, { expression }, { evaluateLater, cleanup }) => {
        const run = evaluateLater(expression);
        let last = null;

        const onClick = (event) => {
            if (completesDoubleTap(last, event.timeStamp)) {
                last = null;
                run(() => {}, { scope: { $event: event }, params: [event] });

                return;
            }

            last = event.timeStamp;
        };

        el.addEventListener('click', onClick);

        cleanup(() => {
            el.removeEventListener('click', onClick);
        });
    });
}
