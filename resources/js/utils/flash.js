/**
 * `x-wk-flash`: a row lights up in a tone when it changes, driven by the server.
 *
 * Reported from a till: every change to a line in the basket should show on that line for a
 * moment, green for an addition, amber for one fewer, red for a removal, and a removed line should
 * leave only after its red. The application built it from two identical keyframes and an attribute
 * it flipped between them, because a CSS animation restarts only when its NAME changes: without
 * the flip, a line changed twice in a row lit up once.
 *
 * THE SERVER WRITES THREE ATTRIBUTES AND NOTHING ELSE:
 *
 *   - `data-wk-flash`: the tone, one of the intents (success, warning, danger, info, primary,
 *     neutral). Its tint is a token, `--color-wk-flash-{tone}`.
 *   - `data-wk-flash-key`: a value that changes with every change to report, a counter or a time.
 *     A new key plays the flash again, whatever the tone was before.
 *   - `data-wk-flash-leave`: the row is on its way out. It is drawn once more, flashes, and is
 *     taken out of the page when the flash ends.
 *
 * WHY AN ATTRIBUTE PLUS AN OBSERVER, the reasoning `x-wk-indeterminate` gives: the value comes
 * from the server, a Livewire render writes it onto the element that is already there, and Alpine
 * does not initialize an element again. A mutation on an attribute is true whoever wrote it, so
 * this also works in a page without Livewire.
 *
 * WHY THE WEB ANIMATIONS API and not a keyframe class: an animation started from script starts
 * every time it is asked to, so the second change of a row lights it up again without a second
 * set of keyframes. A table row is painted by its cells, and a cell with a background of its own
 * (a frozen column, a striped row) would cover the row's tint, so for a `<tr>` the cells flash.
 *
 * REDUCED MOTION keeps the signal and drops the movement: the tint appears at once, stays for the
 * same time and is gone at once, with no fade. The change is still there to see.
 *
 * Lifecycle: one MutationObserver per element, disconnected in the directive's cleanup.
 */

import { prefersReducedMotion } from './motion.js';
export const WK_FLASH_ATTRIBUTE = 'data-wk-flash';
export const WK_FLASH_KEY_ATTRIBUTE = 'data-wk-flash-key';
export const WK_FLASH_LEAVE_ATTRIBUTE = 'data-wk-flash-leave';

export const WK_FLASH_TONES = ['success', 'warning', 'danger', 'info', 'primary', 'neutral'];

/** The animations each element is running, so a new flash replaces the last one. */
const running = new WeakMap();

/** `600ms` or `0.6s` as milliseconds; the fallback when the token cannot be read. */
function milliseconds(value, fallback) {
    const text = String(value ?? '').trim();
    const number = parseFloat(text);

    if (! Number.isFinite(number)) {
        return fallback;
    }

    return text.endsWith('ms') ? number : number * 1000;
}

/** Play the flash the element's attributes describe. Exported for the unit test. */
export function playFlash(el) {
    const tone = el.getAttribute(WK_FLASH_ATTRIBUTE);

    if (! WK_FLASH_TONES.includes(tone) || typeof el.animate !== 'function') {
        return null;
    }

    const style = getComputedStyle(el);
    const color = style.getPropertyValue(`--color-wk-flash-${tone}`).trim();

    if (color === '') {
        return null;
    }

    const duration = milliseconds(style.getPropertyValue('--motion-wk-duration-slow'), 600);
    const easing = style.getPropertyValue('--motion-wk-easing-out').trim() || 'ease-out';
    const reduce = prefersReducedMotion();

    // A row is painted by its cells, so the cells carry the tint.
    const targets = el.tagName === 'TR' ? [...el.children] : [el];

    for (const animation of running.get(el) ?? []) {
        animation.cancel();
    }

    const animations = targets.map((target) => target.animate(
        // Reduced motion: the tint holds for the whole duration and ends at once. Otherwise the
        // single keyframe fades to whatever the target paints on its own.
        reduce ? [{ backgroundColor: color }, { backgroundColor: color }] : [{ backgroundColor: color }],
        { duration, easing: reduce ? 'linear' : easing },
    ));

    running.set(el, animations);

    if (el.hasAttribute(WK_FLASH_LEAVE_ATTRIBUTE)) {
        // Out of the way while it goes: nothing on a leaving row can be pressed any more.
        el.setAttribute('aria-hidden', 'true');
        el.inert = true;

        Promise.all(animations.map((animation) => animation.finished))
            .then(() => el.remove())
            .catch(() => {
                // Canceled by a newer flash of the same row, which takes over its exit.
            });
    }

    return animations;
}

/**
 * Register the `x-wk-flash` directive. The first flash plays when Alpine initializes the element,
 * which is also when a row the server just added appears; every later one comes from the observer.
 */
export function registerFlashDirective(Alpine) {
    Alpine.directive('wk-flash', (el, directive, { cleanup }) => {
        let lastKey = el.getAttribute(WK_FLASH_KEY_ATTRIBUTE);

        if (lastKey !== null && lastKey !== '') {
            playFlash(el);
        }

        if (typeof MutationObserver === 'undefined') {
            return;
        }

        const observer = new MutationObserver(() => {
            const key = el.getAttribute(WK_FLASH_KEY_ATTRIBUTE);

            // A new key is a new change. The tone alone changing without a new key reports nothing.
            if (key !== null && key !== '' && key !== lastKey) {
                lastKey = key;
                playFlash(el);
            }
        });

        observer.observe(el, { attributes: true, attributeFilter: [WK_FLASH_KEY_ATTRIBUTE, WK_FLASH_LEAVE_ATTRIBUTE] });

        cleanup(() => observer.disconnect());
    });
}
