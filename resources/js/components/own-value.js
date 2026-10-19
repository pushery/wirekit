/**
 * The switch below an input that follows a derived value (`own` on `<x-wirekit::input>`).
 *
 * Turned on, the field takes a value of the reader's own; turned off, it follows the derived
 * value again and is read-only. The lock follows the switch here, in the browser, so a form
 * without Livewire opens the field at once. With `own-model` the switch also sends its state live,
 * and the server, which derives the value, renders the same lock: both read the one switch.
 */
export default function wirekitOwnValue() {
    return {
        /** Lock the field the switch names, or open it, by the switch's state. */
        follow() {
            const field = document.getElementById(this.$el.getAttribute('data-wk-own-for') ?? '');

            if (field && 'readOnly' in field) {
                field.readOnly = ! this.$el.checked;
            }
        },
    };
}
