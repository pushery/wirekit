/**
 * Two siblings of the same overlay must not stand open at once.
 *
 * ## What it is for
 *
 * Without it, two `<x-wirekit::combobox>` instances on one page can both stand
 * open with their panels overlapping: opening the second does not close the
 * first, because nothing tells it to. One channel per kind of overlay keeps
 * that in one place rather than a copy of the same lines in every component,
 * each drifting a little.
 *
 * ## What it does not do
 *
 * The channel is a PARAMETER, so opening a dropdown does not close a popover,
 * deliberately: making every overlay close every other is a product decision
 * about how the interface feels, not a defect fix. Siblings of the same
 * component are what would overlap, and siblings are what this closes.
 *
 * ## A nested instance is not a sibling
 *
 * An overlay can stand in the panel of another of its kind: a popover with a form that holds a
 * second popover. Opening the inner one must not close the one it was opened from, or its trigger
 * goes with the outer panel. So an instance may pass its root element, the announcement carries
 * it, and an instance does not close for a sender that stands in it, a teleport counting where its
 * template is written (utils/teleport.js).
 *
 * ## Why a uid rather than comparing elements
 *
 * The announcement travels on `window`, so the sender receives its own event.
 * Comparing the event's source against the listener's own id is what keeps an
 * overlay from closing itself the moment it opens — and an id survives the
 * teleport that moves several of these panels out of their own root, which an
 * element comparison would not.
 */

import { belongsThroughTeleports } from './teleport.js';

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
 * @param  {Element|null} [options.root]  the instance's root; a sender inside it is not a sibling
 * @return {{uid: string, announce: Function, stop: Function}}
 */
export function coordinateOverlay({ channel, onOther, root = null }) {
    if (typeof channel !== 'string' || channel === '') {
        // Thrown rather than tolerated: a coordination that listens to nothing
        // looks installed and is silent, which is indistinguishable from having
        // none.
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

        const sender = event.detail.root;

        if (sender && root && typeof root.contains === 'function' && belongsThroughTeleports(sender, root)) {
            return;
        }

        onOther();
    };

    window.addEventListener(channel, handler);

    return {
        uid,

        /** Say "I am open" so every sibling on this channel closes. */
        announce() {
            window.dispatchEvent(new CustomEvent(channel, { detail: { source: uid, root } }));
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
