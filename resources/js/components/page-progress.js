/**
 * Page progress — the page-edge bar that reports a request the reader is waiting on.
 *
 * It reports WAITING, not work. A round trip has no percentage, so each step closes a
 * fraction of what is left and the bar slows the longer it waits; only the answer takes
 * it to the end. A bar that fills on a timer lies on the first request that runs long,
 * and one that stops dead on a round number reads as hung.
 *
 * Ported from a build that ran in production from 2026-09-08, and the two decisions below
 * are the ones that build paid for. Both look like polish and neither is:
 *
 *   A POLL IS NOT A REQUEST THE READER MADE. A screen polling every three seconds ran the
 *   bar through on every tick. No suite could see it: nothing was broken, and the evidence was
 *   a bar that blinked on its own — which teaches its reader to ignore it, and then it no
 *   longer reports the waiting that IS worth reporting. The test is Livewire's own metadata,
 *   not a list of method names that would need keeping in step with every template.
 *
 *   ONE DECREMENT PER MESSAGE, WHICHEVER EVENT ARRIVES. Livewire cancels a message a newer
 *   one supersedes. A counter that only falls on success leaves the bar sitting on a page
 *   with nothing left to wait for.
 *
 * The fill animates with `transform: scaleX` rather than `width`, which is the same choice
 * the reading bar documents and a stronger one here: this bar moves precisely while the main
 * thread is busy answering the request, so a property that forces layout is the property
 * that stutters.
 *
 * Lifecycle resources held on `this`:
 *   - the two `livewire:navigate` / `livewire:navigated` document listeners
 *   - the show / hide timers and the tick interval
 *   - `_releaseHook`, whatever Livewire handed back for the seam we took
 *   all released in destroy().
 *
 * @param {Object} config
 * @param {number} config.showAfter  ms of waiting before the bar appears at all
 * @param {number} config.ceiling    the percentage the easing approaches while waiting
 */
import { pauseWhileHidden } from '../utils/page-visibility.js';

