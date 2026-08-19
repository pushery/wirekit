/**
 * Two siblings of the same overlay must not stand open at once.
 *
 * ## The defect this is for
 *
 * Two `<x-wirekit::combobox>` instances on one page both opened, and their
 * panels overlapped: opening the second did not close the first, because
 * nothing told it to. `context-menu` had already solved it, and the fix was
 * copied to `combobox` — which is where a third copy would normally follow,
 * then a fourth, each drifting a little.
 *
 * Measured before this existed: `dropdown`, `menubar`, `navigation-menu`,
 * `hover-card` and `popover` had **zero** cross-close hooks between them. Five
 * components, one bug, five places to write the same twelve lines.
 *
 * ## What it does not do
 *
 * The channel is a PARAMETER, so opening a dropdown does not close a popover.
 * That is today's behavior kept deliberately: making every overlay close every
 * other is a product decision about how the interface feels, not a defect fix,
 * and it would arrive silently in a patch release. Siblings of the same
 * component are what overlapped, and siblings are what this closes.
 *
 * ## Why a uid rather than comparing elements
 *
 * The announcement travels on `window`, so the sender receives its own event.
 * Comparing the event's source against the listener's own id is what keeps an
 * overlay from closing itself the moment it opens — and an id survives the
 * teleport that moves several of these panels out of their own root, which an
 * element comparison would not.
 */

/**
 * Process-wide counter. Two instances built in the same tick must not share an
 * id, or each would read the other's announcement as its own and ignore it —
 * the exact silence this module exists to break.
 */
let sequence = 0;

/**
 * Join a cross-close channel.
 *
 * @param  {Object}   options
 * @param  {string}   options.channel  event name, e.g. `wirekit:dropdown-open`
 * @param  {Function} options.onOther  called when ANOTHER instance announces
 * @return {{uid: string, announce: Function, stop: Function}}
 */
export function coordinateOverlay({ channel, onOther }) {
    if (typeof channel !== 'string' || channel === '') {
        // Thrown rather than tolerated: a coordination that listens to nothing
        // looks installed and is silent, which is indistinguishable from the
        // bug it was added to fix.
        throw new TypeError('coordinateOverlay: `channel` must be a non-empty event name.');
    }

    if (typeof onOther !== 'function') {
        throw new TypeError('coordinateOverlay: `onOther` must be a function.');
    }

    sequence += 1;
    const uid = `${channel}#${sequence}`;

    const handler = (event) => {
        // No detail at all means somebody else's event of the same name — a
        // developer's own dispatch, say. Closing on it would make this
        // component react to a message it was never sent.
        if (! event || ! event.detail || event.detail.source === uid) {
            return;
        }

        onOther();
    };

    window.addEventListener(channel, handler);

    return {
        uid,

        /** Say "I am open" so every sibling on this channel closes. */
        announce() {
            window.dispatchEvent(new CustomEvent(channel, { detail: { source: uid } }));
        },

        /**
         * Leave the channel. Required in `destroy()`: a listener that outlives
         * its component holds the whole scope alive and writes into it on every
         * sibling's open.
         */
        stop() {
            window.removeEventListener(channel, handler);
        },
    };
}

export default coordinateOverlay;
