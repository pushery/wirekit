/**
 * Whether the name or key an event carries addresses the one a component holds.
 *
 * A component holds what its view handed it: text from an attribute, or the value as PHP held it.
 * An event carries what its sender put in, and Livewire's `dispatch('…', name: $order->id)` sends
 * an integer as a number. A number and the text with the same digits are one name. Any other pair
 * across types is not, and `null`, `undefined` and the empty string address nothing.
 *
 * @param {unknown} sent  the name the event carries
 * @param {unknown} held  the name the component holds
 * @returns {boolean}
 */
export function sameName(sent, held) {
    const name = asName(sent);

    return name !== null && name === asName(held);
}

/**
 * Whether an event names anything at all. One that names nothing addresses every receiver that
 * takes an unaddressed event; one that names something addresses only the receiver of that name.
 *
 * @param {unknown} sent
 * @returns {boolean}
 */
export function namesSomething(sent) {
    return asName(sent) !== null;
}

/** A name as text, or null for a value that names nothing. */
function asName(value) {
    if (typeof value === 'string') {
        return value === '' ? null : value;
    }

    return typeof value === 'number' && Number.isFinite(value) ? String(value) : null;
}
