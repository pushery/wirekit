/**
 * Key presses that belong to an input method's composition, kept away from a field's own keys.
 *
 * A reader writing Japanese, Chinese or Korean types into an input method editor, which converts
 * what they type: Enter confirms the conversion, the arrow keys pick a candidate and Escape
 * abandons it. Those presses still reach the page as `keydown` events, and Alpine's key
 * modifiers read `event.key` alone, so an `@keydown.enter` on a search field or a tag field fired
 * on the Enter that confirmed a conversion: a tags input took the half-converted text as a tag,
 * and a combobox picked the highlighted option while the reader was still writing.
 *
 * Chrome and Firefox mark such a press with `isComposing`. Safari has already ended the
 * composition when its `keydown` arrives and reports the press with `keyCode` 229, the value the
 * engines use for a key the input method is processing.
 */

/**
 * Whether a key press belongs to an input method's composition.
 *
 * @param {KeyboardEvent|null|undefined} event
 * @returns {boolean}
 */
export function isComposing(event) {
    return Boolean(event?.isComposing) || event?.keyCode === 229;
}

/**
 * The listener `x-wk-ime` puts on a field: a composition's key press stops at the field.
 *
 * `stopImmediatePropagation()` keeps it from the field's own listeners and from any on an
 * ancestor or the window, where an Escape that abandoned a conversion would otherwise close the
 * dialog around the field. The press is not canceled: the input method has already acted on it.
 *
 * @param {KeyboardEvent} event
 */
export function stopCompositionKey(event) {
    if (isComposing(event)) {
        event.stopImmediatePropagation();
    }
}

/**
 * Register `x-wk-ime`. It goes on a text field whose component listens for keys on it.
 *
 * The listener is a capture listener on the field itself, and at the field every capture listener
 * runs before every other listener registered there, whatever order they were added in; Alpine's
 * `@keydown` is one of the latter. A listener in the capture phase of an ANCESTOR still runs first,
 * so a component that hears keys at the window in that phase checks `isComposing()` itself.
 *
 * Idempotent: a second bundle registers the same handler.
 *
 * @param {object} Alpine
 */
export function registerImeDirective(Alpine) {
    Alpine.directive('wk-ime', (el, directive, { cleanup }) => {
        el.addEventListener('keydown', stopCompositionKey, { capture: true });
        cleanup(() => el.removeEventListener('keydown', stopCompositionKey, { capture: true }));
    });
}
