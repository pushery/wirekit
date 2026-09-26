import { withOpenAlias } from '../utils/open-alias.js';

/**
 * Collapsible — the open state of a disclosure.
 *
 * The state is `isOpen`. `open` reads and writes the same value for markup written against the
 * earlier name, such as a developer's own slot content; see `utils/open-alias.js`.
 *
 * @param {Object}  [config]
 * @param {boolean} [config.open]  whether the disclosure starts open
 */
export default function wirekitCollapsible(config = {}) {
    return withOpenAlias({
        isOpen: config.open === true,
    });
}
