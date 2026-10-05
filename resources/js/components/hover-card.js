import { coordinateOverlay } from '../utils/overlay-coordination.js';
import { applyTriggerAria } from '../utils/trigger-aria.js';

/**
 * WireKit Hover Card Alpine Component.
 *
 * Similar to Tooltip but designed for rich content (avatar, bio, actions).
 * Shows on hover with delay, hides on leave. Stays open when hovering
 * the card itself. Uses Floating UI for positioning.
 *
 * THE KEYBOARD MODEL IS SPELLED OUT HERE BECAUSE THE TELEPORT REMOVED THE ONE
 * THE BROWSER WOULD HAVE GIVEN US FOR FREE. The panel moves to the overlay root
 * at the end of <body> so its `position: fixed` box escapes every ancestor
 * containing block — and sequential focus order follows the DOM, not the
 * screen. A reader who focused the trigger and pressed Tab therefore landed on
 * the NEXT control on the page while the open card sat in front of them, and
 * the card's own buttons were reachable only after tabbing through the whole
 * rest of the document. That is the promise this component makes and Tooltip
 * does not — rich content, `role="dialog"`, interactive children — so the three
 * edges the teleport broke are handled explicitly: Tab off the trigger steps
 * into the card, Tab off its last control steps back out to whatever follows
 * the trigger, and Escape returns focus to the trigger (WCAG 2.4.3).
 *
 * A card with no focusable content keeps the plain behavior: Tab leaves for the
 * next control and the card closes behind it. Nothing is bridged that the
 * reader could not have used.
 *
 * Focus always moves BEFORE the panel hides. `x-show` writes `display: none`,
 * and hiding the subtree that holds focus makes the browser drop it on <body> —
 * after our own focus() call, which would then have accomplished nothing. Same
 * ordering, same reason, as navigation-menu.js.
 *
 * @see https://www.w3.org/WAI/ARIA/apg/patterns/tooltip/
 */
import { position } from '../utils/floating.js';
import { isComposing } from '../utils/ime.js';
import { withOpenAlias } from '../utils/open-alias.js';
import { outOfReachBeside } from '../utils/teleport.js';

/**
 * What counts as focusable inside the card. Same selector navigation-menu's
 * flyout panels use — same question, so the same answer rather than a second
 * list that drifts from it.
 */
const CARD_FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

/**
 * @param {Object} config - Hover card configuration from Blade
 * @param {string} config.placement - Floating UI placement (default: 'bottom')
 * @param {number} config.offset - Distance from trigger in px (default: 8)
 * @param {number} config.delayShow - Delay before showing (ms, default: 300)
 * @param {number} config.delayHide - Delay before hiding (ms, default: 200)
 */
