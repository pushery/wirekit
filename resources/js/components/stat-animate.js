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
import { prefersReducedMotion } from '../utils/motion.js';
export default () => ({
    value: '0',
    // animating: true while counter is running (used by descriptionDeferred Option A
    // to hide/show the description span via x-show).
    animating: false,
    // progress: 0 (start) → 1 (settled), eased. Used by descriptionAnimate Option C
    // to interpolate the description text color synchronously with the count-up.
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
        if (prefersReducedMotion()) {
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
            // Idempotent: a real trigger (animationend / IO) and the safety-net
            // fallback timer below both call this; whichever fires first wins,
            // the others are no-ops.
            if (this._started) return;
            this._started = true;
            if (this._fallbackTimer) {
                clearTimeout(this._fallbackTimer);
                this._fallbackTimer = null;
            }
            this.animating = true;

            // Each run carries a token, and a tick abandons itself when the token has moved
            // on. Without it a restart during an in-flight animation leaves TWO frame loops
            // running against the same value: the new one counts up from zero while the old
            // one keeps writing where it had got to, and the reader sees the number stutter
            // between two runs. Found by a test that dispatched mid-animation by accident —
            // which is exactly what a reader does when they hit a replay control early.
            const run = (this._run = (this._run ?? 0) + 1);

            const start = performance.now();
            const duration = 1200;
            const ease = (t) => 1 - Math.pow(1 - t, 3); // ease-out cubic

            const tick = (now) => {
                // A superseded run stops here and writes nothing.
                if (this._run !== run) return;

                // Cancels the settle watchdog below on the first frame that lands:
                // once the animation is ticking it owns the value, and a watchdog
                // firing mid-count would snap over a running animation.
                this._ticked = true;
                if (this._settleTimer) {
                    clearTimeout(this._settleTimer);
                    this._settleTimer = null;
                }

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

            // Settle watchdog. `requestAnimationFrame` only runs while the document
            // is actually being rendered — a frame that is hidden, zero-sized or
            // otherwise not rendered pauses it indefinitely, while `setTimeout` keeps
            // running. In that state the counter has already reset to "0" and no tick
            // ever arrives, so a correct value stays overpainted with a zero for as
            // long as the condition lasts. `init()` already treats a start signal that
            // never arrives as this component's own problem (the fallback timer above);
            // a restart whose frames never arrive is that same problem one step later.
            //
            // Settling costs nothing in the paused-then-resumed case: `start` is
            // captured before the pause, so the first frame after rendering resumes
            // already has `t >= 1` and snaps to the target without animating either.
            this._ticked = false;
            if (this._settleTimer) {
                clearTimeout(this._settleTimer);
            }
            this._settleTimer = setTimeout(() => {
                this._settleTimer = null;
                if (this._run !== run) return;
                if (this._ticked) return;
                this.value = formatValue(numeric);
                this.progress = 1;
                this.animating = false;
            }, duration + 400);

            requestAnimationFrame(tick);
        };

        // Held on the instance so `replay()` can reach it. Everything the counter needs is
        // captured in this closure — the parsed target, the suffix, the formatter — so a
        // restart re-runs the same animation rather than re-deriving it from a dataset that
        // may since have been re-rendered.
        this._runCounter = runCounter;

        // A restart that a caller who is not Alpine can reach. The documentation site renders
        // a replay control for anything carrying `data-replayable`, and this component sets it;
        // dispatching beats reaching into the component's scope from outside.
        this._replayListener = () => this.replay();

        // Bound to BOTH elements a caller could reasonably aim at, because with an entrance
        // wrapper they are not the same element: `data-replayable` — the marker the
        // documentation site renders its replay control for — sits on the OUTER wrapper,
        // while `x-data` and `data-target` sit on the inner root. Events bubble up, so a
        // dispatch on the wrapper could never reach a listener bound to the root, and the
        // control did nothing at all in exactly the configuration that renders one.
        //
        // NOT delegated on `window`, which is how `wirekit:reveal` reaches its components:
        // that event is deliberately a broadcast, and a replay is not. A window listener
        // would restart every counter on the page instead of the one that was asked.
        this._replayTargets = [this.$root];

        const replayMarker = this.$root.parentElement;
        if (replayMarker?.hasAttribute('data-replayable')) {
            this._replayTargets.push(replayMarker);
        }

        this._replayTargets.forEach((el) => el.addEventListener('wirekit:stat-replay', this._replayListener));

        // Safety net. Both start-triggers below can MISS:
        // the entrance path waits for an `animationend` that may never fire (a
        // coalesced / skipped keyframe, or a browser that doesn't emit it), and
        // the standalone IntersectionObserver can fail to report `isIntersecting`
        // on some touch browsers (iOS Safari edge cases). Either miss would leave
        // the count-up stuck at "0" painted over the correct SSR value. After a
        // generous window, start it anyway so `animate` is never visibly stuck.
        // Cleared the instant a real trigger fires (runCounter) or on teardown.
        this._fallbackTimer = setTimeout(() => {
            this._fallbackTimer = null;
            runCounter();
        }, 1800);

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
     * Start the count-up again, from zero.
     *
     * The counter is deliberately once-per-mount: `runCounter` is guarded by `_started`, and
     * the IntersectionObserver disconnects itself the first time it fires. That is right for
     * the scroll trigger — a stat should not re-count every time it passes the viewport — and
     * it left no way to ask for a restart at all.
     *
     * Two surfaces were promising one anyway. The component emits `data-replayable="true"`,
     * which makes the documentation site render a replay control, and the component's own page
     * told developers to "scroll the preview out of view and back in" — which cannot work,
     * because the observer is gone after the first intersection. The site's control happens to
     * work by re-mounting the whole subtree, producing a fresh component; that is a property of
     * the caller destroying the DOM, not something this component offered.
     *
     * Reduced motion is honored the same way `init()` honors it: no animation, straight to the
     * settled state. A restart is still a motion.
     */
    replay() {
        if (! this._runCounter) {
            return;
        }

        if (this._fallbackTimer) {
            clearTimeout(this._fallbackTimer);
            this._fallbackTimer = null;
        }

        // A watchdog from the previous run must not outlive it — left armed, it
        // would fire during the restart and snap the counter to target mid-count.
        if (this._settleTimer) {
            clearTimeout(this._settleTimer);
            this._settleTimer = null;
        }

        this._started = false;
        this.value = '0';
        this.progress = 0;
        this.animating = false;

        this._runCounter();
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
        if (this._fallbackTimer) {
            clearTimeout(this._fallbackTimer);
            this._fallbackTimer = null;
        }
        if (this._settleTimer) {
            clearTimeout(this._settleTimer);
            this._settleTimer = null;
        }
        if (this._replayListener) {
            (this._replayTargets ?? [this.$root]).forEach(
                (el) => el?.removeEventListener('wirekit:stat-replay', this._replayListener)
            );
            this._replayTargets = null;
            this._replayListener = null;
        }
        this._runCounter = null;
    },
});
