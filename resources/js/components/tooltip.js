/**
 * WireKit Tooltip Alpine Component.
 *
 * Supports hover (desktop), focus (keyboard), and long-press (touch).
 * Positioning via Floating UI with auto flip/shift.
 */
import { placementReference, position } from '../utils/floating.js';
import { isComposing } from '../utils/ime.js';
import { withOpenAlias } from '../utils/open-alias.js';
import { singleKeysHidden, watchSingleKeysHidden } from '../utils/single-keys-hidden.js';
import { timerDelay } from '../utils/timer-delay.js';

// What a reader can tab to.
const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

// A control that holds a cut text as part of itself, so a focus on the control is a focus on the
// text. One control each: a focusable container, such as a scroll region or a listbox that moves an
// active descendant, belongs to every text inside it at once and is not one of these.
const SURROUNDING_CONTROL = [
    'a[href]', 'button', 'summary', 'label',
    '[role="button"]', '[role="link"]', '[role="tab"]', '[role="option"]', '[role="treeitem"]', '[role="gridcell"]',
    '[role="menuitem"]', '[role="menuitemcheckbox"]', '[role="menuitemradio"]',
    '[role="checkbox"]', '[role="radio"]', '[role="switch"]',
].join(', ');

// Every cut text a tooltip repeats carries this mark (resources/views/components/text.blade.php).
const CUT_TEXT = '[data-wk-cut-tip]';

// A text in a tooltip's trigger whose words lead the panel while it is cut, as the label of a badge
// with `truncate`, and the place the panel holds for them (resources/views/components/tooltip.blade.php).
const CUT_LEAD = '[data-wk-tooltip-cut]';
const CUT_LEAD_PLACE = '[data-wk-tooltip-cut-words]';

// The trigger of a tooltip, the element around what the caller put in its slot.
const TRIGGER = '[data-wk-tooltip-trigger]';

/** A text's words as they read on the line: the spaces and line breaks of the markup collapsed. */
const wordsOf = (element) => (element?.textContent ?? '').replace(/\s+/g, ' ').trim();

// A widget that keeps the keyboard focus to itself and moves it with the arrow keys. A tab stop of
// a cut text's own inside one would be a stop the widget does not expect.
const COMPOSITE_WIDGET = [
    '[role="grid"]', '[role="treegrid"]', '[role="listbox"]', '[role="combobox"]', '[role="menu"]',
    '[role="menubar"]', '[role="tablist"]', '[role="tree"]', '[role="radiogroup"]', '[role="toolbar"]',
].join(', ');

/**
 * @param {Object} config - Tooltip configuration from Blade
 * @param {string} config.placement - Floating UI placement
 * @param {number} config.offset - Distance between trigger and tooltip in px
 * @param {number} config.delayShow - Delay before showing on hover (ms)
 * @param {number} config.delayHide - Delay before hiding on mouseleave (ms)
 * @param {boolean} [config.whenTruncated] - Repeat the text of the element it sits on, only while that text is cut off
 */
