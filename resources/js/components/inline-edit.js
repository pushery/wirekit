import { prefersReducedMotion } from '../utils/motion.js';

/**
 * WireKit Inline Edit Alpine Component.
 *
 * A read mode that turns into an editor in place, and back. The library does
 * NOT own the saving: the developer's Livewire component does, and this emits
 * the event it acts on. That split is why the state machine below has a
 * `saving` state it cannot leave on its own.
 *
 * CLEANUP CONTRACT
 * ---------------------------------------------------------------------------
 * Resources held: `_previous` (a value snapshot, no listener), `_exclusiveHandler`
 * (window listener for the exclusive-open protocol), `_saveTimeout` (the
 * no-answer fallback), `_pointerDownAt` (drag-threshold bookkeeping).
 * Every one of them is released in destroy(); every callback that touches one
 * null-guards first, because a Livewire morph can tear the host down while a
 * save is still in flight.
 *
 * @param {Object} config
 * @param {string} config.name            Field name, carried in the event detail
 * @param {string} config.value           Initial value
 * @param {boolean} config.exclusive      Close other open editors when this opens
 * @param {number} config.saveTimeout     ms to wait for a save answer before giving up
 */
export default function wirekitInlineEdit(config = {}) {
    return {
        editing: false,
        saving: false,
        draft: '',

        /**
         * The value to restore on cancel. A snapshot rather than a live
         * binding: with an explicit commit there has to be something to go
         * back TO, and a two-way binding would have already overwritten it.
         */
        _previous: '',
        _exclusiveHandler: null,
        _beforeUnload: null,
        _saveTimeout: null,
        _pointerDownAt: null,

        /**
         * Snapshot a value so cancel has something to restore.
         *
         * A COPY for an array, and that is not defensive tidiness: a flat
         * assignment points at the same array, so every edit mutates the
         * "previous" value too and cancel restores what the user just typed.
         * The slot path makes this reachable — a tags-input holds an array.
         */
        _snapshot(value) {
            return Array.isArray(value) ? [...value] : value;
        },

        init() {
            this.saving = config.loading === true;
            this.draft = Array.isArray(config.value) ? [...config.value] : String(config.value ?? '');
            this._previous = this._snapshot(this.draft);

            // Open when the server rendered an error for this field.
            //
            // The pessimistic-close rule keeps the editor open across a failed
            // save — but only while this component instance lives, and a
            // validation failure is exactly the case where it may not. The
            // failure re-renders the field WITH an error message, which changes
            // the subtree enough that Livewire replaces it rather than patching
            // it, and Alpine then runs this init on a fresh instance whose
            // `editing` is false. The reader is thrown back to read mode looking
            // at their old value, with an error message about an edit they can
            // no longer see — the precise outcome the rule exists to prevent.
            //
            // Deriving the state from the error instead of only remembering it
            // is what survives that, and it is also just true: a field carrying
            // a validation error is a field the reader still has to fix.
            if (config.hasError === true) {
                this.editing = true;
            }

            // A user can leave ten editors open and carry ten unsaved drafts,
            // which a wire:navigate then takes with it. The house pattern for
            // this is the combobox's: announce on window, let the others close.
            if (config.exclusive !== false) {
                // The component ROOT, captured once. init() runs on the x-data
                // element, so `this.$el` is the root HERE — but not everywhere.
                //
                // That difference made this component impossible to open at all.
                // `open()` is invoked from the trigger's x-on:click, so Alpine
                // resolves `$el` to the BUTTON there, while this handler runs
                // from a window event, where it resolves to the root. Comparing
                // the two never matched, so the instance that had just set
                // `editing = true` canceled ITSELF, synchronously, inside its
                // own open(). Nothing threw, nothing logged, the button just
                // looked dead — and a single component on an empty page did it
                // too, so it never read as a multi-instance problem.
                const root = this.$el;

                this._exclusiveHandler = (event) => {
                    if (!this._exclusiveHandler) return;      // torn down mid-flight
                    if (event.detail?.source === root) return;
                    // The trigger lives inside the root, and so does any control
                    // a developer supplies through a slot — anything dispatching
                    // from within this component is still this component.
                    if (root.contains(event.detail?.source)) return;
                    if (this.editing && !this.saving) this.cancel();
                };
                window.addEventListener('wirekit:inline-edit-opened', this._exclusiveHandler);
            }

            // A draft lives only in this component, so anything that replaces the
            // page takes it with it — a wire:navigate, a link, a closed tab. The
            // browser's own prompt is the only thing that can interrupt those,
            // and it only appears when there is genuinely something to lose.
            this._beforeUnload = (event) => {
                if (!this._beforeUnload) return;
                if (!this.editing || this.draft === this._previous) return;
                event.preventDefault();
                // Assigning returnValue is what still triggers the prompt in
                // several browsers; the string itself is never shown any more.
                event.returnValue = '';
            };
            window.addEventListener('beforeunload', this._beforeUnload);
        },

        destroy() {
            if (this._exclusiveHandler) {
                window.removeEventListener('wirekit:inline-edit-opened', this._exclusiveHandler);
                this._exclusiveHandler = null;
            }
            if (this._beforeUnload) {
                window.removeEventListener('beforeunload', this._beforeUnload);
                this._beforeUnload = null;
            }
            if (this._saveTimeout) {
                clearTimeout(this._saveTimeout);
                this._saveTimeout = null;
            }
            this._pointerDownAt = null;
        },

        // ── Opening ─────────────────────────────────────────────────────────

        open() {
            if (this.editing || this.saving) return;
            this._previous = this._snapshot(this.draft);
            this.editing = true;

            // `$root`, not `$el`: this method is invoked from the trigger's
            // x-on:click, where `$el` is the BUTTON. Listeners identify the
            // sender by comparing against a component root, so sending the
            // button meant nobody could recognize it — including this component,
            // which then canceled itself. `$root` is the same element in every
            // calling context, which is the property this identity needs.
            window.dispatchEvent(new CustomEvent('wirekit:inline-edit-opened', {
                detail: { source: this.$root, name: config.name },
            }));

            this.$nextTick(() => {
                const control = this.$refs.control;

                // The documented contract for a slot-supplied control, checked
                // rather than hoped for. Without the ref the focus choreography
                // has nothing to aim at and simply does nothing — a silent
                // failure, and silent is what makes it expensive. One library
                // detects a brought-your-own control against a hand-kept list of
                // twelve component names; a control whose name is missing or
                // minified there gets the wrong treatment and never saves. That
                // failure class is not worth importing.
                if (!control) {
                    if (config.debug && config.hasSlotEditor) {
                        console.warn(
                            '[WireKit] inline-edit: the editor slot has no element with x-ref="control", '
                            + 'so focus cannot be placed and the control cannot be read. '
                            + 'See docs.wirekit.app/components/inline-edit#bringing-your-own-control'
                        );
                    }

                    return;
                }

                // Blade cannot add attributes to slot content, so the wiring the
                // built-in path gets declaratively is applied here instead —
                // same attributes, one place, so the two paths cannot drift.
                if (config.describedBy && !control.getAttribute('aria-describedby')) {
                    control.setAttribute('aria-describedby', config.describedBy);
                }

                control.focus();
                // Select rather than place a caret: the common intent is to
                // replace a short value, and a caret at position 0 makes the
                // user clear it by hand first.
                control.select?.();
            });
        },

        /**
         * Open from a click on the VALUE rather than the button.
         *
         * Two exceptions, both of which show up the moment a real page uses
         * this, and both taken from what a mature implementation does:
         *
         *  - a drag is a text SELECTION, not a request to edit. Without a
         *    threshold, every attempt to select the value opens the editor and
         *    throws the selection away.
         *  - a link inside the value belongs to the link. Opening the editor
         *    would make the link unreachable by mouse entirely.
         */
        onValuePointerDown(event) {
            this._pointerDownAt = { x: event.clientX, y: event.clientY };
        },

        onValueClick(event) {
            const start = this._pointerDownAt;
            this._pointerDownAt = null;

            if (event.target.closest?.('a[href]')) return;

            if (start) {
                const moved = Math.abs(event.clientX - start.x) + Math.abs(event.clientY - start.y);
                if (moved > 5) return;
            }

            this.open();
        },

        // ── Leaving ─────────────────────────────────────────────────────────

        cancel() {
            if (this.saving) return;
            this.draft = this._snapshot(this._previous);
            this.editing = false;
            this._focusTrigger();
        },

        /**
         * Commit. The editor stays OPEN until the save is confirmed, because
         * the end of a round trip is not evidence of success — a validation
         * failure ends one too, and closing there would discard the message
         * the developer just rendered.
         */
        commit() {
            // A second Enter while saving must not send a second time. Some
            // implementations leave exactly this gap, and a double submit on a
            // form that charges money is not a cosmetic defect.
            if (this.saving || !this.editing) return;

            if (this.draft === this._previous) {
                this.editing = false;
                this._focusTrigger();

                return;
            }

            this.saving = true;

            this.$dispatch('wirekit:inline-edit-confirmed', {
                name: config.name,
                value: this.draft,
                previous: this._previous,
            });

            // Nothing guarantees an answer: the developer may not have wired
            // the paired event, or the request may die. Rather than leave the
            // editor busy forever, give up after a bound and say the state is
            // unknown instead of claiming success.
            const wait = Number(config.saveTimeout ?? 10000);
            if (wait > 0) {
                this._saveTimeout = setTimeout(() => {
                    if (!this._saveTimeout) return;
                    this._saveTimeout = null;
                    this.saving = false;
                    this.$refs.status && (this.$refs.status.textContent = config.unknownMessage ?? '');
                }, wait);
            }
        },

        /** The developer reports the outcome; it is never inferred. */
        onSaved(event) {
            if (event?.detail?.name && event.detail.name !== config.name) return;
            if (this._saveTimeout) {
                clearTimeout(this._saveTimeout);
                this._saveTimeout = null;
            }
            this.saving = false;
            this.editing = false;
            this._previous = this._snapshot(this.draft);
            this._focusTrigger();
        },

        onFailed(event) {
            if (event?.detail?.name && event.detail.name !== config.name) return;
            if (this._saveTimeout) {
                clearTimeout(this._saveTimeout);
                this._saveTimeout = null;
            }
            this.saving = false;
            // Editor stays open and focus does NOT move: the error is here, and
            // pulling the user away from it would hide what they must fix.
        },

        // ── Keyboard ────────────────────────────────────────────────────────

        onKeydown(event) {
            if (event.key === 'Escape') {
                // .stop matters: an enclosing modal or drawer would otherwise
                // close on the same Escape, and the user loses the whole dialog
                // when they meant to abandon one field.
                event.stopPropagation();
                this.cancel();

                return;
            }

            if (event.key === 'Enter') {
                // Cmd/Ctrl+Enter commits in EVERY control type, which is what
                // makes the textarea rule below survivable: there is always one
                // keyboard path to save, whatever Enter itself does.
                if (event.metaKey || event.ctrlKey) {
                    event.preventDefault();
                    event.stopPropagation();
                    this.commit();

                    return;
                }

                // In a textarea Enter writes a line. Claiming it would take away
                // the one thing a multi-line control is for, so it is left alone
                // — and that is exactly why the view refuses to hide the action
                // buttons for this control.
                if (config.control === 'textarea') return;

                // Everywhere else Enter commits, and stops. Inside a
                // <form wire:submit> a bare Enter would otherwise submit the
                // whole form instead of committing this one field.
                event.preventDefault();
                event.stopPropagation();
                this.commit();
            }

            // ArrowUp / ArrowDown are NOT handled here on purpose. On a number
            // control they belong to the spinbutton, and on a select they are
            // how a keyboard user moves through the options. Intercepting them
            // would break the control's own keyboard model.
        },

        /**
         * Leaving the control without committing does NOT move focus. The user
         * was on their way somewhere; pulling them back to the trigger would
         * undo the navigation they just performed.
         */
        onBlur() {
            if (!this.editing || this.saving) return;
            if (config.commitOn === 'blur' && config.control !== 'select') {
                this.commit();

                return;
            }
            this.draft = this._snapshot(this._previous);
            this.editing = false;
        },

        _focusTrigger() {
            this.$nextTick(() => this.$refs.trigger?.focus());
        },

        /** Used by the view to skip the mode-change transition. */
        get motionReduced() {
            return prefersReducedMotion();
        },
    };
}
