/**
 * Floating UI wrapper for WireKit overlay positioning.
 *
 * Provides a simplified API around @floating-ui/dom for dropdown and tooltip
 * positioning with automatic flip and shift middleware.
 */
import { computePosition, autoUpdate, flip, shift, limitShift, size, offset as offsetMiddleware } from '@floating-ui/dom';

/**
 * Is focus already inside `container`?
 *
 * Every overlay that places focus after awaiting `position()` needs this answer first. The panel
 * is visible and operable from the frame Alpine reveals it, and positioning resolves later, so a
 * reader who moves into the panel in between, with the keyboard, a screen reader or a script,
 * would otherwise have focus taken back to wherever the component meant to put it.
 *
 * Guarded rather than assumed: a unit harness does not have to provide `document`, and a panel
 * that is gone by the time a promise resolves is no reason to throw.
 *
 * @param {Element|null|undefined} container
 * @returns {boolean}
 */
export function focusIsWithin(container) {
    const active = typeof document !== 'undefined' ? document.activeElement : null;

    if (! container || ! active || typeof container.contains !== 'function') {
        return false;
    }

    return container.contains(active);
}

/**
 * Lift a panel above the dialog its trigger stands in.
 *
 * Every panel teleports into the one overlay root, where it stacks against the modals and drawers
 * that live there too by z-index alone: a dropdown at `--z-wk-dropdown` and a tooltip at
 * `--z-wk-tooltip` sit below a dialog at `--z-wk-modal`. For a panel of the page that is right. For
 * one opened from inside the dialog it is not: the dialog's own layer would cover it, and a click on
 * a dropdown entry would land on that layer, which closes the dialog.
 *
 * So the panel is lifted just above the highest positioned ancestor of its trigger that stands at
 * dialog level or above, a tooltip one step higher than the rest, so that inside a dialog the two
 * keep the order they have on the page. A panel of the page finds no such ancestor and keeps its
 * own layer; a panel opened from a panel that was lifted is lifted above that one in turn.
 *
 * @param {HTMLElement} reference
 * @param {HTMLElement} floating
 */
export function liftAboveTriggerDialog(reference, floating) {
    if (! reference || ! floating || typeof getComputedStyle !== 'function') {
        return;
    }

    // The panel's own layer, from its classes: a previous lift is cleared before it is read.
    floating.style.zIndex = '';

    const tokens = getComputedStyle(document.documentElement);
    const dialogLevel = parseInt(tokens.getPropertyValue('--z-wk-modal'), 10);
    const tooltipLevel = parseInt(tokens.getPropertyValue('--z-wk-tooltip'), 10);
    const own = parseInt(getComputedStyle(floating).zIndex, 10);

    if (! Number.isFinite(dialogLevel)) {
        return;
    }

    let layer = null;

    for (let el = reference.parentElement; el && el !== document.body; el = el.parentElement) {
        const style = getComputedStyle(el);
        const z = parseInt(style.zIndex, 10);

        if (style.position !== 'static' && Number.isFinite(z) && z >= dialogLevel && (layer === null || z > layer)) {
            layer = z;
        }
    }

    if (layer === null) {
        return;
    }

    const step = Number.isFinite(own) && Number.isFinite(tooltipLevel) && own >= tooltipLevel ? 2 : 1;
    floating.style.zIndex = String(layer + step);
}

