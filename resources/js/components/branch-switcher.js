/**
 * Branch switcher — which of several generated answers is showing.
 *
 * The index is 1-BASED throughout, and that is deliberate rather than sloppy: it is what the
 * label says ("2 of 3"), what `wire:model` hands the server, and what a caller writes in a
 * template. A zero-based internal index would mean three places converting, and the one that
 * forgot would be off by one in a number the reader can see.
 *
 * ⚠️ ANNOUNCEMENT IS THE WHOLE SENTENCE, EVERY TIME — never a fragment appended to a live
 * region. A screen reader reading "2 of 3" without the noun tells its user nothing, and a
 * region that already holds "1 of 3" may coalesce a change into silence. The count beside the
 * buttons is a plain label for the same reason: making it live would interrupt the reader in
 * the middle of moving.
 *
 * Lifecycle resources held on `this`: NONE. Both handlers are bound declaratively in the
 * template, so Alpine tears them down with the component.
 *
 * @param {Object} config
 * @param {number} [config.total]    how many variants exist
 * @param {number} [config.current]  which one is showing, 1-based
 * @param {boolean} [config.loop]    whether the ends wrap
 * @param {string} [config.countLabel]    ":current of :total", translated in the template
 * @param {string} [config.announcement]  "Showing response :current of :total", likewise
 */
export default function wirekitBranchSwitcher(config = {}) {
    return {
        total: Number(config.total) || 0,
        current: Number(config.current) || 1,
        loop: config.loop === true,
        // `resources/js` has no translator, so every user-visible string is translated in the
        // Blade template and handed down. The English stays as the fallback, which is what a
        // developer who registers this factory by hand gets.
        countLabel: config.countLabel || ':current of :total',
        announcement: config.announcement || 'Showing response :current of :total',
        announced: '',

        init() {
            // Clamped on the way in as well as in the template: a server that regenerates can
            // hand over an index whose variant no longer exists, and an out-of-range current
            // would disable both buttons and strand the reader.
            this.current = this.clamp(this.current);
        },

        clamp(value) {
            if (this.total <= 0) {
                return 0;
            }

            return Math.min(this.total, Math.max(1, Number(value) || 1));
        },

        canGoPrevious() {
            return this.total > 1 && (this.loop || this.current > 1);
        },

        canGoNext() {
            return this.total > 1 && (this.loop || this.current < this.total);
        },

        label() {
            return this.fill(this.countLabel);
        },

        fill(template) {
            return String(template)
                .replace(':current', String(this.current))
                .replace(':total', String(this.total));
        },

        previous() {
            if (!this.canGoPrevious()) {
                return;
            }

            this.go(this.current === 1 ? this.total : this.current - 1);
        },

        next() {
            if (!this.canGoNext()) {
                return;
            }

            this.go(this.current === this.total ? 1 : this.current + 1);
        },

        go(index) {
            const target = this.clamp(index);

            if (target === this.current) {
                return;
            }

            this.current = target;

            // The whole sentence, and a fresh one: a live region handed the same string twice
            // may announce nothing the second time, and a reader who steps forward and back is
            // exactly the case where that happens.
            this.announced = this.fill(this.announcement);

            this.$el.dispatchEvent(
                new CustomEvent('wirekit:branch-switcher:change', {
                    bubbles: true,
                    detail: { current: this.current, total: this.total },
                })
            );
        },
    };
}
