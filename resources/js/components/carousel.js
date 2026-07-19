/**
 * WireKit Carousel Alpine Component.
 *
 * A native scroll-snap carousel: the browser owns the scrolling, the snapping and
 * the momentum, and this component OBSERVES the result rather than driving it.
 *
 * Why observe rather than drive: the previous implementation translated the track
 * by `-current * 100%`, which meant one slide per view by construction and no
 * touch swipe beyond what the transform allowed. A snap track gets swipe,
 * momentum and multi-per-view from the platform for free — but only if the
 * component treats scroll position as the source of truth instead of trying to
 * own it. Fighting the scroller (writing scrollLeft on every frame) is what makes
 * these components feel broken on a phone.
 *
 * @see https://www.w3.org/WAI/ARIA/apg/patterns/carousel/
 */
export default function wirekitCarousel(config = {}) {
    return {
        current: 0,
        total: 0,
        autoplay: config.autoplay || false,
        playing: false,
        interval: config.interval || 5000,
        loop: config.loop !== false,
        vertical: config.vertical || false,
        _timer: null,
        _hoverPaused: false,
        _observer: null,
        _slides: [],

        init() {
            this._slides = Array.from(this.$el.querySelectorAll('[data-wk-carousel-slide]'));
            this.total = this._slides.length;

            this._watchSlides();

            // Autoplay is motion the reader did not ask for, so it must not start
            // for someone who asked for less of it. This is a real runtime check,
            // not a CSS media query: the timer lives in JS, and a CSS-only guard
            // would leave it running invisibly.
            if (this.autoplay && this.total > 1 && !this._prefersReducedMotion()) {
                this.play();
            }
        },

        destroy() {
            this._stopTimer();

            // Disconnect explicitly. An observer that outlives its element keeps
            // firing into a dead Alpine scope, and the callback then reads
            // properties off nothing — the null-callback class this codebase has
            // been bitten by before.
            if (this._observer) {
                this._observer.disconnect();
                this._observer = null;
            }

            this._slides = [];
        },

        /**
         * Track which slide is showing by watching the scroller, so `current`
         * stays right no matter HOW the scroll happened — a button, a keyboard,
         * a trackpad, or a thumb flick we never hear about.
         */
        _watchSlides() {
            const viewport = this.$refs.viewport;

            if (!viewport || typeof IntersectionObserver === 'undefined') {
                return;
            }

            this._observer = new IntersectionObserver(
                (entries) => {
                    // Guard the post-destroy fire. IntersectionObserver delivers
                    // its callbacks from a queue, so a batch recorded before
                    // destroy() disconnected us can still arrive afterwards and
                    // write into a scope Alpine has already torn down. Today the
                    // emptied _slides list happens to make that a no-op, which is
                    // exactly the kind of accident that stops being true the next
                    // time someone edits this callback.
                    if (!this._observer) return;

                    // Pick the most-visible slide rather than the first one to
                    // cross the line: with several slides per view, "intersecting"
                    // is true for all of them at once and the first-wins reading
                    // would report the leftmost forever.
                    let best = null;

                    for (const entry of entries) {
                        if (!entry.isIntersecting) continue;
                        if (!best || entry.intersectionRatio > best.intersectionRatio) {
                            best = entry;
                        }
                    }

                    if (!best) return;

                    const index = this._slides.indexOf(best.target);
                    if (index !== -1) this.current = index;
                },
                { root: viewport, threshold: [0.25, 0.5, 0.75, 1] },
            );

            for (const slide of this._slides) {
                this._observer.observe(slide);
            }
        },

        _prefersReducedMotion() {
            return typeof window !== 'undefined'
                && typeof window.matchMedia === 'function'
                && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        },

        /**
         * Scroll a slide into view. The scroller does the moving; we only say
         * where to.
         */
        goTo(index) {
            if (index < 0 || index >= this.total) return;

            const slide = this._slides[index];
            const viewport = this.$refs.viewport;
            if (!slide || !viewport) return;

            // Smooth scrolling is motion too. Jumping is the honest fallback —
            // the slide still changes, it just does not travel.
            const behavior = this._prefersReducedMotion() ? 'auto' : 'smooth';

            // Measure against the viewport's own box rather than offsetLeft:
            // offsetLeft is relative to the nearest positioned ancestor, and the
            // slide and the viewport do not have to share one. When they do not,
            // the difference is a silent few pixels — enough to land the scroller
            // just off the snap point.
            const slideBox = slide.getBoundingClientRect();
            const viewportBox = viewport.getBoundingClientRect();

            if (this.vertical) {
                viewport.scrollTo({
                    top: viewport.scrollTop + (slideBox.top - viewportBox.top),
                    behavior,
                });
            } else {
                viewport.scrollTo({
                    left: viewport.scrollLeft + (slideBox.left - viewportBox.left),
                    behavior,
                });
            }

            // Set it now rather than waiting for the observer: a screen reader
            // should hear the new position when the button is pressed, not when
            // the animation finishes.
            this.current = index;
            this._restartTimer();
        },

        next() {
            if (this.current < this.total - 1) {
                this.goTo(this.current + 1);
            } else if (this.loop) {
                this.goTo(0);
            }
        },

        prev() {
            if (this.current > 0) {
                this.goTo(this.current - 1);
            } else if (this.loop) {
                this.goTo(this.total - 1);
            }
        },

        /**
         * Start the auto-advance. This is what the play/pause control drives, and
         * WCAG 2.2.2 requires that control to exist for anything that moves on its
         * own for more than five seconds.
         */
        play() {
            this.playing = true;
            this._startTimer();
        },

        pause() {
            this.playing = false;
            this._stopTimer();
        },

        toggle() {
            this.playing ? this.pause() : this.play();
        },

        /**
         * Hover and focus pause the rotation WITHOUT touching `playing`: the
         * reader is looking at a slide, not asking the carousel to stop forever.
         * Conflating the two is why these controls usually lie — the button reads
         * "paused" because a mouse passed over it.
         */
        pauseOnHover() {
            this._hoverPaused = true;
            this._stopTimer();
        },

        resumeFromHover() {
            this._hoverPaused = false;
            if (this.playing) this._startTimer();
        },

        _startTimer() {
            this._stopTimer();
            if (this._hoverPaused || this.total <= 1) return;

            this._timer = setInterval(() => this.next(), this.interval);
        },

        _stopTimer() {
            if (this._timer) {
                clearInterval(this._timer);
                this._timer = null;
            }
        },

        _restartTimer() {
            if (this.playing && !this._hoverPaused) this._startTimer();
        },

        get announcement() {
            return `Slide ${this.current + 1} of ${this.total}`;
        },
    };
}