/**
 * Position a floating element relative to a reference element.
 *
 * @param {HTMLElement} reference - The trigger/anchor element
 * @param {HTMLElement} floating - The floating panel element
 * @param {Object} options - Positioning options
 * @param {string} options.placement - Floating UI placement (e.g. 'bottom-start')
 * @param {number} options.offset - Distance in px between reference and floating
 * @param {boolean} options.crossAxisShift - Also shift along the CROSS axis to
 *   keep the panel inside the viewport. Floating UI's default `shift()` only
 *   shifts along the placement's MAIN axis — which for `left`/`right`
 *   placements is the Y (vertical) axis, so a `right`-placed panel that
 *   overflows the RIGHT viewport edge is never pulled back horizontally and
 *   relies solely on `flip` (which also fails when BOTH sides overflow on a
 *   narrow viewport). Opt-in (default `false`) so dropdown / tooltip
 *   positioning stays byte-identical; popover passes `true` because it is the
 *   overlay that supports explicit left/right placement. A `limitShift()`
 *   limiter prevents the panel from over-shifting off its anchor.
 * @param {boolean} options.fitViewport - Cap the panel's height to the space
 *   actually available and let it scroll instead of overflowing. Without it a
 *   panel taller than the room below its trigger is pinned to the viewport edge
 *   by `shift` and then CLIPPED by the panel's own `overflow-hidden`, so the
 *   entries at the top — usually the important ones — simply disappear. Opt-in
 *   for the same reason as `crossAxisShift`: most of the overlay family calls
 *   this helper and their current geometry must not move.
 * @param {number} options.minHeight - Floor for `fitViewport`. Below this the
 *   panel stops shrinking and is allowed to overflow, because a menu too short to
 *   show two entries is worse than one that reaches past the fold. Two rows, not
 *   more: a higher floor would push a panel past the edge of a fixed layer on a
 *   short viewport (a phone held sideways, a page zoomed to 400%) while it still
 *   had room to scroll, and neither scrolling the page nor moving the focus
 *   would bring the rest into view (WCAG 1.4.10).
 * @param {boolean} options.matchReferenceWidth - Set the panel's width from the
 *   reference element. A panel positioned `absolute` inside its field wrapper
 *   inherits the field's width through `w-full`; one positioned `fixed` (which
 *   is what lets it escape a clipping ancestor) has no such parent, so the width
 *   has to be carried over explicitly.
 * @param {boolean} options.minReferenceWidth - Let the panel be WIDER than its reference but
 *   never narrower, and never wider than the viewport allows. For a panel that sizes itself by
 *   its content or by a width its caller chose: it starts at the field's width, grows to what it
 *   asks for, and is capped at the room the chosen placement leaves. Opt-in like the options
 *   above; `matchReferenceWidth` wins when both are set, since an exact width leaves nothing to
 *   bound.
 * @param {boolean} options.autoReposition - Keep the panel pinned to its trigger
 *   while it is open: re-run the SAME middleware pipeline on scroll, resize, and
 *   ancestor-scroll via Floating UI's `autoUpdate`. Opt-in (default `false`, like
 *   `fitViewport`/`crossAxisShift`) so existing callers stay byte-identical. When
 *   enabled, `position()` resolves with a `stop` cleanup function on the result —
 *   the caller MUST call it on close/destroy or the scroll/resize listeners leak
 *   (the caller owns teardown). No `animationFrame` option: the default
 *   scroll+resize listeners are cheap; a per-frame rAF loop would burn CPU here.
 * @param {boolean|function(HTMLElement): void} options.repairErasure - Put the placement back when something REMOVES it.
 *   A framework that re-renders the panel patches it against its own template, whose `style`
 *   attribute carries none of what this function writes — so `top`, `left`, the width and the
 *   height cap all disappear at once, while the state that opened the panel never changed and
 *   nothing asks for a new placement. A `fixed` element with no `top` then sits at its static
 *   position, which for a teleported panel is the end of the document.
 *
 *   `autoReposition` does not cover this. `autoUpdate` recomputes when something it observes
 *   changes, and it observes the two elements' boxes — so it repairs an erasure only where the
 *   erasure also resizes the panel. A menu whose `max-height` was never binding keeps its box when
 *   `top` and `max-height` are erased, so no observer fires and the placement stays gone.
 *
 *   This option watches the `style` attribute instead, which is the thing that is actually taken
 *   away. The mutation record arrives with `top` already empty when the callback runs, early
 *   enough to put it back.
 *
 *   It re-places ONLY when `top` is empty, and that condition is the termination proof rather
 *   than an optimization: a write from inside the callback re-enters the observer, so an
 *   unconditional repair loops forever. Writing a `top`
 *   makes the re-entrant run see a placed panel and stop, after exactly one extra pass.
 *
 *   Opt-in, like the options above, so no existing caller changes behavior. Like `autoReposition`
 *   it returns a `stop` the caller owns.
 *
 *   A function instead of `true` is called with the panel before the placement is put back, for a
 *   caller that writes inline style of its own besides the placement. The same update removes that
 *   too: a tooltip copies its colors onto its teleported panel, and without the callback it would
 *   come back placed but in the default colors. The emptiness test still ends it: a write from the
 *   callback re-enters the observer like any other, and every pass ends in a `run()` that writes
 *   `top`.
 * @returns {Promise<{x: number, y: number, placement: string, stop?: () => void}>}
 */
