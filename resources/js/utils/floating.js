/**
 * Floating UI wrapper for WireKit overlay positioning.
 *
 * Provides a simplified API around @floating-ui/dom for dropdown and tooltip
 * positioning with automatic flip and shift middleware.
 */
import { computePosition, flip, shift, limitShift, offset as offsetMiddleware } from '@floating-ui/dom';

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
 * @returns {Promise<{x: number, y: number, placement: string}>}
 */
export async function position(reference, floating, { placement = 'bottom-start', offset = 8, strategy = 'fixed', crossAxisShift = false } = {}) {
    const result = await computePosition(reference, floating, {
        strategy,
        placement,
        middleware: [
            offsetMiddleware(offset),
            flip({ padding: 8 }),
            // crossAxis:false + no limiter === the original `shift({ padding: 8 })`,
            // so existing callers (dropdown, tooltip) are unaffected.
            shift({
                crossAxis: crossAxisShift,
                limiter: crossAxisShift ? limitShift() : undefined,
                padding: 8,
            }),
        ],
    });

    Object.assign(floating.style, {
        left: `${result.x}px`,
        top: `${result.y}px`,
    });

    return result;
}
