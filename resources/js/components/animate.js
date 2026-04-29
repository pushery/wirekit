/**
 * Animation helper Alpine plugin.
 *
 * Drives the `<x-wirekit::reveal>` Blade wrapper and any consumer that
 * adds `<div x-data="wirekitAnimate('fade-in')">` directly. Triggers an
 * animation by adding the matching `.wk-animate-{preset}` utility class
 * to the host element when a configured trigger fires.
 *
 * Three trigger modes:
 *   'viewport' — IntersectionObserver, threshold 0.4. (Default.)
 *   'click'    — wait for click on the host element.
 *   'manual'   — consumer dispatches `Alpine.$dispatch('wirekit:reveal')`
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

    bindViewport() {
        // Reduced-motion: still trigger (so final state is visible) but the
        // global @media block snaps duration to 0.01ms — visually identical
        // to no-animation. The opacity-from-0 keyframes would otherwise leave
        // the element invisible if we skipped triggering entirely.
        const observer = new IntersectionObserver(
            (entries) => {
                if (! entries[0].isIntersecting) return;
                this.fire();
                if (this.options.once) observer.disconnect();
            },
            { threshold: this.options.threshold }
        );
        observer.observe(this.$root);
    },

    bindClick() {
        const handler = () => {
            this.fire();
            if (this.options.once) this.$root.removeEventListener('click', handler);
        };
        this.$root.addEventListener('click', handler);
    },

    bindManual() {
        const handler = () => {
            this.fire();
            if (this.options.once) window.removeEventListener('wirekit:reveal', handler);
        };
        window.addEventListener('wirekit:reveal', handler);
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
