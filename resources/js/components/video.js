/**
 * `<x-wirekit::video>`: a video in the page. A press plays a file, a stream (HLS) or the player of
 * YouTube or Vimeo, and a provider's player loads only once the page holds the reader's consent.
 * With `background`, a file or a stream runs as a muted loop with a pause button.
 *
 * Nothing loads before it is asked for. The element gets its address on the first press, a loop
 * when it is seen, and a provider's iframe is created by the press that loads it, so before it the
 * page has sent nothing to the provider.
 *
 * Consent, between the player and the page:
 *   - `consented` from the server: the page knows the reader agreed, and a press plays.
 *   - "Load the video" sends `wirekit:video-consent`, cancelable, with `{ name, provider }`. A
 *     consent manager that takes the question cancels it, asks in its own way, and answers with
 *     `wirekit:consent`. Not canceled, the press is the consent for this one video.
 *   - `wirekit:consent` on `window`, `{ name, granted }`, tells every player: granted, one waiting
 *     for it loads; withdrawn, a provider's player that plays is taken away.
 *
 * A stream plays natively where the browser can (Safari), and elsewhere through hls.js, handed
 * over with `registerHlsEngine()` or found as `window.Hls`. A player that mounted before it
 * arrived plays once `wirekit:hls-engine` says it is there.
 *
 * Lifecycle resources held on `this`, released in destroy():
 *   - _onConsent — the `wirekit:consent` listener on window
 *   - _onEngine — the `wirekit:hls-engine` listener on window while a stream waits for its engine
 *   - _observer — the IntersectionObserver that starts and pauses a loop
 *   - _hls — the engine that plays a stream, destroyed with the component
 *   - _shownRaf — the animation frame that waits for the video or the player to render before
 *     the focus goes to it
 *
 * @param {Object} config
 * @param {string} config.kind - `file`, `hls`, `youtube` or `vimeo` (Support\VideoSource)
 * @param {string|null} [config.src] - the address of a file or a stream
 * @param {string|null} [config.embed] - the address of a provider's player, built on the server
 * @param {string} [config.title] - the video's name, which the provider's iframe takes
 * @param {string} [config.consent] - the name of the consent a provider's player needs
 * @param {boolean} [config.consented] - whether the page holds it
 * @param {boolean} [config.background] - a muted loop
 * @param {string} [config.state] - `ready` or `consent`, as the server drew it
 */
import { keepRaw } from '../utils/keep-raw.js';
import { prefersReducedMotion } from '../utils/motion.js';

/** The stream engine the host handed over. */
const engines = { hls: null };

const PROVIDERS = ['youtube', 'vimeo'];

/**
 * Hand hls.js to every `<x-wirekit::video>` on the page, including players that mounted before
 * it arrived. A page that loads hls.js's own build, which sets `window.Hls`, needs no call.
 *
 * @param {Function} engine  the default export of `hls.js`
 */
export function registerHlsEngine(engine) {
    // Thrown rather than tolerated: an engine that is not the library would read as registered
    // and leave every stream on its sentence that it cannot play, with nothing to say why.
    if (typeof engine !== 'function' || typeof engine.prototype?.loadSource !== 'function') {
        throw new TypeError('registerHlsEngine: pass the default export of hls.js.');
    }

    engines.hls = engine;

    // On `window`, so a player that mounted before the engine arrived can play.
    if (typeof window !== 'undefined' && typeof window.dispatchEvent === 'function' && typeof CustomEvent === 'function') {
        window.dispatchEvent(new CustomEvent('wirekit:hls-engine'));
    }
}

/** The engine that plays a stream here, or null: a registered one first, then the global. */
export function hlsEngine() {
    const engine = engines.hls ?? (typeof window !== 'undefined' ? window.Hls : null);

    if (typeof engine !== 'function') {
        return null;
    }

    return typeof engine.isSupported === 'function' && ! engine.isSupported() ? null : engine;
}

/** Whether the reader asked to save data, which a loop respects as it respects reduced motion. */
export function savesData(nav = typeof navigator !== 'undefined' ? navigator : null) {
    return nav?.connection?.saveData === true;
}