export default function wirekitTooltip(config = {}) {
    return withOpenAlias({
        isOpen: false,
        // `||` is CORRECT here — placement is a string enum and '' is not a valid
        // placement, so there is no falsy-but-legitimate value to preserve.
        _placement: config.placement || 'top',
        // `??` (not `||`) for the numeric props: 0 is a LEGITIMATE value (`offset="0"`
        // = flush tooltip, `delay-show="0"` = instant), and `0 || default` would
        // silently discard exactly that value and revert to the default. Only
        // undefined/null should fall back. Do NOT "consistency-fix" these to `||`.
        _offset: config.offset ?? 6,
        _delayShow: config.delayShow ?? 300,
        _delayHide: config.delayHide ?? 100,
        _showTimer: null,
        _hideTimer: null,
        _longPressTimer: null,
        _autoDismissTimer: null,

        // The positioner's teardown handle, held only while the tooltip is open. It ends both
        // observers of one showing, the attribute-filtered MutationObserver and the scroll and
        // resize listeners, and every hide calls it — see the notes beside `repairErasure` and
        // `autoReposition` below for why a tooltip of all things needs them.
        _stopRepair: null,

        // Stored cleanup handler for destroy()
        _navCleanup: null,

        // Puts the description back on the control after an update; disconnected in destroy().
        _describedByObserver: null,

        // Takes the other platform's keys out of the panel again after an update, and a single-key
        // shortcut while single keys are hidden; disconnected in destroy().
        _shownKeysObserver: null,

        // Stops following the switch for single keys; called in destroy().
        _stopSingleKeysWatch: null,

        // The single-key shortcuts taken out of the panel, each with the comment that holds its place.
        _keysAway: [],

        // `text truncate="tooltip"`: the tooltip repeats the text of the element it sits on, which
        // that element cuts off, and only while it does. See `_watchTheCut()`.
        _whenTruncated: config.whenTruncated === true,

        // That text's own tab stop, bound as `x-bind:tabindex="tabStop"`: '0' while the text is cut
        // and stands in no control, null otherwise, which removes the attribute.
        tabStop: null,
        _standsAlone: false,

        // The control the text stands in, its listeners, and the observers of the cut; all released
        // in destroy(). `_surroundingAround` is the element the text stands in, which for a label
        // is the label rather than the control it names.
        _surroundingControl: null,
        _surroundingAround: null,
        _surroundingFocusIn: null,
        _surroundingFocusOut: null,
        _cutObservers: [],

        // The words of every cut text in the control, while this text speaks for all of them on a
        // focus of the control. See `_focusFromTheControl()`.
        _controlWords: null,

        init() {
            this._moveDescriptionToTheFocusableTrigger();
            this._keepTheDescriptionAcrossUpdates();
            // The panel comes with the template that teleports it, which Alpine sets up after this
            // init() has run, so its keys are kept from the next tick on.
            this.$nextTick?.(() => this._keepTheShownKeysOnly());

            if (this._whenTruncated) {
                this._watchTheCut();
            }

            // Cleanup on SPA navigation
            this._navCleanup = () => this._forceClose();
            // Not `once`: a component inside `@persist` is carried to the next page without a new
            // `init()`, and still has to close on every page change after the first.
            document.addEventListener('livewire:navigating', this._navCleanup);
        },

        /**
         * Put `aria-describedby` on the element a reader actually lands on.
         *
         * The template can only annotate its own wrapper `<div>`, because the trigger is the
         * CALLER's markup — a `<button>`, a link, whatever they passed. A wrapper is not
         * focusable and is not announced, so without `focusable-trigger` the description was
         * attached to nothing a reader ever reaches: the tooltip existed and was announced to
         * nobody.
         *
         * Moved rather than duplicated. Two elements describing themselves with the same id
         * makes a nested pair announce the text twice, and the wrapper's copy is the one with
         * no reader on it.
         *
         * A tooltip switched off describes nothing, or a reader would hear a tip nobody sees: the
         * description this tooltip wrote goes while the switch is on and comes back with it off.
         */
        _moveDescriptionToTheFocusableTrigger() {
            const root = this.$refs?.trigger;
            if (!root || typeof root.querySelector !== 'function') {
                return;
            }

            const id = root.getAttribute('data-wk-tooltip-describedby');
            if (!id) {
                return;
            }

            const quiet = this._disabled();

            // The wrapper itself takes the tab stop under `focusable-trigger`; in that case
            // it IS the element a reader lands on and keeps the attribute.
            if (root.hasAttribute('tabindex')) {
                if (quiet) {
                    root.removeAttribute('aria-describedby');
                } else if (!root.hasAttribute('aria-describedby')) {
                    root.setAttribute('aria-describedby', id);
                }

                return;
            }

            const focusable = root.querySelector(FOCUSABLE);

            if (!focusable) {
                return;
            }

            if (quiet) {
                if (focusable.getAttribute('aria-describedby') === id) {
                    focusable.removeAttribute('aria-describedby');
                }
            } else if (!focusable.hasAttribute('aria-describedby')) {
                // A caller who described their own control keeps their description — replacing it
                // would silence something they chose deliberately.
                focusable.setAttribute('aria-describedby', id);
            }

            root.removeAttribute('aria-describedby');
        },

        /**
         * Keep the shortcut in the panel to the keys the stylesheet shows.
         *
         * A closed panel is hidden, and a hidden element that `aria-describedby` names counts all of
         * its text, the keys the stylesheet hides as well (Accessible Name and Description
         * Computation, step 2A). Two rules hide keys. A shortcut draws the keys of both kinds of
         * platform, and the stylesheet shows the one the root names in `data-wk-platform`: the keys
         * of the other platform leave the panel, and leave it again when an update puts them back.
         * A single-key shortcut is drawn only while single keys work, which the reader's switch and
         * a screen that turns them off change while the page is open: its keys leave the panel while
         * they are hidden and come back to their place when they are drawn again. Closed or open,
         * the tooltip describes its control with the keys it shows. Before the root names a
         * platform the platform's keys are left as they are.
         */
        _keepTheShownKeysOnly() {
            const panel = this.$refs?.tooltip;

            // A tooltip taken out of the page before the tick takes its panel with it.
            if (! panel || panel.isConnected === false || typeof panel.querySelector !== 'function') {
                return;
            }

            const platform = typeof document === 'undefined' ? null : document.documentElement?.getAttribute?.('data-wk-platform');
            const variants = (platform === 'mac' || platform === 'other') && panel.querySelector('[data-wk-kbd-platform]') !== null;
            const single = panel.querySelector('[data-wk-kbd-single]') !== null;

            if (! variants && ! single) {
                return;
            }

            const otherPlatform = `[data-wk-kbd-platform="${platform === 'mac' ? 'other' : 'mac'}"]`;
            // Each round changes nothing the second time, so the observer's own writes end it.
            const keep = () => {
                if (variants) {
                    panel.querySelectorAll(otherPlatform).forEach((el) => el.remove());
                }

                if (single) {
                    this._placeSingleKeys(panel, singleKeysHidden());
                }
            };

            keep();

            if (typeof MutationObserver === 'function') {
                this._shownKeysObserver = new MutationObserver(keep);
                this._shownKeysObserver.observe(panel, { childList: true, subtree: true });
            }

            if (single) {
                this._stopSingleKeysWatch = watchSingleKeysHidden(keep);
            }
        },

        /**
         * Take the panel's single-key shortcuts out while the stylesheet hides them, each leaving a
         * comment in its place, and put them back in those places while it draws them. A shortcut
         * inside one already taken out goes with it. A place an update has drawn over is dropped:
         * the update brought the keys back itself, and they leave again if they are still hidden.
         */
        _placeSingleKeys(panel, hidden) {
            if (hidden) {
                panel.querySelectorAll('[data-wk-kbd-single]').forEach((el) => {
                    if (! panel.contains(el)) {
                        return;
                    }

                    const place = el.ownerDocument.createComment('');
                    el.replaceWith(place);
                    this._keysAway.push({ place, el });
                });

                return;
            }

            const away = this._keysAway;
            this._keysAway = [];

            for (const { place, el } of away) {
                if (panel.contains(place)) {
                    place.replaceWith(el);
                }
            }
        },

        /**
         * Keep the description on the control a reader lands on when the page updates.
         *
         * A Livewire update patches the trigger against its template, which carries
         * `aria-describedby` on the wrapper and none on the caller's control. That undoes the move
         * above while the tooltip stays open and focused, so the control a reader is on describes
         * nothing any more. The observer watches that one attribute inside the trigger, and the
         * replaced control too, and makes the move again. The move changes nothing the second time,
         * so its own writes end the round. The same observer watches the switch on the root, which
         * a call site binds (`x-bind:data-wk-tooltip-disabled`), so the description follows it.
         */
        _keepTheDescriptionAcrossUpdates() {
            const root = this.$refs?.trigger;
            if (!root || typeof MutationObserver !== 'function' || !root.getAttribute?.('data-wk-tooltip-describedby')) {
                return;
            }

            this._describedByObserver = new MutationObserver(() => this._moveDescriptionToTheFocusableTrigger());
            this._describedByObserver.observe(root, {
                attributes: true,
                attributeFilter: ['aria-describedby'],
                childList: true,
                subtree: true,
            });

            if (this.$root && this.$root !== root) {
                this._describedByObserver.observe(this.$root, {
                    attributes: true,
                    attributeFilter: ['data-wk-tooltip-disabled'],
                });
            }
        },

        /**
         * Keep a cut text reachable by keyboard, and its tooltip true to what is cut.
         *
         * A keyboard reaches the text through a control: one inside it, whose focus the element's
         * own `focusin` hears, or one around it, a link or a button the text is part of. Focus on
         * a control around it never travels down to the text, so that control gets listeners of
         * its own. A text in no control at all takes a tab stop while it is cut, so its tooltip is
         * not for the pointer alone (WCAG 2.1.1), and gives it back once the text fits. Not inside
         * a widget that moves the focus with the arrow keys, and not where nothing may take focus.
         *
         * The cut is measured again when the element changes size (a narrower window, a container
         * shown late), when its text changes (an update), and once the fonts are in.
         */
        _watchTheCut() {
            const host = this.$root;
            if (!host) {
                return;
            }

            const around = host.parentElement?.closest?.(SURROUNDING_CONTROL) ?? null;
            // A label hands its focus to the control it names, which need not be inside it.
            const control = around?.tagName === 'LABEL' ? around.control : around;

            if (control) {
                this._surroundingControl = control;
                this._surroundingAround = around;
                this._surroundingFocusIn = (event) => this._focusFromTheControl(event);
                this._surroundingFocusOut = () => {
                    this._controlWords = null;
                    this.focusout();
                };
                control.addEventListener('focusin', this._surroundingFocusIn);
                control.addEventListener('focusout', this._surroundingFocusOut);
            }

            // Decided once, so only by what lasts. A text hidden from assistive technology takes no
            // stop, since a focusable element there is announced as nothing. An inert region does
            // not count: it takes the focus from everything in it while it lasts, and the page
            // behind an open modal is inert only until the modal closes, when a row an update
            // added there would otherwise have no stop at all.
            this._standsAlone = !around
                && !host.hasAttribute('tabindex')
                && !host.querySelector(FOCUSABLE)
                && !host.closest(COMPOSITE_WIDGET)
                && !host.closest('[aria-hidden="true"]');

            const measure = () => this._measureTheCut();

            if (typeof ResizeObserver === 'function') {
                const size = new ResizeObserver(measure);
                size.observe(host);
                this._cutObservers.push(size);
            }

            if (typeof MutationObserver === 'function') {
                const text = new MutationObserver(measure);
                text.observe(host, { childList: true, characterData: true, subtree: true });
                this._cutObservers.push(text);
            }

            document.fonts?.ready?.then(measure);
            measure();
        },

        /**
         * Keyboard focus on the control the text stands in.
         *
         * A control can hold more than one cut text, a list entry with a name and a code under it
         * for one. Every text listens to the same control, and each opened its own panel above its
         * own line, so a focus stacked the panels over each other. Now the first text in the
         * control that is cut speaks for all of them: its panel carries the words of every cut text
         * there, joined by a middle dot, and the others stay closed. The pointer still shows the
         * tooltip of the text it is on.
         */
        _focusFromTheControl(event) {
            const cut = this._cutTextsInTheControl();

            if (cut.length > 1 && cut[0] !== this.$root) {
                return;
            }

            this._controlWords = cut.length > 1 ? cut.map(wordsOf).join(' · ') : null;
            this.focusin(event);
        },

        /** The texts in this text's control that are cut right now, in the order of the page. */
        _cutTextsInTheControl() {
            const around = this._surroundingAround;

            if (!around || typeof around.querySelectorAll !== 'function') {
                return [];
            }

            return [...around.querySelectorAll(CUT_TEXT)].filter((element) =>
                element.parentElement?.closest?.(SURROUNDING_CONTROL) === around
                && element.scrollWidth > element.clientWidth);
        },

        /**
         * Act on the cut as it is now: the tab stop of a text that stands alone, and a tooltip on
         * screen, which takes new text while the text is cut and closes once it fits. A text that
         * holds the focus keeps its stop until the focus leaves, so a resize never drops it.
         */
        _measureTheCut() {
            const cut = this._isTruncated();
            const focused = this.$root?.matches?.(':focus') === true;
            this.tabStop = this._standsAlone && (cut || focused) ? '0' : null;

            if (!this.isOpen) {
                return;
            }

            if (cut) {
                this._writeTheCutText();
            } else {
                this.close();
            }
        },

        /**
         * Is the element's text cut off at its end right now?
         *
         * The element's own measure, in whole pixels and with no tolerance: a text cut by a pixel
         * still has its tooltip, and a text that fits reports no more width than its box.
         */
        _isTruncated() {
            const el = this.$root;

            return Boolean(el) && el.scrollWidth > el.clientWidth;
        },

        /**
         * Write the cut text, whole, into the panel. Read from the element when the tooltip shows
         * and while it stays, so it repeats the text as it is now rather than as it was rendered;
         * the spaces and line breaks of the markup collapse as they do on the line.
         */
        _writeTheCutText() {
            const panel = this.$refs?.tooltip;
            if (!panel) {
                return;
            }

            const text = this._controlWords ?? wordsOf(this.$root);
            if (panel.textContent !== text) {
                panel.textContent = text;
            }

            // The panel stands in the overlay root, outside the text, so it names the text's
            // language itself: glyphs, quotation marks and line breaks follow the closest element
            // that names one, as they do on the line.
            if (typeof panel.setAttribute === 'function' && typeof panel.removeAttribute === 'function') {
                const scope = typeof this.$root?.closest === 'function' ? this.$root.closest('[lang]') : null;
                const lang = typeof scope?.getAttribute === 'function' ? (scope.getAttribute('lang') ?? '') : '';

                if (lang !== '') {
                    panel.setAttribute('lang', lang);
                } else {
                    panel.removeAttribute('lang');
                }
            }
        },

        /**
         * Write the words of the cut text in the trigger above the tooltip's own, or nothing while
         * that text fits. A badge with `truncate` and a tooltip marks its label so: a tooltip of the
         * label's own would open a second panel over this one. Read from the element each time the
         * panel shows, so the words are the ones on the line now, in the language they stand in.
         */
        _writeTheCutLead() {
            const place = this.$refs?.tooltip?.querySelector?.(CUT_LEAD_PLACE);
            if (!place) {
                return;
            }

            // The text in this tooltip's own trigger, never one in a tooltip nested inside it, which
            // leads that tooltip's panel.
            const trigger = this.$root?.querySelector?.(TRIGGER);
            const text = [...(this.$root?.querySelectorAll?.(CUT_LEAD) ?? [])]
                .find((element) => element.closest?.(TRIGGER) === trigger);
            const words = text && text.scrollWidth > text.clientWidth ? wordsOf(text) : '';

            if (place.textContent !== words) {
                place.textContent = words;
            }

            const scope = words !== '' && typeof text.closest === 'function' ? text.closest('[lang]') : null;
            const lang = typeof scope?.getAttribute === 'function' ? (scope.getAttribute('lang') ?? '') : '';

            if (lang !== '') {
                place.setAttribute('lang', lang);
            } else {
                place.removeAttribute('lang');
            }
        },

        destroy() {
            this._describedByObserver?.disconnect();
            this._describedByObserver = null;
            this._shownKeysObserver?.disconnect();
            this._shownKeysObserver = null;
            this._stopSingleKeysWatch?.();
            this._stopSingleKeysWatch = null;

            for (const observer of this._cutObservers) {
                observer.disconnect();
            }
            this._cutObservers = [];

            if (this._surroundingControl) {
                this._surroundingControl.removeEventListener('focusin', this._surroundingFocusIn);
                this._surroundingControl.removeEventListener('focusout', this._surroundingFocusOut);
                this._surroundingControl = null;
                this._surroundingAround = null;
            }

            if (this._navCleanup) {
                document.removeEventListener('livewire:navigating', this._navCleanup);
            }
            this._forceClose();
        },

        /**
         * Desktop mouse hover — show with delay.
         */
        mouseenter() {
            clearTimeout(this._hideTimer);

            // Guarded on `open`, because this handler is bound to the PANEL as well as
            // to the trigger — that pairing is what makes the tip hoverable, and WCAG
            // 1.4.13 asks for it. Moving the pointer onto an already-open panel has to
            // cancel the pending hide (the line above) and nothing else; arming a
            // second show timer there would only fire into a `show()` that returns at
            // its first line. Same shape as the hover card, for the same reason.
            if (! this.isOpen) {
                this._showTimer = setTimeout(() => this.show(), timerDelay(this._delayShow));
            }
        },

        /**
         * Desktop mouse leave — hide with delay.
         *
         * Bound to the trigger AND to the panel. The delay is what bridges the `offset`
         * gap between the two: leaving the trigger towards the panel arms this timer,
         * and the panel's own `mouseenter` clears it on the way in. Setting
         * `delay-hide="0"` therefore switches the hoverable behavior off — the panel
         * closes before the pointer can reach it.
         */
        mouseleave() {
            clearTimeout(this._showTimer);
            this._hideTimer = setTimeout(() => this.close(), timerDelay(this._delayHide));
        },

        /**
         * Keyboard focus — show immediately.
         *
         * Only a VISIBLE focus, the one a keyboard gives. Focus that a script moved, like the
         * first control a dialog focuses when it opens, showed the tooltip over the dialog with no
         * input at all, and it stayed for as long as the dialog was open. A pointer already shows
         * it on hover, so nothing is lost for a mouse.
         */
        focusin(event) {
            if (! this._focusIsVisible(event?.target)) {
                return;
            }

            clearTimeout(this._hideTimer);
            this.show();
        },

        _focusIsVisible(target) {
            if (! target || typeof target.matches !== 'function') {
                return true;
            }

            try {
                return target.matches(':focus-visible');
            } catch {
                // An engine without the pseudo-class shows the tooltip on every focus.
                return true;
            }
        },

        /**
         * Enter or Space on a trigger this component made a tab stop of its own, which is a
         * button: the tooltip shows at once, as activating a toggletip shows it. Focus shows it
         * already; this brings it back after Escape closed it while the focus stayed. The default
         * is prevented, so Space does not scroll the page.
         */
        activate(event) {
            event?.preventDefault?.();
            clearTimeout(this._showTimer);
            clearTimeout(this._hideTimer);
            this.show();
        },

        /**
         * Keyboard blur — hide immediately.
         */
        focusout() {
            clearTimeout(this._showTimer);
            this.close();

            // A cut text that kept its tab stop only because it held the focus gives it back once
            // the focus has left, if the text fits by then.
            if (this._whenTruncated) {
                this._measureTheCut();
            }
        },

        /**
         * Touch long-press start — begin 500ms timer.
         * Only triggers on touch devices (pointerType === 'touch').
         */
        pointerdown(e) {
            if (e.pointerType !== 'touch') return;
            this._longPressTimer = setTimeout(() => {
                this.show();
            }, 500);
        },

        /**
         * Touch long-press end — clear timer, auto-dismiss after 1.5s.
         */
        pointerup(e) {
            if (e.pointerType !== 'touch') return;
            clearTimeout(this._longPressTimer);

            if (this.isOpen) {
                // Auto-dismiss tooltip after 1.5 seconds on touch
                this._autoDismissTimer = setTimeout(() => this.close(), 1500);
            }
        },

        /**
         * Touch pointer leaves — cancel long-press timer.
         */
        pointerleave(e) {
            if (e.pointerType !== 'touch') return;
            clearTimeout(this._longPressTimer);
        },

        /**
         * Escape anywhere: hide the tooltip and clear every pending timer.
         *
         * Heard on the WINDOW, not on the component, because a tooltip is normally
         * opened by a pointer and a pointer moves no focus: a key bound inside the
         * component never saw the keystroke that was meant for it. WCAG 1.4.13 names
         * Escape as the way hover-shown content is dismissed without moving the
         * pointer or the focus.
         *
         * Heard in the CAPTURE phase, and a tooltip on screen marks the press as
         * handled. A modal or a drawer around the reader reads Escape later, on the
         * document and on the window, and leaves a marked press alone: the reader
         * asked the tooltip to go, and nothing else. A tooltip only shows for what the
         * reader points at or stands on, so nothing on screen sits above it to take
         * the key first.
         *
         * Clearing the timers is not housekeeping here — it is the point. A pending
         * `delayShow` that survives Escape puts the panel on screen a moment AFTER
         * the key that was meant to answer it. A press that only cleared a pending
         * show stays unmarked: nothing was on screen for the reader to dismiss.
         *
         * @param {KeyboardEvent} [event]
         */
        keydownEscape(event) {
            // An Escape that abandons an input method's conversion belongs to the input method.
            if (isComposing(event)) return;

            if (this.isOpen) {
                event?.preventDefault();
            }

            this._clearAllTimers();
            this.close();
        },

        /**
         * Is this tooltip switched off right now?
         *
         * Read off the ROOT ATTRIBUTE rather than held as state, and that is the
         * whole design. A boolean passed into the factory would only ever answer
         * for the render that created it — but the case this exists for is a
         * tooltip that must go quiet when something else on the page changes,
         * a collapsing sidebar being the concrete case. Reading the attribute
         * at trigger time means `x-bind:data-wk-tooltip-disabled` from the
         * call site works reactively with no further API at all.
         *
         * `pointer-events-none` on the root is NOT a way to do this, however
         * much it looks like one: `mouseenter` is delivered to every ancestor of
         * the element actually hit, whatever their own pointer-events value, so
         * the handler fires and the tooltip appears over a control that is
         * supposed to be inert.
         */
        _disabled() {
            return this.$root?.getAttribute('data-wk-tooltip-disabled') === 'true';
        },

        /**
         * Show tooltip and position via Floating UI.
         */
        async show() {
            if (this.isOpen) return;

            // Checked HERE as well as in the handlers, because show() is public:
            // it is reachable from a call site that dispatches it directly, and a
            // switch that only guards the doors it knows about is not a switch.
            if (this._disabled()) return;

            if (this._whenTruncated) {
                // A text that fits has nothing to repeat.
                if (!this._isTruncated()) return;

                // Its words go in before the panel shows, as its colors do below.
                this._writeTheCutText();
            }

            // A cut text in the trigger leads the panel with its words, before the panel shows.
            this._writeTheCutLead();

            // Color the panel before it is shown, not after.
            //
            // `open = true` flips x-show, which sets display immediately. A copy
            // after the $nextTick that follows would leave a window in which the
            // panel is displayed and still carries the default color, observable
            // by anything that reads a shown panel, which makes the contract
            // untestable without a race.
            //
            // The panel is teleported by `<template x-teleport>` at init and
            // only display-toggled afterwards, so the ref is already there —
            // but it is guarded rather than assumed, and the copy after the
            // tick stays as the fallback for a first show that resolves late.
            // Running it twice costs nothing: it reads computed style and
            // writes the same values.
            if (this.$refs.tooltip) {
                this._inheritThemeVars(this.$refs.tooltip);
            }

            this.isOpen = true;

            await this.$nextTick();

            const trigger = this.$refs.trigger;
            const tooltip = this.$refs.tooltip;

            if (trigger && tooltip) {
                this._inheritThemeVars(tooltip);

                // Drop the previous showing's observer before making another one. A tooltip is
                // shown and hidden constantly, so one left behind per hover adds up fastest here
                // of anywhere in the catalog.
                this._stopRepair?.();
                this._stopRepair = null;

                const placement = await position(placementReference(trigger), tooltip, {
                    placement: this._placement,
                    offset: this._offset,

                    // A tooltip looks like the one overlay that cannot need this, and it does.
                    //
                    // Everything this call writes is inline style, and a framework update patches
                    // the panel against its own template, whose `style` attribute carries none of
                    // it. The placement is then gone while the tooltip is still open, and since
                    // this panel is teleported to the overlay root, with no `top` it sits at the
                    // END of the document.
                    //
                    // The obvious objection is that a tooltip lasts a moment, so an update can
                    // hardly catch one. That holds for the pointer, and not at all for the
                    // keyboard: a tooltip opened by focus stands for as long as the focus does,
                    // which on a form is minutes, and one refresh in that time erases `top`
                    // while the tooltip stays visible and focus stays on the trigger.
                    //
                    // That is an accessibility path rather than a cosmetic one. The trigger's
                    // `aria-describedby` still points at this panel, so a screen reader is
                    // describing a control with a box that is now nowhere near it.
                    //
                    // The same update removes the colors `_inheritThemeVars()` copied onto the
                    // panel, since they sit in that `style` attribute too. The callback copies
                    // them again before the placement is put back, so a tooltip restyled on its
                    // wrapper keeps its colors across the update.
                    //
                    // The panel of a cut text loses its words in that update too, since this
                    // component writes them and the server does not, so they go back in as well.
                    repairErasure: (panel) => this._restoreThePanel(panel),
                    // It also follows the trigger: the placement is viewport-relative (`fixed`),
                    // so a scroll while this is open would otherwise leave it where the trigger
                    // was. The same `stop()` ends both observers.
                    autoReposition: true,
                    // Without this a `placement="right"` tooltip runs off the
                    // right edge of a phone and stays there. Floating UI's
                    // default shift only moves along the placement's MAIN axis,
                    // which for left/right is vertical — so nothing pulls it
                    // back horizontally, and `flip` gives up when both sides
                    // overflow, which on a 375px viewport they do.
                    // The sibling overlays (popover, navigation-menu, filter-
                    // builder, color-picker, event-calendar, notification-
                    // center) pass it too.
                    crossAxisShift: true,
                });

                if (placement && typeof placement.stop === 'function') {
                    if (this.isOpen) {
                        this._stopRepair = placement.stop;
                    } else {
                        // Hidden while the placement was in flight — its observer would outlive
                        // the panel it belongs to, and on a tooltip that race is the common case
                        // rather than the exotic one.
                        placement.stop();
                    }
                }
            }
        },

        /**
         * Put back what a framework update took off the panel while it was open: the colors
         * copied onto it and, on a cut text, its words.
         */
        _restoreThePanel(panel) {
            this._inheritThemeVars(panel);

            if (this._whenTruncated) {
                this._writeTheCutText();
            }

            this._writeTheCutLead();
        },

        /**
         * Carry the tooltip's themeable variables onto the teleported panel.
         *
         * The documented way to restyle one tooltip is an inline `style` on the component,
         * which sets `--color-wk-tooltip-bg` / `-text` on the trigger wrapper; the panel read
         * them by INHERITANCE, as a descendant.
         *
         * Teleporting the panel to `<body>` — which it needs, so a masking or clipping
         * ancestor cannot cut it off — ends that descent. The panel keeps rendering, with the
         * theme defaults, and every per-tooltip override silently stops working. The whole
         * "Styling Variants" section of the documentation was showing six identical tooltips.
         *
         * So the values are copied explicitly. Read from the WRAPPER with `getComputedStyle`,
         * which resolves whatever actually applies there — an inline style, a class, a scoped
         * theme — rather than reading the inline attribute and missing the other two routes.
         *
         * Copied on every show rather than once at init: a developer may change the variable
         * at runtime, and a value captured at mount would then be quietly stale.
         */
        _inheritThemeVars(tooltip) {
            const source = getComputedStyle(this.$el);

            for (const name of ['--color-wk-tooltip-bg', '--color-wk-tooltip-text', '--size-wk-tooltip-max']) {
                const value = source.getPropertyValue(name).trim();

                if (value !== '') {
                    tooltip.style.setProperty(name, value);
                }
            }
        },

        /**
         * Hide tooltip and clear all pending timers.
         */
        close() {
            this.isOpen = false;
            this._stopRepair?.();
            this._stopRepair = null;
            this._clearAllTimers();
        },

        /**
         * Clear all pending timers — prevents stale callbacks from reopening.
         */
        _clearAllTimers() {
            clearTimeout(this._showTimer);
            clearTimeout(this._hideTimer);
            clearTimeout(this._longPressTimer);
            clearTimeout(this._autoDismissTimer);
        },

        /**
         * Force close — used during SPA cleanup.
         */
        _forceClose() {
            this.isOpen = false;
            this._stopRepair?.();
            this._stopRepair = null;
            this._clearAllTimers();
        },
    });
}
