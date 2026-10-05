/**
 * Let a component that sends through a hidden field stop an empty submit when it is required.
 *
 * The browser validates no hidden field, so `required` on one does nothing. The component renders a
 * stand-in beside its control (`partials/required-check`): a text field with no name, so it is never
 * sent, carrying `required`, whose value is empty exactly while the component has none. It takes
 * part in `checkValidity()`, `reportValidity()`, `requestSubmit()` and `:invalid` like any field,
 * so the form is not sent while it is empty.
 *
 * The browser would point its own message at the stand-in, which nobody sees, so `invalid` is taken
 * over here: the message the browser wrote, already in the reader's language, is shown in the
 * component, and the first invalid field of the form takes the focus, as the browser gives it to
 * the first. For the component that is its control.
 *
 * Spread into a factory, which supplies two members of its own:
 *   - `requiredValue` — a getter, empty exactly while the component has no value; the stand-in's
 *     value and the message's visibility read it.
 *   - `_focusRequiredControl()` — puts the focus on the control a reader fills in.
 *
 * Methods and a plain value only: spreading an object copies a getter's value at spread time.
 */
export function requiredCheckState() {
    return {
        // The browser's message for the stand-in, set when a submit or a validity check found the
        // component empty. The view shows it while the component is still empty.
        requiredMessage: '',

        /**
         * The stand-in's `invalid` event: no bubble, the browser's message in the component, and the
         * focus on the control when this is the first invalid field of its form.
         *
         * @param {Event} event
         */
        onRequiredInvalid(event) {
            const check = event?.target;

            if (! check || typeof event.preventDefault !== 'function') {
                return;
            }

            event.preventDefault();
            this.requiredMessage = typeof check.validationMessage === 'string' ? check.validationMessage : '';

            const form = check.form;
            const first = form && form.elements
                ? Array.from(form.elements).find((el) => el.willValidate && el.validity && ! el.validity.valid)
                : check;

            if (first === check && typeof this._focusRequiredControl === 'function') {
                this._focusRequiredControl();
            }
        },
    };
}
