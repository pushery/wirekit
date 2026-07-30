/**
 * A development-only warning on an element that has no factory of its own.
 *
 * Card and the plain-HTML table warn about a composition mistake that renders
 * without visibly failing, and neither element carries any other Alpine state —
 * so the warning IS the component. Where the element already has a factory
 * (tabs, the sortable table) the same message is passed into that factory
 * instead, because an element cannot carry two `x-data`.
 *
 * Both paths call the same helper. See resources/js/utils/dev-warning.js for why
 * this cannot be an inline `console.warn(…)`: under Alpine's CSP build naming
 * `console` throws while building the component, which takes down the very
 * element the warning is about.
 *
 * @param {Object} config
 * @param {string} config.message  the warning text, already composed in Blade
 */
import { devWarn } from '../utils/dev-warning.js';

export default function wirekitDevWarning(config = {}) {
    return {
        init() {
            devWarn(config.message);
        },
    };
}
