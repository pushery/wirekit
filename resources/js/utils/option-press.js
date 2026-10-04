/**
 * A press on a list whose options cannot take the focus, shared by `combobox` and `multi-select`.
 *
 * The options of an editable combobox are rows with no tab stop: the focus stays in the text
 * field, and `aria-activedescendant` says which row is active. A press on a row takes the focus
 * off the field all the same, because a press on anything that cannot hold the focus hands it to
 * the page, and the next Tab then starts wherever the browser restarts from, which in Blink is
 * the top of the document (WCAG 2.4.3).
 *
 * So the press of a mouse or a pen is kept from moving the focus. A finger is left alone: with
 * the focus still in the field, the on-screen keyboard would stay open over the choice just made.
 *
 * `mousedown` is the event whose default action moves the focus, and it does not say what
 * pressed, so the pointer type is taken from the `pointerdown` that precedes it.
 *
 * @returns {{ _pressedBy: string, notePress: (event: PointerEvent) => void, keepFocusOnPress: (event: MouseEvent) => void }}
 */
export function optionPressState() {
    return {
        // What made the last press on the list. A mouse until a pointer says otherwise, so a
        // `mousedown` that no `pointerdown` came before keeps the focus where it is.
        _pressedBy: 'mouse',

        /**
         * Remember what pressed: `mouse`, `pen` or `touch`.
         *
         * @param {PointerEvent} event
         */
        notePress(event) {
            this._pressedBy = event?.pointerType || 'mouse';
        },

        /**
         * Keep the focus in the field through a press that a finger did not make.
         *
         * A press on the list's own box is left to the browser: that is where its scrollbar is,
         * and a scrollbar whose press is canceled no longer drags. Bound on the list, so the
         * rows and everything inside them are what this covers.
         *
         * @param {MouseEvent} event
         */
        keepFocusOnPress(event) {
            if (! event || event.target === event.currentTarget) {
                return;
            }

            if (this._pressedBy !== 'touch') {
                event.preventDefault?.();
            }
        },
    };
}
