/**
 * `<x-wirekit::image-hotspots>`: the marker pressed last, the event it sends, and the labels that
 * Escape hides.
 *
 * Positions, labels and the strip of targets are the stylesheet's, so the component needs no script
 * to show any of them. This holds what markup cannot: which marker was pressed last, whose card the
 * strip on the phone marks, and which labels the reader dismissed.
 *
 * A pressed marker takes the focus where the engine did not give it one, so a quick view the page
 * opens for it hands the focus back there when it closes (utils/focus-clicked.js). Its card in the
 * strip scrolls into view sideways, never the page, and only while the strip shows.
 *
 * A label shown on hover or focus can be dismissed without moving either (WCAG 1.4.13): Escape
 * hides it until its marker loses the focus or the pointer. The key is heard on the window, so it
 * reaches a label under the pointer while the focus stands elsewhere, and a press that hid a label
 * is marked as handled, so a modal or a drawer around the image stays open on it.
 *
 * No listeners of its own, so nothing to release: the one on the window is bound in the view, and
 * Alpine removes it with the component.
 */
import { focusClicked } from '../utils/focus-clicked.js';
import { isComposing } from '../utils/ime.js';
import { prefersReducedMotion, scrollBehavior } from '../utils/motion.js';

/** Whether the device hovers: Tailwind's hover variants, the label's among them, apply only then. */
function deviceHovers() {
    return typeof window !== 'undefined' && typeof window.matchMedia === 'function' && window.matchMedia('(hover: hover)').matches;
}

export default function wirekitImageHotspots() {
    return {
        // The index of the marker pressed last, or null.
        active: null,
        // The indexes of the markers whose label Escape hid.
        quieted: [],

        /**
         * A marker was pressed: give it the focus, mark its card, and tell the page which target it
         * names through `wirekit:image-hotspots-select`.
         */
        select(index, target, label, el) {
            focusClicked(el);
            this.active = index;
            this.quieted = [];
            this._showCard(index);
            this.$dispatch('wirekit:image-hotspots-select', { index, target, label });
        },

        isActive(index) {
            return this.active === index;
        },

        /**
         * Escape anywhere: hide the labels on screen, beside the marker the keyboard stands on and
         * beside the one under the pointer.
         *
         * Heard on the window in the capture phase, as a tooltip hears it. A label shown on hover
         * is dismissed without moving the pointer or the focus, and the focus then often stands
         * outside the image, where a key bound to a marker never hears the press. A press that hid
         * a label is marked as handled, so a modal or a drawer around the image, which reads Escape
         * later, leaves it alone. A press with no label on screen is left to them: the next Escape
         * closes the modal.
         *
         * @param {KeyboardEvent} [event]
         */
        dismiss(event) {
            // An Escape that abandons an input method's conversion belongs to the input method.
            if (isComposing(event)) {
                return;
            }

            const shown = this._shownLabels();

            if (shown.length === 0) {
                return;
            }

            event?.preventDefault?.();
            this.quieted = [...this.quieted, ...shown];
        },

        loud(index) {
            if (this.isQuiet(index)) {
                this.quieted = this.quieted.filter((quiet) => quiet !== index);
            }
        },

        isQuiet(index) {
            return this.quieted.includes(index);
        },

        /**
         * The style of a marker's label: a label Escape hid goes by its opacity, so the marker keeps
         * the name the label gives it, and lets the pointer through to what lies under it. An object,
         * which Alpine sets property by property.
         */
        labelStyle(index) {
            return this.isQuiet(index) ? { opacity: 0, pointerEvents: 'none' } : {};
        },

        /**
         * The markers whose label is on screen, by the stylesheet's own conditions: on a visible
         * focus, and on hover where the device hovers. A label Escape hid already is not.
         */
        _shownLabels() {
            const root = this.$root;
            const markers = root && typeof root.querySelectorAll === 'function' ? [...root.querySelectorAll('[data-wk-hotspot]')] : [];
            const hovers = deviceHovers();

            return markers
                .filter((marker) => marker.matches(':focus-visible') || (hovers && marker.matches(':hover')))
                .map((marker) => Number(marker.getAttribute('data-wk-hotspot')))
                .filter((index) => ! this.isQuiet(index));
        },

        /** Scroll the strip sideways until the card of `index` is in view, when the strip shows. */
        _showCard(index) {
            const root = this.$root;
            const strip = root && typeof root.querySelector === 'function' ? root.querySelector('[data-wk-image-hotspots-strip]') : null;
            const card = strip ? strip.querySelector(`[data-wk-hotspot-card="${index}"]`) : null;

            // A strip the stylesheet hides has no box, and there is nothing to bring into view.
            if (! card || strip.clientWidth === 0 || typeof strip.scrollTo !== 'function') {
                return;
            }

            const stripBox = strip.getBoundingClientRect();
            const cardBox = card.getBoundingClientRect();

            if (cardBox.left >= stripBox.left && cardBox.right <= stripBox.right) {
                return;
            }

            // Physical coordinates and no clamp of our own, so the same arithmetic holds on a
            // right-to-left page, where `scrollLeft` runs from 0 into the negatives. The browser
            // holds `scrollTo()` to the strip's range on either side.
            const left = strip.scrollLeft + (cardBox.left - stripBox.left) - (strip.clientWidth - cardBox.width) / 2;

            strip.scrollTo({ left, behavior: scrollBehavior(! prefersReducedMotion()) });
        },
    };
}
