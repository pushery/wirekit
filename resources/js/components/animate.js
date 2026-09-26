/**
 * Animation helper Alpine plugin.
 *
 * Drives the `<x-wirekit::reveal>` Blade wrapper and any developer that
 * adds `<div x-data="wirekitAnimate('fade-in')">` directly. Triggers an
 * animation by adding the matching `.wk-animate-{preset}` utility class
 * to the host element when a configured trigger fires.
 *
 * Three trigger modes:
 *   'viewport' — IntersectionObserver, threshold 0.4. (Default.)
 *   'click'    — wait for click on the host element.
 *   'manual'   — developer dispatches `Alpine.$dispatch('wirekit:reveal')`
 *                from anywhere; the helper listens for that custom event.
 *
 * Honors `prefers-reduced-motion: reduce` — when the OS-level setting is
 * on, the class is added immediately (final state visible) but the
 * animation itself is gated by the global @media block in dist/wirekit.css
 * which snaps animation-duration to 0.01ms. So the visual result is a
 * snap-to-final with no motion, regardless of trigger.
 *
 * Usage examples:
 *   <div x-data="wirekitAnimate('fade-in')">…</div>
 *   <div x-data="wirekitAnimate('slide-up-in', { trigger: 'click' })">…</div>
 *   <div x-data="wirekitAnimate('scale-in', { trigger: 'manual', once: false })">…</div>
 *
 * Options:
 *   trigger   — 'viewport' (default) | 'click' | 'manual'
 *   once      — true (default) | false (re-fires on every trigger)
 *   threshold — IntersectionObserver threshold (default 0.4)
 *   duration  — 'fast' | 'normal' (default) | 'slow' — maps to
 *               --motion-wk-duration-{x} via .wk-animate-{x} class.
 */
export default (preset, options = {}) => ({
    preset,
    options: {
        trigger: 'viewport',
        once: true,
        threshold: 0.4,
        duration: 'normal',
        ...options,
    },
    fired: false,

    _observer: null,
    _clickHandler: null,
    _manualHandler: null,

    init() {
        const { trigger } = this.options;

        if (trigger === 'viewport') {
            this.bindViewport();
        } else if (trigger === 'click') {
            this.bindClick();
        } else if (trigger === 'manual') {
            this.bindManual();
        }
    },

    /**
     * Alpine teardown hook — disconnect the observer / remove handlers.
     * Without this, components torn down BEFORE their trigger fired
     * (Livewire morph that removes the host element while it's still
     * off-screen, or before the user clicked, or before the manual
     * event was dispatched) leaked the observer/listener references
     * forever — the Alpine instance was eligible for GC, but the
     * observer's $root reference + the document-scoped event listener
     * kept it alive.
     */
    destroy() {
        if (this._observer) {
            this._observer.disconnect();
            this._observer = null;
        }
        if (this._clickHandler) {
            this.$root.removeEventListener('click', this._clickHandler);
            this._clickHandler = null;
        }
        if (this._manualHandler) {
            window.removeEventListener('wirekit:reveal', this._manualHandler);
            this._manualHandler = null;
        }
    },

    bindViewport() {
        // Reduced-motion: still trigger (so final state is visible) but the
        // global @media block snaps duration to 0.01ms — visually identical
        // to no-animation. The opacity-from-0 keyframes would otherwise leave
        // the element invisible if we skipped triggering entirely.
        //
        // "Enough of it is in view" has two readings, and a tall target needs the second. A
        // target taller than `innerHeight / threshold` can never be `threshold` in view at
        // once — a wrapped section of stacked paragraphs on a phone is — so an observer
        // watching the ratio alone never fired and the section stayed at opacity 0 for good,
        // with nothing logged. It now also fires once the target FILLS `threshold` of the
        // viewport's height, and watches a ladder of thresholds so the growing overlap is
        // reported at all (with only `[threshold]` a target that never crosses it is never
        // reported again after the first observation).
        //
        // The first observation keeps its old meaning: a target already partly in view on
        // load fires at once.
        const threshold = Number(this.options.threshold) || 0;
        const ladder = [0, threshold];
        for (let step = 1; step < 20; step++) {
            ladder.push(step / 20);
        }
        let firstReport = true;
        // Fire on ENTERING the in-view state, not on every rung of the ladder: with
        // `once: false` each fire restarts the animation, and a scroll through the ladder
        // would restart it at every step. Leaving the state re-arms it.
        let inView = false;

        this._observer = new IntersectionObserver(
            (entries) => {
                const entry = entries[0];
                const initial = firstReport;
                firstReport = false;

                const viewportHeight = entry.rootBounds?.height || window.innerHeight || 0;
                const enough = entry.isIntersecting && (initial
                    || entry.intersectionRatio >= threshold
                    || (viewportHeight > 0 && entry.intersectionRect.height >= threshold * viewportHeight));

                if (! enough) {
                    inView = false;

                    return;
                }

                if (inView) return;

                inView = true;
                this.fire();
                if (this.options.once) {
                    // Null-guard against post-destroy fire — same race condition
                    // as wirekitStatAnimate. Without the guard, late-fire
                    // callbacks throw on destroyed observers and pollute
                    // developer browser-test console-error assertions.
                    if (! this._observer) return;
                    this._observer.disconnect();
                    this._observer = null;
                }
            },
            { threshold: [...new Set(ladder)].sort((a, b) => a - b) }
        );
        this._observer.observe(this.$root);
    },

    bindClick() {
        this._clickHandler = () => {
            this.fire();
            if (this.options.once) {
                this.$root.removeEventListener('click', this._clickHandler);
                this._clickHandler = null;
            }
        };
        this.$root.addEventListener('click', this._clickHandler);
    },

    bindManual() {
        this._manualHandler = () => {
            this.fire();
            if (this.options.once) {
                window.removeEventListener('wirekit:reveal', this._manualHandler);
                this._manualHandler = null;
            }
        };
        window.addEventListener('wirekit:reveal', this._manualHandler);
    },

    fire() {
        if (this.fired && this.options.once) return;
        this.fired = true;

        const className = `wk-animate-${this.preset}`;
        const durationClass = `wk-animate-${this.options.duration}`;

        // For non-once, remove first so re-fire restarts the animation.
        if (! this.options.once && this.$root.classList.contains(className)) {
            this.$root.classList.remove(className);
            // Reflow to re-apply the animation class
            void this.$root.offsetWidth;
        }

        this.$root.classList.add(className, durationClass);
    },
});
