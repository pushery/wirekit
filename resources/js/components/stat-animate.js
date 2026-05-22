/**
 * Stat counter-animation Alpine plugin.
 *
 * Reads the target value from `data-target` on the root element and
 * animates the bound `value` from 0 to target with an ease-out cubic
 * curve over 1.2s. Animation fires when the element scrolls 40% into
 * view (IntersectionObserver) and runs only once per page load.
 *
 * Respects `prefers-reduced-motion: reduce` — the value snaps to
 * target with no animation if the user has motion-reduction enabled
 * at the OS / browser level.
 *
 * Usage:
 *   <div x-data="wirekitStatAnimate" data-target="10000">
 *       <span x-text="value">0</span>
 *   </div>
 *
 * Numeric input:
 *   data-target="10000"        → animates 0 → 10,000
 *   data-target="$1,250.50"    → animates 0 → 1,250.50, suffix "$" / "," preserved as-is
 *   data-target="42%"          → animates 0 → 42, suffix "%" appended
 *
 * The plugin extracts numerics with a regex; non-numeric prefix/suffix
 * is preserved verbatim. toLocaleString() formats the in-flight value
 * so thousand-separators appear during animation.
 */
export default () => ({
    value: '0',
    // animating: true while counter is running (used by descriptionDeferred Option A
    // to hide/show the description span via x-show).
    animating: false,
    // progress: 0 (start) → 1 (settled), eased. Used by descriptionAnimate Option C
    // to interpolate the description text colour synchronously with the count-up.
    progress: 1,

    init() {
        const target = this.$root.dataset.target ?? '0';

        const numeric = parseFloat(String(target).replace(/[^\d.-]/g, '')) || 0;
        const suffix = String(target).replace(/[\d.,\s-]/g, '');

        // Format helper — used both for the reduced-motion snap and for
        // the in-flight animation tick. Keeps display consistent (locale-
        // formatted thousands-separators + suffix preservation) regardless
        // of which path resolves the value.
        const formatValue = (current) => {
            const rounded = Number.isInteger(numeric) ? Math.round(current) : current.toFixed(2);
            return (typeof rounded === 'number' ? rounded.toLocaleString() : rounded) + suffix;
        };

        // Reduced-motion shortcut: snap to target immediately, no animation.
        // Both `animating` and `progress` resolve to settled state for SR/CLS contract.
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            this.value = formatValue(numeric);
            this.animating = false;
            this.progress = 1;
            return;
        }

        // Pre-flight reactive state — counter is "about to start" but not yet ticking.
        // Description Option A reads animating=false here, so description is visible
        // until intersection fires; that's intentional (no flash before the user sees it).
        this.progress = 0;

        // Encapsulated run-the-counter helper so both the entrance-wrapper path
        // and the standalone IntersectionObserver path can call it.
        const runCounter = () => {
            this.animating = true;
            const start = performance.now();
            const duration = 1200;
            const ease = (t) => 1 - Math.pow(1 - t, 3); // ease-out cubic

            const tick = (now) => {
                const t = Math.min(1, (now - start) / duration);
                const eased = ease(t);
                this.value = formatValue(eased * numeric);
                this.progress = eased;
                if (t < 1) {
                    requestAnimationFrame(tick);
                } else {
                    this.animating = false;
                    this.progress = 1;
                }
            };

            requestAnimationFrame(tick);
        };

        // Entrance-wrapper detection. The Blade template wraps the counter root
        // in an outer <div x-data="wirekitAnimate('…')"> when BOTH animate and
        // animateIn are set on <x-wirekit::stat>. The outer's entrance keyframe
        // shifts geometry (e.g. wk-slide-up-in: translateY(1rem) → translateY(0))
        // for the entire ~300ms entrance window. If the inner's own
        // IntersectionObserver fires while the keyframe is active, the inner's
        // bounding box is shifted out of threshold and the callback returns
        // without starting the counter — the canonical race condition that
        // leaves random stats stuck at "0" on hard refresh.
        //
        // Fix: when the entrance wrapper is present, defer the counter start
        // until the entrance keyframe completes (`animationend` on the outer).
        // The outer's wirekitAnimate plugin already owns scroll-into-view via
        // its own IO; the counter just needs a deterministic "start" signal
        // that doesn't depend on transform-affected geometry.
        const outer = this.$root.parentElement;
        const outerXData = outer?.getAttribute('x-data') ?? '';
        const hasEntranceWrapper = outerXData.includes('wirekitAnimate(');

        if (hasEntranceWrapper) {
            this._entranceListener = (event) => {
                // Only respond to the wk-animate-* entrance keyframe on the
                // outer itself. Descendant animations would bubble up too
                // (animationend is a bubbling event), but they don't carry
                // the wk- prefix unless a developer named a custom keyframe
                // identically. The event.target check pins us to the outer.
                if (event.target !== outer) return;
                if (! event.animationName?.startsWith('wk-')) return;
                outer.removeEventListener('animationend', this._entranceListener);
                this._entranceListener = null;
                runCounter();
            };
            outer.addEventListener('animationend', this._entranceListener);
            return;
        }

        // Standalone counter (no entrance wrapper): use IntersectionObserver
        // on the root itself. Threshold 0.4 means the animation starts when
        // 40% of the element is in viewport — a sweet spot between "too eager"
        // (10%, fires before user reads surrounding context) and "too late"
        // (90%, fires only at full view).
        this._observer = new IntersectionObserver(
            (entries) => {
                if (! entries[0].isIntersecting) return;
                // Null-guard against post-destroy fire — browser-queued callbacks
                // can execute after Alpine teardown set this._observer to null
                // (Livewire morph removing host element pre-intersection is the
                // canonical trigger). Without the guard, `this._observer.disconnect()`
                // throws TypeError("Cannot read properties of null") and reds every
                // developer's assertNoSmoke() / assertNoJavascriptErrors().
                if (! this._observer) return;
                this._observer.disconnect();
                this._observer = null;
                runCounter();
            },
            { threshold: 0.4 }
        );

        this._observer.observe(this.$root);
    },

    /**
     * Alpine teardown hook — disconnect the IntersectionObserver if the
     * stat is removed from the DOM before scrolling into view
     * (Livewire morph, conditional render, navigation). Without this
     * the observer holds a reference to the detached $root indefinitely.
     */
    destroy() {
        if (this._observer) {
            this._observer.disconnect();
            this._observer = null;
        }
        if (this._entranceListener) {
            const outer = this.$root.parentElement;
            outer?.removeEventListener('animationend', this._entranceListener);
            this._entranceListener = null;
        }
    },
});
