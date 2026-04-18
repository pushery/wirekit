/**
 * Floating UI wrapper for WireKit overlay positioning.
 *
 * Provides a simplified API around @floating-ui/dom for dropdown and tooltip
 * positioning with automatic flip and shift middleware.
 */
import { computePosition, flip, shift, offset as offsetMiddleware } from '@floating-ui/dom';

/**
 * Position a floating element relative to a reference element.
 *
 * @param {HTMLElement} reference - The trigger/anchor element
 * @param {HTMLElement} floating - The floating panel element
 * @param {Object} options - Positioning options
 * @param {string} options.placement - Floating UI placement (e.g. 'bottom-start')
 * @param {number} options.offset - Distance in px between reference and floating
 * @returns {Promise<{x: number, y: number, placement: string}>}
 */
export async function position(reference, floating, { placement = 'bottom-start', offset = 8, strategy = 'fixed' } = {}) {
    const result = await computePosition(reference, floating, {
        strategy,
        placement,
        middleware: [
            offsetMiddleware(offset),
            flip({ padding: 8 }),
            shift({ padding: 8 }),
        ],
    });

    Object.assign(floating.style, {
        left: `${result.x}px`,
        top: `${result.y}px`,
    });

    return result;
}
