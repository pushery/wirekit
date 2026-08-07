/**
 * Floating UI wrapper for WireKit overlay positioning.
 *
 * Provides a simplified API around @floating-ui/dom for dropdown and tooltip
 * positioning with automatic flip and shift middleware.
 */
import { computePosition, autoUpdate, flip, shift, limitShift, size, offset as offsetMiddleware } from '@floating-ui/dom';

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
 *   for the same reason as `crossAxisShift`: eleven components call this helper
 *   and their current geometry must not move.
 * @param {number} options.minHeight - Floor for `fitViewport`. Below this the
 *   panel stops shrinking and is allowed to overflow, because a 40px-tall menu
 *   that scrolls is worse than one that reaches past the fold.
 * @param {boolean} options.matchReferenceWidth - Set the panel's width from the
 *   reference element. A panel positioned `absolute` inside its field wrapper
 *   inherits the field's width through `w-full`; one positioned `fixed` (which
 *   is what lets it escape a clipping ancestor) has no such parent, so the width
 *   has to be carried over explicitly.
 * @param {boolean} options.autoReposition - Keep the panel pinned to its trigger
 *   while it is open: re-run the SAME middleware pipeline on scroll, resize, and
 *   ancestor-scroll via Floating UI's `autoUpdate`. Opt-in (default `false`, like
 *   `fitViewport`/`crossAxisShift`) so existing callers stay byte-identical. When
 *   enabled, `position()` resolves with a `stop` cleanup function on the result —
 *   the caller MUST call it on close/destroy or the scroll/resize listeners leak
 *   (the caller owns teardown). No `animationFrame` option: the default
 *   scroll+resize listeners are cheap; a per-frame rAF loop would burn CPU here.
 * @returns {Promise<{x: number, y: number, placement: string, stop?: () => void}>}
 */
export async function position(reference, floating, {
    placement = 'bottom-start',
    offset = 8,
    strategy = 'fixed',
    crossAxisShift = false,
    fitViewport = false,
    minHeight = 120,
    matchReferenceWidth = false,
    autoReposition = false,
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

    if (fitViewport || matchReferenceWidth) {
        // AFTER flip on purpose: `availableHeight` describes the placement that
        // was actually chosen. Measured before the flip it would report the room
        // on the side floating-ui just rejected, and the cap would be wrong in
        // exactly the situation the cap exists for.
        middleware.push(size({
            padding: 8,
            apply({ availableHeight, rects, elements }) {
                if (fitViewport) {
                    elements.floating.style.maxHeight = `${Math.max(availableHeight, minHeight)}px`;
                }

                if (matchReferenceWidth) {
                    elements.floating.style.width = `${rects.reference.width}px`;
                }
            },
        }));
    }

    // ONE code path for the initial placement AND every autoUpdate tick, so the
    // first paint and the repositioned geometry never drift — important because the
    // `size`/`matchReferenceWidth` middleware mutate inline styles on every run.
    const run = async () => {
        // A panel with no box yet is measured as 0x0, and every placement that
        // subtracts the panel's own size then lands one panel-width off. This is
        // not hypothetical: the data table's column menu opened at x 290..482 in
        // a 375px viewport, because `bottom-end` computes
        // `reference.left + reference.width - floating.width` and the width it
        // subtracted was zero — 194 + 96 - 0 = 290, to the pixel.
        //
        // It reproduces in WebKit and not in Blink. Alpine's `$nextTick` fires
        // after the DOM mutation, which is enough for Blink to have laid the
        // panel out but not always for WebKit, so callers that anchor from
        // `$nextTick` (the documented, correct thing to do) still measure
        // nothing. Every engine can hit it; only one of them does so reliably,
        // which is why it shipped.
        //
        // Waiting for a frame is the whole fix, and it costs nothing in the
        // normal case: a panel that already has a box never enters the loop.
        // The cap keeps a legitimately zero-width panel from waiting forever.
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

    const result = await run();

    if (! autoReposition) {
        return result;
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
    // on a clean console — which is how two of the defects in this release were reported
    // in the first place. They would get a red run from our component with nothing they
    // could do about it.
    //
    // A frame's delay is invisible for a panel that is following its trigger, and it
    // moves the write out of the observer's callback, which is the whole cause.
    let queued = 0;

    const stop = autoUpdate(reference, floating, () => {
        if (queued) {
            return;
        }

        queued = requestAnimationFrame(() => {
            queued = 0;
            run();
        });
    });

    return { ...result, stop };
}
