/**
 * WireKit OTP Input Alpine Component.
 *
 * Manages a group of single-character inputs for one-time codes.
 * Features: auto-advance on input, backspace to previous, paste support.
 *
 * @param {Object} config
 * @param {number} config.length - Number of digits
 * @param {string} config.name - Hidden input name for form submission
 * @param {boolean} config.masked - Whether to mask input as password
 */
export default function wirekitOtpInput(config = {}) {
    return {
        _length: config.length || 6,
        _name: config.name || 'otp',

        /**
         * Handle input: accept single digit, advance to next field.
         */
        onInput(event, index) {
            const value = event.target.value;

            // Only allow single digits
            if (!/^\d?$/.test(value)) {
                event.target.value = '';
                return;
            }

            // Auto-advance to next input
            if (value && index < this._length - 1) {
                this.$refs['digit' + (index + 1)]?.focus();
            }

            this._syncHiddenInput();
        },

        /**
         * Handle keydown: backspace moves to previous, arrows navigate.
         */
        onKeydown(event, index) {
            if (event.key === 'Backspace') {
                if (!event.target.value && index > 0) {
                    // Empty field + backspace: move to previous and clear it
                    const prev = this.$refs['digit' + (index - 1)];
                    if (prev) {
                        prev.value = '';
                        prev.focus();
                    }
                } else {
                    event.target.value = '';
                }
                this._syncHiddenInput();
            } else if (event.key === 'ArrowLeft' && index > 0) {
                this.$refs['digit' + (index - 1)]?.focus();
            } else if (event.key === 'ArrowRight' && index < this._length - 1) {
                this.$refs['digit' + (index + 1)]?.focus();
            }
        },

        /**
         * Handle paste: distribute characters across all inputs.
         */
        onPaste(event) {
            event.preventDefault();
            const pasted = (event.clipboardData?.getData('text') || '').replace(/\D/g, '');

            for (let i = 0; i < this._length; i++) {
                const ref = this.$refs['digit' + i];
                if (ref) {
                    ref.value = pasted[i] || '';
                }
            }

            // Focus the first empty field, or last field
            const firstEmpty = Array.from({ length: this._length })
                .findIndex((_, i) => !this.$refs['digit' + i]?.value);
            const focusIndex = firstEmpty >= 0 ? firstEmpty : this._length - 1;
            this.$refs['digit' + focusIndex]?.focus();

            this._syncHiddenInput();
        },

        /**
         * Combine all digit values and update the hidden input.
         */
        _syncHiddenInput() {
            const combined = Array.from({ length: this._length })
                .map((_, i) => this.$refs['digit' + i]?.value || '')
                .join('');

            const hidden = this.$el.parentElement?.querySelector(`input[name="${this._name}"]`);
            if (hidden) {
                hidden.value = combined;
                hidden.dispatchEvent(new Event('input', { bubbles: true }));
            }
        },
    };
}
