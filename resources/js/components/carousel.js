/**
 * WireKit Carousel Alpine Component.
 *
 * Slide-based content carousel with autoplay, loop, and navigation.
 * Follows WAI-ARIA carousel pattern with live region announcements.
 *
 * @see https://www.w3.org/WAI/ARIA/apg/patterns/carousel/
 */
export default function wirekitCarousel(config = {}) {
    return {
        current: 0,
        total: 0,
        autoplay: config.autoplay || false,
        interval: config.interval || 5000,
        loop: config.loop !== false,
        _timer: null,
        _paused: false,

        init() {
            this.total = this.$el.querySelectorAll('[data-wk-carousel-slide]').length;
            if (this.autoplay && this.total > 1) {
                this._startAutoplay();
            }
        },

        destroy() {
            this._stopAutoplay();
        },

        /**
         * Go to next slide.
         */
        next() {
            if (this.current < this.total - 1) {
                this.current++;
            } else if (this.loop) {
                this.current = 0;
            }
            this._restartAutoplay();
        },

        /**
         * Go to previous slide.
         */
        prev() {
            if (this.current > 0) {
                this.current--;
            } else if (this.loop) {
                this.current = this.total - 1;
            }
            this._restartAutoplay();
        },

        /**
         * Go to a specific slide by index.
         */
        goTo(index) {
            if (index >= 0 && index < this.total) {
                this.current = index;
                this._restartAutoplay();
            }
        },

        /**
         * Pause autoplay (on hover/focus).
         */
        pause() {
            this._paused = true;
            this._stopAutoplay();
        },

        /**
         * Resume autoplay (on mouse leave/blur).
         */
        resume() {
            this._paused = false;
            if (this.autoplay) {
                this._startAutoplay();
            }
        },

        _startAutoplay() {
            this._stopAutoplay();
            this._timer = setInterval(() => {
                if (!this._paused) this.next();
            }, this.interval);
        },

        _stopAutoplay() {
            if (this._timer) {
                clearInterval(this._timer);
                this._timer = null;
            }
        },

        _restartAutoplay() {
            if (this.autoplay && !this._paused) {
                this._startAutoplay();
            }
        },

        /**
         * Get announcement text for screen readers.
         */
        get announcement() {
            return `Slide ${this.current + 1} of ${this.total}`;
        },
    };
}
