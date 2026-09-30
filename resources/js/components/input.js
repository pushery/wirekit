/**
 * Input — the trailing affordances: clear the field, copy its value.
 *
 * Only a `clearable` / `copyable` input mounts this. A plain input carries no
 * Alpine at all, and must keep rendering byte-identically.
 *
 * The behavior lived in an inline `x-data` and could not stay there: the object
 * declared methods, and method shorthand is not in the grammar Alpine's CSP
 * build parses. Under a strict Content-Security-Policy the X and the copy
 * button rendered and did nothing — the field kept its text, the clipboard kept
 * its old contents, and nothing said why.
 *
 * The island holds the buttons only. It sits in the field's frame beside the
 * field, never around it: an `x-ref` registers on the nearest `x-data` root, so
 * a root around the field would take the ref a caller puts on it, and the
 * caller's `$refs` would never see it. The island therefore reaches the field
 * through the frame, where the field is the one `<input>` among its direct
 * children; leading and trailing content sits in spans of its own.
 *
 * The field is read rather than mirrored into state: it is a real <input> that
 * a developer's `wire:model` / `x-model` also writes to, so the element is the
 * only honest source of its own value.
 *
 * Lifecycle resources held on `this`:
 *   - _frame + _onInput (an `input` listener on the frame) — removed in
 *     destroy(). The frame is not the island's own element, so nothing takes
 *     the listener away with the island, and one left behind writes to a
 *     component that no longer exists.
 *   - _copiedTimer (setTimeout) — cleared in destroy(). A 2 s timer that fires
 *     after teardown writes to a component that no longer exists.
 *   - _unwatch (the unwatch function of `$wire.$watch`) — called in destroy().
 *     Livewire also releases it when its component goes, but an island can go
 *     before its component does.
 */
export default function wirekitInput() {
    return {
        copied: false,
        hasValue: false,
        _copiedTimer: null,
        _frame: null,
        _onInput: null,
        _unwatch: null,

        init() {
            // Read here, where `$el` is the island itself. In a method a button calls,
            // `$el` is that button, and the clear button sits inside a tooltip root.
            this._frame = this.$el?.parentElement ?? null;

            if (this._frame) {
                // The frame hears the field's own typing, which bubbles up to it.
                this._onInput = () => this.syncHasValue();
                this._frame.addEventListener('input', this._onInput);
            }

            this.syncHasValue();
            this._followBoundModel();
        },

        destroy() {
            if (this._frame && this._onInput) {
                this._frame.removeEventListener('input', this._onInput);
            }

            this._frame = null;
            this._onInput = null;

            if (this._unwatch) {
                this._unwatch();
                this._unwatch = null;
            }

            if (this._copiedTimer) {
                clearTimeout(this._copiedTimer);
                this._copiedTimer = null;
            }
        },

        /** The field this island serves: the `<input>` directly in its frame, or null. */
        _field() {
            return this._frame?.querySelector(':scope > input') ?? null;
        },

        /**
         * The Livewire property the field is bound to, read from its own `wire:model`
         * attribute whatever its modifiers: `wire:model.live.debounce.300ms="scan"` binds
         * `scan`. Null when the field carries none.
         */
        _boundModel() {
            for (const attribute of this._field()?.attributes ?? []) {
                if (attribute.name.startsWith('wire:model') && attribute.value !== '') {
                    return attribute.value;
                }
            }

            return null;
        },

        /**
         * Follow the bound property, so the X follows a value the server writes.
         *
         * Livewire writes a new value of the property into the field without an `input`
         * event, so a server that empties the field after a scan left the X standing over an
         * empty field until the next keystroke. `$wire.$watch` reports every change of the
         * property, and the field is read one tick later, once the binding has written it,
         * so the element stays the only source of its own value. Outside a Livewire component
         * `$wire.$watch` returns nothing, which is why the result is checked.
         */
        _followBoundModel() {
            const model = this._boundModel();

            if (model === null || ! this.$wire || typeof this.$wire.$watch !== 'function') {
                return;
            }

            const unwatch = this.$wire.$watch(model, () => this.$nextTick(() => this.syncHasValue()));

            if (typeof unwatch === 'function') {
                this._unwatch = unwatch;
            }
        },

        /**
         * Track whether the field has content, so the X only appears when there
         * is something to clear.
         *
         * Called by the frame's `input` listener, which the field's own typing
         * bubbles up to, and after the server changes the bound property — which
         * is why it reads the element instead of a copy the component would then
         * have to keep in sync.
         */
        syncHasValue() {
            const field = this._field();

            this.hasValue = !! field && field.value.length > 0;
        },

        /**
         * Empty the field and hand focus back to it.
         *
         * The first two dispatches are not decoration: assigning `.value` fires no
         * event, so without them a `wire:model` / `x-model` binding keeps the
         * text the reader just cleared.
         *
         * The third is for a caller whose X means more than the text, a search that
         * also drops the record it found: `wirekit:input-cleared` fires once per
         * clear, after the model events, and bubbles from the field, so
         * `x-on:wirekit:input-cleared` on the component hears it. A deferred
         * `wire:model` sends nothing until the next request, and this is the moment
         * the caller can act on instead.
         */
        clear() {
            const field = this._field();

            if (! field) {
                return;
            }

            field.value = '';
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
            field.dispatchEvent(new CustomEvent('wirekit:input-cleared', {
                bubbles: true,
                detail: { name: field.name || null },
            }));
            this.hasValue = false;
            field.focus();
        },

        /**
         * Copy the live value, and say so for a moment.
         *
         * `navigator.clipboard` is unavailable on a non-secure origin — plain
         * http, which is where a good deal of local development happens — so the
         * select + execCommand path stays as the fallback. A denied write is
         * swallowed: the reader simply never sees "Copied", which is the honest
         * outcome of a copy that did not happen.
         */
        copy() {
            const field = this._field();

            if (! field) {
                return;
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(field.value)
                    .then(() => this._markCopied())
                    .catch(() => {});

                return;
            }

            try {
                field.select();
                document.execCommand('copy');
                this._markCopied();
            } catch {
                // Neither path available — nothing to report beyond the absent
                // "Copied".
            }
        },

        /** Show the copied state, and take it back after a moment. */
        _markCopied() {
            this.copied = true;
            clearTimeout(this._copiedTimer);
            this._copiedTimer = setTimeout(() => {
                this.copied = false;
            }, 2000);
        },
    };
}
