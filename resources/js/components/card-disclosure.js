import { devWarn } from '../utils/dev-warning.js';
import { applyFindableState, supportsUntilFound } from '../utils/findable.js';
import { fragmentId, onFragmentFollowedAgain } from '../utils/fragment.js';
import { prefersReducedMotion, scrollBehavior } from '../utils/motion.js';
import { withOwnScope } from '../utils/own-scope.js';
import { readPersistedFlag, writePersistedFlag } from '../utils/persisted-flag.js';
import { sameName } from '../utils/same-name.js';

/** The attribute the server writes the card's reasons to stand open into, separated by spaces. */
export const CARD_REASONS_ATTRIBUTE = 'data-wk-card-reasons';

/**
 * The attribute that starts the card open. An attribute rather than a value in `x-data`: Livewire
 * writes a changed `x-data` expression onto the card, and Alpine then builds the component anew from
 * it, so a server `open` that flipped between two responses would put the reader's card back where
 * the server had it. Read once, in `init()`.
 */
export const CARD_OPEN_ATTRIBUTE = 'data-wk-card-open';

/** A field a response rejected. */
const REJECTED = '[aria-invalid="true"]';

/** The reasons an element carries, each once, in the order written. */
export function readReasons(el) {
    const raw = el && typeof el.getAttribute === 'function' ? el.getAttribute(CARD_REASONS_ATTRIBUTE) : null;

    return [...new Set(String(raw ?? '').split(/\s+/).filter((reason) => reason !== ''))];
}

/** Whether `next` holds a reason that `before` did not. A reason that went away opens nothing. */
export function hasNewReason(before, next) {
    return next.some((reason) => ! before.includes(reason));
}

/** Whether a node a response added is, or holds, a rejected field. */
function holdsRejected(node) {
    return Boolean(node && node.nodeType === 1 && (node.matches(REJECTED) || node.querySelector(REJECTED)));
}

/** Whether an event on `window` addresses this card: by its name, in a list of names, or every card. */
function addresses(detail, name) {
    if (! detail || typeof detail !== 'object') {
        return false;
    }

    if (detail.all === true) {
        return true;
    }

    return name !== null && (sameName(detail.name, name) || (Array.isArray(detail.names) && detail.names.some((each) => sameName(each, name))));
}

/**
 * Card disclosure: the open state of `<x-wirekit::card collapsible>`.
 *
 * The state is `isOpen`. The card's own head toggles it, and only that click is the reader's choice:
 * it is what the browser remembers under `persist` and what `wirekit:card-toggled` reports, so an
 * application that keeps the state in its own store can save it there. Everything else that opens
 * the card opens it FOR the reader and records nothing: the browser's find in page, a field the
 * browser finds invalid, an address whose fragment names an element inside, `wirekit:card-open`, a
 * reason the server gives that the card did not have, and a field inside that a response rejects.
 * `wirekit:card-close` folds it and records nothing either.
 *
 * `open` from the server (`data-wk-card-open`) wins over the remembered state on every load, so a
 * card that needs the reader is open whatever they folded last time. A fold during the visit is
 * still remembered. A card holding a field the server rejected starts open too. After the load the
 * state is the reader's: a response changes it only through a reason the card did not have or a
 * field it rejects.
 *
 * Lifecycle resources held on `this`:
 *   - _watcher (MutationObserver) — reads the card's reasons and the fields in its region after each
 *     response; disconnected in destroy() and null-guarded in its callback against a call the
 *     browser queued before the teardown.
 *   - _fragmentAgain (a click listener on `window`, utils/fragment.js) — opens the card when the
 *     reader follows a link into it a second time, which fires no `hashchange`; stopped in
 *     destroy().
 *
 * @param {Object}       [config]
 * @param {boolean}      [config.open]     start open, whatever the browser remembers
 * @param {string|null}  [config.persist]  localStorage key of the reader's choice; null keeps none
 * @param {string|null}  [config.name]     the name `wirekit:card-open` and `wirekit:card-close` address the card by
 * @param {string|null}  [config.warning]  a composition warning, written in debug only
 */
