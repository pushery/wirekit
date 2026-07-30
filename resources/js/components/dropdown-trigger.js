import { applyTriggerAria } from '../utils/trigger-aria.js';

/**
 * Dropdown trigger — puts the menu ARIA on the interactive child.
 *
 * The wrapper a developer writes around their button is a generic element, and
 * `aria-haspopup` / `aria-expanded` on one of those fails axe-core's
 * aria-allowed-attr rule. So the attributes go on the first focusable
 * descendant, and follow `open` from there.
 *
 * This was an inline `x-init` IIFE — an arrow function, two `const`s, an early
 * return and several statements, none of which Alpine's CSP build parses. Under
 * a strict Content-Security-Policy the trigger got no ARIA at all: it still
 * opened on click, and announced itself as a plain button that leads nowhere.
 *
 * It was also the THIRD copy of this logic, after popover and hover-card, and it
 * had drifted from both — hence the shared util rather than a fourth.
 *
 * `aria-controls` points at the panel, whose id lives on an ancestor: the
 * trigger and the panel are siblings under the dropdown root, so the id is read
 * from the scope rather than passed down through markup.
 *
 * @param {Object}  config
 * @param {?string} config.labelFallback  name for an icon-only trigger
 */
export default function wirekitDropdownTrigger(config = {}) {
    return {
        init() {
            applyTriggerAria(this.$el, this.$watch.bind(this), {
                haspopup: 'menu',
                controls: this.$wkAncestorData('[data-wk-panel-id]', 'wkPanelId') || null,
                labelFallback: config.labelFallback || null,
                missingTriggerWarning: '[wirekit] dropdown.trigger: slot has no focusable element (button/link). '
                    + 'Keyboard users cannot open the dropdown. Wrap the trigger content in a <button>.',
            });
        },
    };
}
