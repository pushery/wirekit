/**
 * WireKit Toast Alpine Component.
 *
 * Manages a reactive queue of toast notifications dispatched via
 * `$dispatch('wirekit-toast', { ... })`. Supports auto-dismiss,
 * pause-on-hover, and swipe-to-dismiss.
 */
export default function wirekitToast(config = {}) {
    return {
        /** @type {Array<{id: number, title: string, message: string, variant: string, _timer: number|null}>} */
        toasts: [],

        /** Max visible toasts (oldest removed when exceeded) */
        _max: config.max || 5,

        /** Default auto-dismiss duration in ms */
        _duration: config.duration || 5000,

        /** Auto-incrementing toast ID */
        _nextId: 1,

        init() {
            // Scoped event name — when name is set, only this region receives
            // events dispatched to 'wirekit-toast-{name}'. Without a name,
            // the region listens on the global 'wirekit-toast' event.
            this._eventName = config.name
                ? `wirekit-toast-${config.name}`
                : 'wirekit-toast';

            // Optional DOM-containment scope. When set to a CSS selector,
            // only events whose dispatching element matches `closest(scope)`
            // are handled — useful for "per-section toast surfaces" where
            // multiple toast regions share the same event name but each
            // owns a portion of the DOM tree (e.g. an embedded live-preview
            // pattern, or a real app with per-card local toast queues).
            //
            // Falsy / missing → no containment filter, every dispatched
            // event is handled (current behavior, fully back-compat).
            this._scope = typeof config.scope === 'string' && config.scope !== ''
                ? config.scope
                : null;

            this._handler = (event) => {
                // Containment filter: if a scope is set, the dispatching
                // element must be inside an ancestor matching the selector.
                // event.target is the element on which $dispatch was called;
                // closest() walks up to find a match (or returns null).
                if (this._scope) {
                    const target = event.target;
                    if (! target || typeof target.closest !== 'function' || ! target.closest(this._scope)) {
                        return;
                    }
                }
                this.add(event.detail);
            };
            window.addEventListener(this._eventName, this._handler);

            // Livewire hook: bridge session flash toasts on navigate
            this._livewireHandler = () => {
                // Livewire injects flash data as a custom event after navigation
                // This hook will be consumed by the Blade component if needed
            };
        },

        destroy() {
            window.removeEventListener(this._eventName, this._handler);
            // Clear all pending timers
            this.toasts.forEach((t) => {
                if (t._timer) clearTimeout(t._timer);
            });
        },

        /**
         * Add a new toast to the queue.
         * @param {Object} detail - Toast payload
         * @param {string} [detail.title] - Bold heading
         * @param {string} [detail.message] - Body text
         * @param {string} [detail.variant='info'] - info|success|warning|danger
         * @param {number} [detail.duration] - Override auto-dismiss ms (0 = persistent)
         */
        add(detail) {
            const id = this._nextId++;
            const duration = detail.duration !== undefined ? detail.duration : this._duration;

            const toast = {
                id,
                title: detail.title || null,
                message: detail.message || '',
                variant: detail.variant || 'info',
                _timer: null,
                _paused: false,
                _remaining: duration,
                _started: Date.now(),
            };

            // Auto-dismiss after duration (0 = no auto-dismiss)
            if (duration > 0) {
                toast._timer = setTimeout(() => this.remove(id), duration);
            }

            this.toasts.push(toast);

            // Enforce max queue length — remove oldest
            while (this.toasts.length > this._max) {
                const oldest = this.toasts[0];
                if (oldest._timer) clearTimeout(oldest._timer);
                this.toasts.shift();
            }
        },

        /**
         * Remove a toast by ID.
         * @param {number} id
         */
        remove(id) {
            const idx = this.toasts.findIndex((t) => t.id === id);
            if (idx !== -1) {
                const toast = this.toasts[idx];
                if (toast._timer) clearTimeout(toast._timer);
                this.toasts.splice(idx, 1);
            }
        },

        /**
         * Pause auto-dismiss on hover (preserves remaining time).
         * @param {number} id
         */
        pause(id) {
            const toast = this.toasts.find((t) => t.id === id);
            if (toast && toast._timer) {
                clearTimeout(toast._timer);
                toast._timer = null;
                toast._paused = true;
                toast._remaining = Math.max(0, toast._remaining - (Date.now() - toast._started));
            }
        },

        /**
         * Resume auto-dismiss after hover out.
         * @param {number} id
         */
        resume(id) {
            const toast = this.toasts.find((t) => t.id === id);
            if (toast && toast._paused && toast._remaining > 0) {
                toast._paused = false;
                toast._started = Date.now();
                toast._timer = setTimeout(() => this.remove(id), toast._remaining);
            }
        },

        /**
         * ARIA role based on variant — danger is assertive, others are polite.
         * @param {string} variant
         * @returns {string}
         */
        ariaRole(variant) {
            return variant === 'danger' ? 'alert' : 'status';
        },
    };
}
