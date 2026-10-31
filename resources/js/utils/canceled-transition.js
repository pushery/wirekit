/**
 * A hide that a show cancels, handled where it happens.
 *
 * Alpine hides an `x-show` element that has a transition through a promise, and a show that
 * arrives during the leave transition cancels it: the promise rejects with
 * `{ isFromCancelledTransition: true }`. Alpine treats that rejection as expected and catches it,
 * but it attaches the handler one frame later, in a `requestAnimationFrame`. A show inside that
 * first frame, such as a panel opened again by the keyboard right after it closed, rejects the
 * promise while no handler exists, and the browser reports an unhandled rejection: the console
 * reads "Uncaught (in promise)", and an error monitor or a test that listens for unhandled
 * rejections counts an error the page never made.
 *
 * Alpine calls `_x_toggleAndCascadeWithTransitions` on `Element.prototype` for every `x-show`
 * toggle, looked up each time. Wrapped, it gives each hide promise a handler as the promise is
 * made. Alpine's own handler still observes the promise a frame later and throws any error that is
 * not a canceled transition, so nothing else goes quiet.
 */

/**
 * Wrap Alpine's toggle once. A second bundle, or a second call, leaves the first wrapper in place,
 * and a page without Alpine's method is left alone.
 *
 * @param {object} [win] The window whose `Element.prototype` Alpine extended.
 */
export function installCanceledTransitionHandler(win = typeof window === 'undefined' ? undefined : window) {
    const proto = win?.Element?.prototype;
    const toggle = proto?._x_toggleAndCascadeWithTransitions;

    if (typeof toggle !== 'function' || toggle._wkHandlesCancel === true) {
        return;
    }

    const handled = function (el, value, show, hide) {
        const result = toggle.call(this, el, value, show, hide);
        const hiding = el?._x_hidePromise;

        if (hiding && typeof hiding.catch === 'function') {
            hiding.catch(() => {});
        }

        return result;
    };

    handled._wkHandlesCancel = true;
    proto._x_toggleAndCascadeWithTransitions = handled;
}