export async function position(reference, floating, {
    placement = 'bottom-start',
    offset = 8,
    strategy = 'fixed',
    crossAxisShift = false,
    fitViewport = false,
    minHeight = 48,
    matchReferenceWidth = false,
    minReferenceWidth = false,
    autoReposition = false,
    repairErasure = false,
} = {}) {
    const middleware = [
        offsetMiddleware(offset),
        flip({ padding: 8 }),
        // crossAxis:false + no limiter === the original `shift({ padding: 8 })`,
        // so existing callers (dropdown, tooltip) are unaffected.
        shift({
            crossAxis: crossAxisShift,
            limiter: crossAxisShift ? limitShift() : undefined,
            padding: 8,
        }),
    ];

    if (fitViewport || matchReferenceWidth || minReferenceWidth) {
        // After flip on purpose: `availableHeight` describes the placement that
        // was actually chosen. Computed before the flip it would report the room
        // on the side floating-ui just rejected, and the cap would be wrong in
        // exactly the situation the cap exists for.
        middleware.push(size({
            padding: 8,
            apply({ availableHeight, availableWidth, rects, elements }) {
                if (fitViewport) {
                    elements.floating.style.maxHeight = `${Math.max(availableHeight, minHeight)}px`;
                }

                if (matchReferenceWidth) {
                    elements.floating.style.width = `${rects.reference.width}px`;
                } else if (minReferenceWidth) {
                    elements.floating.style.minWidth = `${rects.reference.width}px`;
                    elements.floating.style.maxWidth = `${Math.max(availableWidth, rects.reference.width)}px`;
                }
            },
        }));
    }

    // ONE code path for the initial placement AND every autoUpdate tick, so the
    // first paint and the repositioned geometry never drift — important because the
    // `size`/`matchReferenceWidth` middleware mutate inline styles on every run.
    const run = async () => {
        // A panel with no box yet reads as 0x0, and every placement that
        // subtracts the panel's own size then lands one panel-width off:
        // `bottom-end` computes `reference.left + reference.width - floating.width`,
        // and with a width of zero the panel starts at the trigger's right edge.
        //
        // Alpine's `$nextTick` fires after the DOM mutation, which is enough for
        // Blink to have laid the panel out but not always for WebKit, so callers
        // that anchor from `$nextTick` (the documented, correct thing to do) can
        // still read nothing. Waiting up to three frames costs nothing in the
        // normal case: a panel that already has a box never enters the loop, and
        // the cap keeps a legitimately zero-width panel from waiting forever.
        for (let frame = 0; frame < 3 && floating.getBoundingClientRect().width === 0; frame++) {
            await new Promise((resolve) => requestAnimationFrame(resolve));
        }

        const result = await computePosition(reference, floating, {
            strategy,
            placement,
            middleware,
        });

        Object.assign(floating.style, {
            left: `${result.x}px`,
            top: `${result.y}px`,
        });

        return result;
    };

    liftAboveTriggerDialog(reference, floating);

    const result = await run();

    // Put the placement back when something takes it away. See the option's docblock for why
    // `autoReposition` cannot do this and why the emptiness test is what makes it terminate.
    let stopRepair = null;

    if (repairErasure && typeof MutationObserver === 'function') {
        const repair = new MutationObserver(() => {
            if (floating.style.top !== '') {
                return;
            }

            if (typeof repairErasure === 'function') {
                repairErasure(floating);
            }

            run();
        });

        repair.observe(floating, { attributes: true, attributeFilter: ['style'] });
        stopRepair = () => repair.disconnect();
    }

    if (! autoReposition) {
        return stopRepair ? { ...result, stop: stopRepair } : result;
    }

    // Follow the trigger on scroll / resize / ancestor-scroll. autoUpdate also
    // fires `run` once immediately (a harmless recompute of what we just placed);
    // the returned `stop` is the caller's teardown handle — call it on close.
    //
    // The recompute is deferred to the next frame, and that is not a micro-optimization.
    // autoUpdate observes with a ResizeObserver, and `run` writes the panel's position —
    // a write, inside the observer's own callback, that the browser can see as another
    // resize. When something else resizes the page in the same frame, which a Livewire
    // morph does, the browser reports `ResizeObserver loop completed with undelivered
    // notifications`.
    //
    // Nothing renders wrong, and that is exactly why it matters: it is a console error,
    // and the developers most likely to meet it are the ones gating their browser suite
    // on a clean console. They would get a red run from our component with nothing they
    // could do about it.
    //
    // A frame's delay is invisible for a panel that is following its trigger, and it
    // moves the write out of the observer's callback, which is the whole cause.
    let queued = 0;

    const stopAutoUpdate = autoUpdate(reference, floating, () => {
        if (queued) {
            return;
        }

        queued = requestAnimationFrame(() => {
            queued = 0;
            run();
        });
    });

    // The frame outlives the teardown unless it is canceled, because the recompute above
    // is deferred. `autoUpdate`'s own stop detaches the observers and knows
    // nothing about a frame we queued ourselves — so a panel closed between the observer
    // firing and the frame running gets one more `run()`: a `computePosition` against a
    // reference that may be detached, and a style write onto an element the caller has
    // already finished with.
    // So `stop` cancels the frame as well; `stopAutoUpdate` alone would leave it running.
    const stop = () => {
        stopAutoUpdate();
        stopRepair?.();

        if (queued) {
            cancelAnimationFrame(queued);
            queued = 0;
        }
    };

    return { ...result, stop };
}
