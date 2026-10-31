/**
 * `<x-wirekit::card-stack>`: shows a hidden part for the visit.
 *
 * The server draws a part the reader hid with `hidden`. Two things show it until the page is left:
 * `wirekit:card-stack-show` on `window` with `{ key }` (and `stack`, the stack's id, where a page
 * holds more than one with that part), and a link whose fragment leads to an element inside it,
 * on arrival, on every `hashchange`, and when the reader follows the link again, which fires no
 * `hashchange`. A part shown that way stays shown when a Livewire render draws it hidden again,
 * until the page is left.
 */
import { onFragmentFollowedAgain } from '../utils/fragment.js';
import { pageScrollBehavior } from '../utils/motion.js';
import { namesSomething, sameName } from '../utils/same-name.js';

export default function wirekitCardStack() {
    return {
        _revealed: null,
        _observer: null,
        _onHash: null,
        _fragmentAgain: null,

        init() {
            this._revealed = new Set();

            // A render that draws a shown part hidden again: take the attribute back off.
            this._observer = new MutationObserver(() => this._keepRevealed());
            this._observer.observe(this.$el, { attributes: true, attributeFilter: ['hidden'], childList: true, subtree: true });

            this._onHash = () => this._revealHash();
            window.addEventListener('hashchange', this._onHash);
            this._fragmentAgain = onFragmentFollowedAgain(this._onHash);
            this._revealHash();
        },

        destroy() {
            this._observer?.disconnect();
            window.removeEventListener('hashchange', this._onHash);
            this._fragmentAgain?.();
            this._fragmentAgain = null;
        },

        /**
         * Show a hidden part for the visit. The key and the stack may come as numbers, as an id
         * a server dispatches does; a number names the part its digits name.
         *
         * @param {{ key: string|number, stack?: string|number }|string|number} detail
         */
        show(detail) {
            const key = detail !== null && typeof detail === 'object' ? detail.key : detail;

            if (detail !== null && typeof detail === 'object' && namesSomething(detail.stack) && ! sameName(detail.stack, this.$el.id)) {
                return;
            }

            const part = this._part(key);

            if (! part) {
                return;
            }

            this._revealed.add(part.getAttribute('data-wk-card-stack-part'));
            part.removeAttribute('hidden');
        },

        /** This stack's own wrapper for a part, never one of a stack nested inside it. */
        _part(key) {
            return [...this.$el.querySelectorAll('[data-wk-card-stack-part]')]
                .find((part) => sameName(key, part.getAttribute('data-wk-card-stack-part')) && part.closest('[data-wk-card-stack]') === this.$el) ?? null;
        },

        _keepRevealed() {
            for (const key of this._revealed) {
                const part = this._part(key);

                if (part?.hasAttribute('hidden')) {
                    part.removeAttribute('hidden');
                }
            }
        },

        /** A fragment that leads into a hidden part shows it, and the target comes into view. */
        _revealHash() {
            let id;

            try {
                id = decodeURIComponent(window.location.hash.slice(1));
            } catch {
                return;
            }

            const target = id ? document.getElementById(id) : null;
            const part = target?.closest('[data-wk-card-stack-part]');

            if (! part || part.closest('[data-wk-card-stack]') !== this.$el || ! part.hasAttribute('hidden')) {
                return;
            }

            this.show({ key: part.getAttribute('data-wk-card-stack-part') });
            // The jump the fragment would have made had the part been shown, at the page's own
            // scroll speed, and a jump under reduced motion.
            target.scrollIntoView({ block: 'start', behavior: pageScrollBehavior() });
        },
    };
}
