/**
 * Floating UI wrapper for WireKit overlay positioning.
 *
 * Provides a simplified API around @floating-ui/dom for dropdown and tooltip
 * positioning with automatic flip and shift middleware.
 */
import { computePosition, flip, shift, limitShift, size, offset as offsetMiddleware } from '@floating-ui/dom';

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
 * @returns {Promise<{x: number, y: number, placement: string}>}
 */
export async function position(reference, floating, {
    placement = 'bottom-start',
    offset = 8,
    strategy = 'fixed',
    crossAxisShift = false,
    fitViewport = false,
    minHeight = 120,
    matchReferenceWidth = false,
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
}