export default function wirekitPageProgress(config = {}) {
    return {
        active: false,
        pct: 0,
        pending: 0,

        // How many requests the bar ACCEPTED as the reader's own. It counts in begin(),
        // before the delay has decided whether anything becomes visible — on a fast machine
        // a real click shows nothing, so an assertion on the visible bar would be flaky in
        // exactly the direction that matters. The poll rule sits on this seam, so this is
        // what an assertion about that rule can hold.
        begins: 0,

        _showTimer: null,
        _hideTimer: null,
        _tick: null,
        _onNavigate: null,
        _onNavigated: null,
        _onLivewireInit: null,
        _releaseHook: null,
        _visibility: null,

        _showAfter: Number(config.showAfter ?? 180),
        _ceiling: Number(config.ceiling ?? 92),

        init() {
            this._onNavigate = () => this.begin();
            this._onNavigated = () => this.end();

            document.addEventListener('livewire:navigate', this._onNavigate);
            document.addEventListener('livewire:navigated', this._onNavigated);

            // ⚠️ THE EASING STOPS IN A BACKGROUND TAB, and it can because its work is
            // re-derivable: the position is a decoration over a wait whose real end is the
            // answer, not a clock anybody is counting. A reader who returns mid-request finds
            // the bar where it was and it resumes — which is indistinguishable from having
            // crept the whole time, at no cost to a battery nobody was watching.
            //
            // The COUNTER keeps running either way. Only the interval sleeps; a message that
            // completes while the tab is hidden still decrements, so the bar does not come
            // back waiting on something already answered.
            this._visibility = pauseWhileHidden({
                onHide: () => this._stopTick(),
                onShow: () => this._startTick(),
            });

            if (window.Livewire) {
                this._attachToLivewire(window.Livewire);

                return;
            }

            // Alpine can start before Livewire in a bundle that calls Alpine.start() itself,
            // and a component that read window.Livewire once would then report navigation and
            // never a round trip. Nothing fires this event when Livewire is absent altogether,
            // which is the other case this has to survive.
            this._onLivewireInit = () => this._attachToLivewire(window.Livewire);
            document.addEventListener('livewire:init', this._onLivewireInit, { once: true });
        },

        destroy() {
            document.removeEventListener('livewire:navigate', this._onNavigate);
            document.removeEventListener('livewire:navigated', this._onNavigated);

            if (this._onLivewireInit) {
                document.removeEventListener('livewire:init', this._onLivewireInit);
                this._onLivewireInit = null;
            }

            if (typeof this._releaseHook === 'function') {
                this._releaseHook();
                this._releaseHook = null;
            }

            this._visibility?.stop();
            this._visibility = null;

            this.clearTimers();
        },

        _attachToLivewire(livewire) {
            if (! livewire) {
                return;
            }

            if (typeof livewire.interceptMessage === 'function') {
                // `interceptMessage` is what the `commit` hook is itself built on, and it is the
                // only one of the two handed the message's ACTIONS. The `commit` hook receives
                // `message.payload`, which carries the calls and not their metadata — so the poll
                // question cannot be asked one layer up.
                this._releaseHook = livewire.interceptMessage(({ message, onFinish, onError, onCancel }) => {
                    const actions = Array.from(message?.actions ?? []);

                    // An empty action set is a component refresh with no call, not a poll —
                    // and `every()` answers true for it, which would silence real refreshes.
                    if (actions.length > 0 && actions.every((action) => action?.metadata?.type === 'poll')) {
                        return;
                    }

                    this.begin();

                    let done = false;
                    const finish = () => {
                        if (done) {
                            return;
                        }

                        done = true;
                        this.end();
                    };

                    onFinish(finish);
                    onError(finish);
                    onCancel(finish);
                });

                return;
            }

            if (typeof livewire.hook === 'function') {
                // The older seam, kept so a Livewire without `interceptMessage` reports requests
                // rather than nothing. It cannot tell a poll apart, which is why the assertion
                // about the poll rule holds the branch above rather than this one.
                this._releaseHook = livewire.hook('commit', ({ respond }) => {
                    this.begin();
                    respond(() => this.end());
                });
            }
        },

        // Started in two places — when the delay elapses, and when a hidden tab comes back —
        // so it is one method rather than two copies of an easing constant.
        _startTick() {
            if (this._tick || ! this.active) {
                return;
            }

            this._tick = setInterval(() => {
                this.pct += (this._ceiling - this.pct) * 0.12;
            }, 160);
        },

        _stopTick() {
            clearInterval(this._tick);
            this._tick = null;
        },

        clearTimers() {
            clearTimeout(this._showTimer);
            clearTimeout(this._hideTimer);
            clearInterval(this._tick);
            this._showTimer = null;
            this._hideTimer = null;
            this._tick = null;
        },

        begin() {
            this.begins += 1;
            this.pending += 1;

            // A request that begins while the previous bar is fading out. That window opens
            // after every answer that showed the bar, and a follow-up in it is the ordinary
            // case: a second click, `wire:model.live` while typing, a lazy component loading.
            // The early return below used to come first, so the hide timer kept running and
            // retired the bar 220ms later, and the whole new wait showed nothing.
            //
            // The bar goes back to where a wait starts instead of staying at 100: a full bar
            // says "done", and this is a new wait.
            if (this._hideTimer) {
                clearTimeout(this._hideTimer);
                this._hideTimer = null;

                if (this.active) {
                    this.pct = 8;
                    this._startTick();

                    return;
                }
            }

            if (this.active || this._showTimer) {
                return;
            }

            this._showTimer = setTimeout(() => {
                this._showTimer = null;
                this.active = true;
                this.pct = 8;

                this._startTick();
            }, this._showAfter);
        },

        end() {
            this.pending = Math.max(0, this.pending - 1);

            if (this.pending > 0) {
                return;
            }

            clearTimeout(this._showTimer);
            this._showTimer = null;
            clearInterval(this._tick);
            this._tick = null;

            if (! this.active) {
                // It finished inside the delay window. Nothing was ever shown, and nothing
                // should flash now to announce that.
                this.pct = 0;

                return;
            }

            this.pct = 100;
            this._hideTimer = setTimeout(() => {
                this.active = false;
                this.pct = 0;
            }, 220);
        },

        // The object form, not a string template. Alpine's style binding REPLACES the whole
        // attribute when handed a string, which would blow away the static declarations the
        // element carries — its color, its height, its anchor. The object form assigns
        // property by property and leaves them standing. Same reason the reading bar states.
        fillStyle() {
            return {
                transform: 'scaleX(' + (this.pct / 100) + ')',
                opacity: this.active ? '1' : '0',
            };
        },

        // Rounded for assertions and for anything a developer binds to it. The raw value
        // carries the easing's floating point, which is not a number to show anyone.
        roundedProgress() {
            return Math.round(this.pct);
        },
    };
}