export default function wirekitVideo(config = {}) {
    const provider = PROVIDERS.includes(config.kind);
    // The root, kept while it is attached, for the event the consent button sends and the frame it
    // creates.
    let rootEl = null;

    return {
        state: config.state === 'consent' ? 'consent' : 'ready',
        background: config.background === true,
        consented: config.consented === true,
        // Whether a loop runs now, which names the loop's button.
        looping: false,
        _pausedByReader: false,
        // Whether the loop is in view, so an engine that arrives late can start one already seen.
        _seen: false,
        _requested: false,
        _attached: false,
        _hls: null,
        _observer: null,
        _onConsent: null,
        _onEngine: null,
        _shownRaf: 0,

        init() {
            rootEl = this.$root ?? null;

            // Only a provider's player waits for consent; a file and a stream play on a press.
            if (provider) {
                this._onConsent = (event) => this.consentChanged(event?.detail ?? {});
                window.addEventListener('wirekit:consent', this._onConsent);
            }

            if (this.background) {
                this._watchVisibility();
            }
        },

        destroy() {
            if (this._onConsent) {
                window.removeEventListener('wirekit:consent', this._onConsent);
                this._onConsent = null;
            }

            this._stopWaitingForTheEngine();

            if (this._shownRaf) {
                cancelAnimationFrame(this._shownRaf);
                this._shownRaf = 0;
            }

            this._observer?.disconnect();
            this._observer = null;
            this._hls?.destroy?.();
            this._hls = null;
            this.$refs?.video?.pause?.();
            rootEl = null;
        },

        /** Whether the video shows, playing or as a loop, rather than the poster. */
        isPlaying() {
            return this.state === 'playing' || this.looping;
        },

        /** The play button: a file or a stream in the element, a provider's player in the stage. */
        play() {
            if (provider) {
                if (this.consented) {
                    this._embed();
                } else {
                    this.state = 'consent';
                }

                return;
            }

            const video = this.$refs.video;

            if (! video || ! this._attach(video)) {
                return;
            }

            this.state = 'playing';
            this._start(video);
            this.$nextTick(() => this._onceShown(video, () => this._focus(video)));
        },

        /** "Load the video": the page's consent manager may take the question, or the press is it. */
        requestConsent() {
            const event = new CustomEvent('wirekit:video-consent', {
                detail: { name: config.consent, provider: config.kind },
                bubbles: true,
                cancelable: true,
            });

            if (rootEl && ! rootEl.dispatchEvent(event)) {
                this._requested = true;

                return;
            }

            this.consented = true;
            this._embed();
        },

        /** `wirekit:consent` from the page, for this player's consent or another one. */
        consentChanged({ name, granted } = {}) {
            if (name !== config.consent) {
                return;
            }

            if (granted === true) {
                this.consented = true;

                if (this._requested) {
                    this._requested = false;
                    this._embed();
                } else if (this.state === 'consent') {
                    this.state = 'ready';
                }

                return;
            }

            if (granted === false) {
                this.consented = false;
                this._requested = false;

                if (provider) {
                    this.$refs.stage?.replaceChildren?.();
                    this.state = 'consent';
                }
            }
        },

        /** The loop's button: pause it for good, or play it, also where it would not start itself. */
        toggle() {
            const video = this.$refs.video;

            if (! video) {
                return;
            }

            if (this.looping) {
                this._pausedByReader = true;
                video.pause();
                this.looping = false;

                return;
            }

            this._pausedByReader = false;
            this._loop(video, true);
        },

        /** Create the provider's player, which is the first request to the provider. */
        _embed() {
            const stage = this.$refs.stage;

            if (! stage || typeof config.embed !== 'string' || config.embed === '') {
                return;
            }

            let frame = stage.querySelector('iframe');

            if (! frame) {
                frame = (rootEl?.ownerDocument ?? document).createElement('iframe');
                frame.setAttribute('src', config.embed);
                frame.setAttribute('title', config.title ?? '');
                frame.setAttribute('allow', 'autoplay; fullscreen; picture-in-picture; encrypted-media');
                frame.setAttribute('allowfullscreen', '');
                frame.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
                frame.setAttribute('data-wk-video-frame', '');
                stage.appendChild(frame);
            }

            this.state = 'playing';
            this.$nextTick(() => this._onceShown(frame, () => frame.focus?.({ preventScroll: true })));
        },

        /** Give the element its address, once, natively or through the engine; false where neither plays. */
        _attach(video) {
            if (this._attached) {
                return true;
            }

            if (config.kind === 'hls' && ! video.canPlayType?.('application/vnd.apple.mpegurl')) {
                const Engine = hlsEngine();

                if (! Engine) {
                    this.state = 'unsupported';
                    this._waitForTheEngine();

                    return false;
                }

                // Raw, outside Alpine's reactivity: the engine is the library's object, and a proxy
                // around it would stand between hls.js and its own state.
                this._hls = keepRaw(new Engine());
                this._hls.loadSource(config.src);
                this._hls.attachMedia(video);
            } else {
                video.setAttribute('src', config.src ?? '');
            }

            this._showDefaultTrack(video);
            this._attached = true;

            return true;
        },

        /**
         * The track marked `default` shows from the start. Blink and Gecko show it by themselves;
         * WebKit picks the captions by the reader's own caption setting and keeps a `default`
         * subtitle hidden under its automatic choice, so the player shows the track the page marked.
         */
        _showDefaultTrack(video) {
            const marked = [...(video.querySelectorAll?.('track') ?? [])].find((track) => track.default === true);

            if (marked?.track && marked.track.mode !== 'showing') {
                marked.track.mode = 'showing';
            }
        },

        /** A play() the browser refuses (a policy, a codec) leaves the controls to the reader. */
        _start(video) {
            const playing = video.play?.();

            if (playing && typeof playing.catch === 'function') {
                playing.catch(() => {});
            }
        },

        /** Run the loop, unless the reader paused it, asked for less motion or to save data. */
        _loop(video, byReader = false) {
            if (! byReader && (this._pausedByReader || prefersReducedMotion() || savesData())) {
                return;
            }

            // Muted and inline before the address: a browser allows autoplay on those terms only.
            video.muted = true;
            video.loop = true;
            video.playsInline = true;

            if (! this._attach(video)) {
                return;
            }

            const playing = video.play?.();

            // `looping` follows the play the browser started, not the call: the poster stays until
            // the loop runs, so the box never shows empty before the first frame. A loop that left
            // the view before it started, and that the reader did not start, pauses then.
            if (playing && typeof playing.then === 'function') {
                playing.then(() => {
                    if (! byReader && ! this._seen) {
                        video.pause();
                        this.looping = false;

                        return;
                    }

                    this.looping = ! video.paused;
                }, () => {
                    this.looping = false;
                });
            } else {
                this.looping = ! video.paused;
            }
        },

        /** A loop starts when a quarter of it is seen and pauses when it leaves. */
        _watchVisibility() {
            if (typeof IntersectionObserver === 'undefined' || ! rootEl) {
                return;
            }

            this._observer = new IntersectionObserver((entries) => {
                // A callback the browser queued before destroy() can still run after it.
                if (! this._observer) {
                    return;
                }

                const video = this.$refs?.video;

                if (! video) {
                    return;
                }

                this._seen = entries.some((entry) => entry.isIntersecting);

                if (this._seen) {
                    this._loop(video);
                } else if (this.looping) {
                    video.pause();
                    this.looping = false;
                }
            }, { threshold: 0.25 });
            this._observer.observe(rootEl);
        },

        _waitForTheEngine() {
            if (this._onEngine) {
                return;
            }

            this._onEngine = () => {
                if (hlsEngine() && this.state === 'unsupported') {
                    this._stopWaitingForTheEngine();
                    this.state = 'ready';

                    if (this.background && this._seen && this.$refs?.video) {
                        this._loop(this.$refs.video);
                    }
                }
            };
            window.addEventListener('wirekit:hls-engine', this._onEngine);
        },

        _stopWaitingForTheEngine() {
            if (this._onEngine) {
                window.removeEventListener('wirekit:hls-engine', this._onEngine);
                this._onEngine = null;
            }
        },

        /**
         * Run `then` once `el` is rendered. Without a transition, `x-show` reveals an element on
         * the next animation frame while the page is visible, and WebKit runs `$nextTick` before
         * that frame, so a focus there meets an element with `display: none` and is dropped.
         * Ten frames at most; an element that cannot tell its boxes counts as rendered.
         */
        _onceShown(el, then, frames = 0) {
            const rendered = typeof el?.getClientRects !== 'function' || el.getClientRects().length > 0;

            if (! rendered && frames < 10 && typeof requestAnimationFrame === 'function') {
                if (this._shownRaf) {
                    cancelAnimationFrame(this._shownRaf);
                }

                this._shownRaf = requestAnimationFrame(() => {
                    // A frame that destroy() canceled does not run; one already queued finds 0.
                    if (! this._shownRaf) {
                        return;
                    }

                    this._shownRaf = 0;
                    this._onceShown(el, then, frames + 1);
                });

                return;
            }

            then();
        },

        /** The focus to the element, so the keyboard controls it; one WebKit does not focus takes a tab stop. */
        _focus(video) {
            video.focus?.({ preventScroll: true });

            if (video.ownerDocument?.activeElement !== video) {
                video.tabIndex = 0;
                video.focus?.({ preventScroll: true });
            }
        },
    };
}