export default function wirekitCardDisclosure(config = {}) {
    return withOwnScope({
        isOpen: config.open === true,
        _forced: config.open === true,
        _persist: config.persist || null,
        _name: config.name || null,
        _reasons: [],
        _watcher: null,
        _fragmentAgain: null,

        init() {
            devWarn(config.warning);

            if (this.$root && typeof this.$root.hasAttribute === 'function' && this.$root.hasAttribute(CARD_OPEN_ATTRIBUTE)) {
                this._forced = true;
            }

            this.isOpen = this._forced || readPersistedFlag(this._persist, this.isOpen);
            this._reasons = readReasons(this.$root);

            // A field the server rejected needs the reader, whatever they folded on an earlier visit.
            if (! this.isOpen && this._region()?.querySelector(REJECTED)) {
                this.isOpen = true;
            }

            this.openForFragment();
            this._fragmentAgain = onFragmentFollowedAgain(() => this.openForFragment());
            this._watch();
        },

        destroy() {
            this._watcher?.disconnect();
            this._watcher = null;
            this._fragmentAgain?.();
            this._fragmentAgain = null;
        },

        /** The part that folds: the ref, or the region found through the DOM from a scope that has no ref. */
        _region() {
            return this.$refs.region ?? this.$root.querySelector(':scope > [data-wk-card-region]');
        },

        /**
         * What a response changes on the card. Livewire writes the server's markup over the card's
         * own elements and runs no `init()` again, so the card reads the change from the DOM: its own
         * reasons, and the fields in its region. Both are watched from the card's root.
         */
        _watch() {
            if (typeof MutationObserver === 'undefined' || ! this.$root) {
                return;
            }

            this._watcher = new MutationObserver((records) => {
                if (! this._watcher) {
                    return;
                }

                this._answer(records);
            });
            this._watcher.observe(this.$root, {
                attributes: true,
                attributeOldValue: true,
                attributeFilter: [CARD_REASONS_ATTRIBUTE, 'aria-invalid'],
                childList: true,
                subtree: true,
            });
        },

        /** One batch of changes: a reason the card did not have, or a field newly rejected, opens it. */
        _answer(records) {
            const region = this._region();
            let open = false;

            for (const record of records) {
                if (record.type === 'attributes' && record.attributeName === CARD_REASONS_ATTRIBUTE) {
                    // A card nested in this one carries reasons of its own.
                    if (record.target !== this.$root) {
                        continue;
                    }

                    const next = readReasons(this.$root);

                    open = open || hasNewReason(this._reasons, next);
                    this._reasons = next;
                } else if (region && region.contains(record.target)) {
                    open = open || (record.type === 'attributes'
                        ? record.target.getAttribute('aria-invalid') === 'true' && record.oldValue !== 'true'
                        : Array.from(record.addedNodes).some(holdsRejected));
                }
            }

            if (open) {
                this.reveal();
            }
        },

        /** The reader's click on the head: the one change that is remembered and reported. */
        toggle() {
            this.isOpen = ! this.isOpen;
            writePersistedFlag(this._persist, this.isOpen);
            this.$dispatch('wirekit:card-toggled', { name: this._name, persist: this._persist, open: this.isOpen });

            if (this.isOpen) {
                this._announceOpen();
            }
        },

        /** Open for the reader, and leave an open card alone. Nothing is remembered. */
        reveal() {
            if (! this.isOpen) {
                this.isOpen = true;
                this._announceOpen();
            }
        },

        /**
         * Resolves once the part that folds stands at its full height. Opening slides it from
         * nothing, so an element inside has a box while the page around it is still as short as
         * with the card closed, and a scroll to it would land short. At once where nothing animates:
         * a reduced-motion preference, or a card that was open already.
         */
        _whenOpened() {
            const region = this._region();
            const frame = () => new Promise((resolve) => {
                if (typeof requestAnimationFrame === 'function') {
                    requestAnimationFrame(() => resolve());
                } else {
                    setTimeout(resolve, 16);
                }
            });
            const running = () => (region && typeof region.getAnimations === 'function' ? region.getAnimations() : []);

            // Two frames, so a slide the state change starts on the next frame is running when read.
            return new Promise((resolve) => this.$nextTick(resolve))
                .then(frame)
                .then(frame)
                .then(async () => {
                    for (let round = 0; round < 3; round++) {
                        const animations = running();

                        if (animations.length === 0) {
                            return;
                        }

                        await Promise.all(animations.map((animation) => Promise.resolve(animation.finished).catch(() => null)));
                    }
                });
        },

        /**
         * `wirekit:card-opened` from the card once it stands open at its full height, so a script
         * that opened it can bring an element inside into view and land where it means to. Not
         * sent for a card the reader folded again in the meantime.
         */
        _announceOpen() {
            return this._whenOpened().then(() => {
                if (this.isOpen) {
                    this.$dispatch('wirekit:card-opened', { name: this._name });
                }
            });
        },

        /**
         * A field inside is invalid and the browser is about to focus it. The region has to be shown
         * before the browser goes on, so it is shown here and now rather than on Alpine's next tick:
         * a closed card would otherwise swallow the submit with no message, and the console would
         * report a control that is not focusable.
         */
        revealForInvalid() {
            const region = this._region();

            if (region) {
                applyFindableState(region, true, supportsUntilFound(region.ownerDocument));
            }

            this.reveal();
        },

        /**
         * `wirekit:card-open` dispatched on the card or on any element inside it. A card that was
         * open already answers with `wirekit:card-opened` as well, so whoever asked waits for one
         * answer either way.
         */
        openFrom() {
            this._openAsked();
        },

        /** `wirekit:card-open` on `window`: `{ name }`, `{ names: [...] }` or `{ all: true }`. */
        openByName(event) {
            if (addresses(event && event.detail, this._name)) {
                this._openAsked();
            }
        },

        _openAsked() {
            if (this.isOpen) {
                this._announceOpen();
            } else {
                this.reveal();
            }
        },

        /**
         * `wirekit:card-close` dispatched on the card or on an element inside it folds the nearest
         * card only: folding a card around it would hide more than was asked for. An event that
         * addresses cards by name or all of them is answered on `window`.
         */
        closeFrom(event) {
            const detail = event && event.detail;

            if (detail && typeof detail === 'object' && (detail.all === true || detail.name != null || Array.isArray(detail.names))) {
                return;
            }

            const target = event && event.target;

            if (target && typeof target.closest === 'function' && target.closest('[data-wk-card-collapsible]') === this.$root) {
                this._fold();
            }
        },

        /** `wirekit:card-close` on `window`: `{ name }`, `{ names: [...] }` or `{ all: true }`. */
        closeByName(event) {
            if (addresses(event && event.detail, this._name)) {
                this._fold();
            }
        },

        /**
         * Fold for the reader, and record nothing. A focus inside the region would be left on an
         * element nobody can see, so it moves to the head, the control that opens the card again.
         */
        _fold() {
            if (! this.isOpen) {
                return;
            }

            const region = this._region();
            const active = region && region.ownerDocument ? region.ownerDocument.activeElement : null;

            this.isOpen = false;

            if (active && region.contains(active)) {
                this.$root.querySelector(':scope > [data-wk-card-head] [aria-controls]')?.focus();
            }
        },

        /**
         * The address names an element inside the card: open it, then bring the element into view,
         * which the browser could not do while it was hidden. Read on start, on `hashchange`, and
         * when the reader follows a link to the same fragment again.
         */
        openForFragment() {
            const id = fragmentId(typeof window === 'undefined' ? '' : window.location.hash);

            if (id === '' || this.isOpen) {
                return;
            }

            const target = this.$root.ownerDocument.getElementById(id);

            if (! target || ! this.$root.contains(target)) {
                return;
            }

            this.reveal();
            this._whenOpened().then(() => target.scrollIntoView({ block: 'start', behavior: scrollBehavior(! prefersReducedMotion()) }));
        },
    });
}
