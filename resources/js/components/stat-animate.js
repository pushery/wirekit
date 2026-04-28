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

    init() {
        const target = this.$root.dataset.target ?? '0';

        // Reduced-motion shortcut: snap to target immediately, no animation.
        // Single source of truth is the OS-level setting.
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            this.value = target;
            return;
        }

        const numeric = parseFloat(String(target).replace(/[^\d.-]/g, '')) || 0;
        const suffix = String(target).replace(/[\d.,\s-]/g, '');

        // Wait for scroll-into-view, then animate once. Threshold 0.4 means
        // the animation starts when 40% of the element is in viewport — a
        // sweet spot between "too eager" (10%, fires before user reads
        // surrounding context) and "too late" (90%, fires only at full view).
        const observer = new IntersectionObserver(
            (entries) => {
                if (! entries[0].isIntersecting) return;
                observer.disconnect();

                const start = performance.now();
                const duration = 1200;
                const ease = (t) => 1 - Math.pow(1 - t, 3); // ease-out cubic

                const tick = (now) => {
                    const t = Math.min(1, (now - start) / duration);
                    const current = ease(t) * numeric;
                    // Round and format. toLocaleString gives thousand-separators
                    // matching the browser locale; suffix ($, %, etc.) re-appended.
                    const rounded = Number.isInteger(numeric) ? Math.round(current) : current.toFixed(2);
                    this.value = (typeof rounded === 'number' ? rounded.toLocaleString() : rounded) + suffix;
                    if (t < 1) requestAnimationFrame(tick);
                };

                requestAnimationFrame(tick);
            },
            { threshold: 0.4 }
        );

        observer.observe(this.$root);
    },
});
