/**
 * Accordion — which panels are open, in single or multiple mode.
 *
 * The whole thing was an inline `x-data` declaring two methods, which Alpine's
 * CSP build cannot parse; the spread and the arrow function inside them are out
 * of its grammar too. Under a strict Content-Security-Policy no panel opened —
 * the headers rendered, the chevrons sat still, and nothing said why.
 *
 * `opened` is an ARRAY rather than a single id even in single mode. That looks
 * redundant and is not: the template asks `isOpen(id)` per item, and one shape
 * for both modes keeps that question answerable without the item knowing which
 * mode it is in.
 *
 * @param {Object} config
 * @param {string} [config.mode]  'single' (default) or 'multiple'
 */
export default function wirekitAccordion(config = {}) {
    return {
        mode: config.mode === 'multiple' ? 'multiple' : 'single',
        opened: [],

        toggle(id) {
            if (this.mode === 'single') {
                // Clicking the open panel closes it: a single-mode accordion
                // that cannot be fully closed traps the reader in whichever
                // panel they opened last.
                this.opened = this.opened.includes(id) ? [] : [id];

                return;
            }

            this.opened = this.opened.includes(id)
                ? this.opened.filter((openId) => openId !== id)
                : this.opened.concat([id]);
        },

        isOpen(id) {
            return this.opened.includes(id);
        },
    };
}