export default function wirekitHoverCard(config = {}) {
    return withOpenAlias({
        /** Move the popup ARIA onto the trigger's focusable child. */
        initTriggerAria() {
            applyTriggerAria(this.$el, this.$watch.bind(this), {});
        },

        isOpen: false,
        _placement: config.placement || 'bottom',
        _offset: config.offset ?? 8,
        _delayShow: config.delayShow ?? 300,
        _delayHide: config.delayHide ?? 200,
        _showTimer: null,
        _hideTimer: null,

        // SPA cleanup handler
        _navCleanup: null,
        // Cross-close channel — see utils/overlay-coordination.js. Two open
        // sibling hover cards overlap, which a reader sees and no test does.
        _coordination: null,
        // Ends both observers of one showing: the one that puts the placement back after a
        // framework update erases it, and the one that follows the trigger on scroll. See the
        // notes beside `repairErasure` and `autoReposition` in show().
        _stopRepair: null,
        // Held up for exactly the moment focus is put back on the trigger by
        // Escape or a shift-Tab out of the card. Without it the trigger's own
        // `focusin` re-shows the card the instant focus lands, and Escape would
        // read as doing nothing at all.
        _returningFocus: false,

        init() {
            this._navCleanup = () => this._forceClose();
            // Not `once`: a component inside `@persist` is carried to the next page without a new
            // `init()`, and still has to close on every page change after the first.
            document.addEventListener('livewire:navigating', this._navCleanup);

            // Opening this one closes every other hover card on the page.
            this._coordination = coordinateOverlay({
                channel: 'wirekit:hover-card-open',
                onOther: () => this._forceClose(),
            });
        },

        destroy() {
            this._coordination?.stop();
            this._coordination = null;

            if (this._navCleanup) {
                document.removeEventListener('livewire:navigating', this._navCleanup);
            }
            this._forceClose();
        },

        /**
         * Mouse enters trigger or panel — show with delay, cancel hide.
         */
        mouseenter() {
            clearTimeout(this._hideTimer);
            if (!this.isOpen) {
                this._showTimer = setTimeout(() => this.show(), this._delayShow);
            }
        },

        /**
         * Mouse leaves trigger or panel — hide with delay.
         * The delay allows moving between trigger and panel without closing.
         *
         * A card the reader is FOCUSED inside is a card in use, so the pointer leaving does
         * not dismiss it. This path asks the same question as the keyboard path beside it,
         * and the two answers are not separable: a reader who hovers the trigger, Tabs into
         * the card — which this component's documentation invites, and which is the whole
         * reason it is a dialog rather than a tooltip — and then moves the mouse away would
         * otherwise lose the card mid-use, and with it their place in the document. Hiding the subtree that holds focus drops it on `<body>` (WCAG 2.4.3).
         *
         * Dismissal is not lost, only handed to the path that owns it: `focusout()` closes the
         * card as soon as focus genuinely leaves.
         */
        mouseleave() {
            this._scheduleHide();
        },

        /**
         * Keyboard focus on trigger — show immediately.
         */
        focusin() {
            if (this._returningFocus) return;

            clearTimeout(this._hideTimer);
            this.show();
        },

        /**
         * Keyboard blur — hide with delay, unless focus landed inside the card.
         *
         * Bound on the panel as well as on the trigger, so leaving the panel
         * again closes the card. That is also why the containment question is
         * asked of the refs and not of `$el`: `$el` is whichever element the
         * listener fired on, so the same expression would mean "the trigger"
         * one time and "the panel" the next, and each reading answers false
         * for the other half of the component.
         */
        focusout() {
            this._scheduleHide();
        },

        /**
         * Arm the hide timer, and close only if the card is not in use when it fires.
         *
         * ONE implementation for both dismissal paths, deliberately. Pointer-leaves and
         * focus-leaves are the same question asked by two different inputs — "is the reader
         * still using this card?" — and they were written twice, whereupon one of them
         * stopped asking. Everything the card promises about interactive content depends on
         * the answer, so it is computed in a single place rather than in two that agree only
         * as long as somebody keeps them agreeing.
         *
         * The containment test is deferred to the moment the timer fires rather than taken
         * when it is armed: focus moves during the delay, which is precisely the case that
         * matters — Tabbing from the trigger into the panel arms this from the trigger's own
         * blur and must not then hide the panel focus just landed in.
         */
        _scheduleHide() {
            clearTimeout(this._showTimer);
            this._hideTimer = setTimeout(() => {
                if (! this._holdsFocus()) {
                    this.close();
                }
            }, this._delayHide);
        },

        /**
         * Is the reader's focus inside this card — either half of it?
         *
         * The panel is teleported to the overlay root, so it is NOT a
         * descendant of the trigger the blur fired on. Asking that one element
         * whether it `contains()` the focused node is therefore false the
         * moment somebody Tabs into the card, and the card hid itself one
         * hide-delay later with focus left on `<body>`. A hover card is the
         * variant that may hold buttons and links — the whole reason it is a
         * dialog rather than a tooltip — so both halves have to be named.
         */
        _holdsFocus() {
            const active = document.activeElement;

            if (! active) return false;

            return Boolean(
                this.$refs.trigger?.contains(active)
                || this.$refs.panel?.contains(active)
            );
        },

        /**
         * Focusable elements inside the card, in DOM order.
         *
         * Resolved through the ref rather than a descendant query on the
         * wrapper: the panel is teleported, so it is not a descendant of
         * anything this component renders inline.
         */
        _cardFocusables() {
            const panel = this.$refs.panel;

            return panel ? [...panel.querySelectorAll(CARD_FOCUSABLE)] : [];
        },

        /**
         * The control the reader actually focused to open the card.
         *
         * The trigger slot wraps whatever the developer passed, so the tab stop
         * is the button or link INSIDE it — the wrapper `<span>` carries no
         * tabindex and `focus()` on it would do nothing, which is a silently
         * lost focus rather than a visible failure.
         */
        _triggerControl() {
            const trigger = this.$refs.trigger;

            if (! trigger) return null;

            return trigger.matches(CARD_FOCUSABLE) ? trigger : trigger.querySelector(CARD_FOCUSABLE);
        },

        /**
         * The box the card is placed against.
         *
         * The control the reader sees and focuses, not the wrapper around it. The wrapper is an
         * inline span whose box is the line's, shorter than a button inside it, so a gap counted
         * from the wrapper's edge would put the card over the button's focus ring, which reaches
         * past the button by its offset and its width. A trigger with no control of its own, or
         * one that is not rendered, keeps the wrapper.
         */
        _placementReference() {
            const control = this._triggerControl();

            return control && control.getClientRects().length > 0 ? control : this.$refs.trigger;
        },

        /**
         * Tab pressed while the trigger has focus — step into the card.
         *
         * Only forwards, and only when there is something in there to use: a
         * read-only card must keep the plain behavior, or Tab would trap the
         * reader on a panel they cannot act on. Shift+Tab is left alone because
         * backwards the DOM order is already right — the trigger sits where it
         * looks like it sits, and the panel is the part that moved.
         */
        tabFromTrigger(event) {
            if (! this.isOpen || event.shiftKey) return;

            const first = this._cardFocusables()[0];

            if (! first) return;

            event.preventDefault();
            first.focus({ preventScroll: true });
        },

        /**
         * Tab pressed inside the card — handle both of its edges.
         *
         * Neither is what the browser would do: forwards it would send the
         * reader off the end of <body>, backwards to whatever happens to
         * precede the overlay root. Both are somewhere else entirely on screen.
         */
        tabWithinCard(event) {
            const focusables = this._cardFocusables();

            if (! focusables.length) return;

            if (event.shiftKey && document.activeElement === focusables[0]) {
                event.preventDefault();
                this.closeAndFocusTrigger();

                return;
            }

            if (! event.shiftKey && document.activeElement === focusables[focusables.length - 1]) {
                event.preventDefault();
                this._focusAfterTrigger();
                this.close();
            }
        },

        /**
         * Close and put focus back where the reader left it.
         *
         * What Escape means, and what a shift-Tab off the card's first control
         * means: the reader is done with the card and back at the thing that
         * opened it. Leaving focus on the hidden panel drops it on <body>
         * instead, which costs a screen-reader user their place in the page.
         */
        closeAndFocusTrigger() {
            const control = this._triggerControl();

            if (control) {
                this._returningFocus = true;
                control.focus({ preventScroll: true });
                this._returningFocus = false;
            }

            this.close();
        },

        /**
         * Escape anywhere while the card is on screen.
         *
         * Heard on the WINDOW, because a hover card opens on a pointer or on the focus
         * reaching its trigger, and neither puts the focus inside the teleported panel:
         * the panel's own handler never hears that key. WCAG 1.4.13 requires content
         * shown on hover or focus to be dismissible without moving the pointer or the
         * focus, and Escape is the mechanism it names.
         *
         * Heard in the CAPTURE phase, and the press that closes the card is marked as
         * handled. A modal or a drawer around the reader reads Escape later, on the
         * document and on the window, and leaves a marked press alone: the reader asked
         * the card to go, and nothing else. A card only shows for what the reader points
         * at or stands on, so nothing on screen sits above it to take the key first.
         * With the focus inside the card, the panel's own handler still runs after this
         * one and hands the focus back to the trigger.
         *
         * @param {KeyboardEvent} event
         */
        escapeAnywhere(event) {
            // An Escape that abandons an input method's conversion belongs to the input method.
            if (isComposing(event)) return;

            if (! this.isOpen) return;

            event?.preventDefault();
            this.close();
        },

        /**
         * Move to the control that FOLLOWS the trigger on the page.
         *
         * Where forward-Tab out of the card belongs: the panel is drawn beside
         * the trigger, so leaving it should continue from the trigger, not from
         * the end of the document where the panel's markup happens to live.
         * Anything inside the trigger is skipped (a descendant also "follows"
         * it by document position), and so is this component's own panel. On the
         * page the whole overlay root is skipped, since it holds every other
         * teleported panel. Inside a modal or drawer the trigger sits in that
         * dialog's teleported clone, and only the clone is in reach (see
         * `outOfReachBeside()`). A control that is not rendered takes no focus.
         */
        _focusAfterTrigger() {
            const trigger = this.$refs.trigger;

            if (! trigger) return;

            const outOfReach = outOfReachBeside(trigger);
            const panel = this.$refs.panel;

            const next = [...document.querySelectorAll(CARD_FOCUSABLE)].find((el) => {
                if (trigger.contains(el)) return false;
                if (panel?.contains(el)) return false;
                if (outOfReach(el)) return false;
                if (el.getClientRects().length === 0) return false;

                return Boolean(trigger.compareDocumentPosition(el) & Node.DOCUMENT_POSITION_FOLLOWING);
            });

            next?.focus({ preventScroll: true });
        },

        /**
         * Show hover card and position via Floating UI.
         */
        async show() {
            if (this.isOpen) return;
            this.isOpen = true;
            this._coordination?.announce();

            // For a caller that loads the card's content when it opens. The announcement above
            // is the cards' own channel, which names no card; this one rises from the card that
            // opened, under a name of its own, so the channel's listeners never hear it.
            this.$root?.dispatchEvent(new CustomEvent('wirekit:hover-card-opened', { bubbles: true }));

            await this.$nextTick();

            const trigger = this.$refs.trigger;
            const panel = this.$refs.panel;
            const reference = this._placementReference();

            if (trigger && panel) {
                // Drop the previous showing's observer before making another one.
                this._stopRepair?.();
                this._stopRepair = null;

                const placement = await position(reference, panel, {
                    // Capped to the room on a short viewport and scrolled inside, so a phone held
                    // sideways or a page zoomed to 400% keeps every entry reachable (WCAG 1.4.10).
                    fitViewport: true,
                    placement: this._placement,
                    offset: this._offset,

                    // Everything this call writes is inline style, and a framework update patches
                    // the panel against its own template, whose `style` attribute carries none of
                    // it. The placement is gone while the card is still open — and this panel is
                    // teleported to the overlay root, so with no `top` it sits at the END of the
                    // document rather than a few pixels off its trigger, with its box unchanged.
                    //
                    // That unchanged box is why `repairErasure` is needed beside `autoReposition`
                    // below: an unchanged box means no resize, and `autoUpdate` observes boxes
                    // rather than the style attribute — it would watch this panel being erased
                    // and report nothing.
                    //
                    // A hover card is not transient either. It holds itself open while the
                    // pointer rests on it, and it is focusable — the reader tabs INTO it, which
                    // is the whole reason `tabFromTrigger` exists.
                    repairErasure: true,
                    // It also follows the trigger: the placement is viewport-relative (`fixed`),
                    // so a scroll while this is open would otherwise leave it where the trigger
                    // was. The same `stop()` ends both observers.
                    autoReposition: true,
                });

                if (placement && typeof placement.stop === 'function') {
                    if (this.isOpen) {
                        this._stopRepair = placement.stop;
                    } else {
                        // Closed while the placement was in flight — its observer would outlive
                        // the panel it belongs to.
                        placement.stop();
                    }
                }
            }
        },

        /**
         * Hide hover card.
         */
        close() {
            this.isOpen = false;
            clearTimeout(this._showTimer);
            clearTimeout(this._hideTimer);
            this._stopRepair?.();
            this._stopRepair = null;
        },

        /**
         * Force close — used during SPA cleanup.
         */
        _forceClose() {
            this.isOpen = false;
            clearTimeout(this._showTimer);
            clearTimeout(this._hideTimer);
            this._stopRepair?.();
            this._stopRepair = null;
        },
    });
}
