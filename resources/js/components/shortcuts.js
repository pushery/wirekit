/**
 * The overview of the keyboard shortcuts, `<x-wirekit::shortcuts />`.
 *
 * It binds `?` everywhere, lists the shortcuts that apply at the moment it opens, by area, and holds
 * the switch for single-key shortcuts. The list is read before the overview opens: once it is open
 * it is itself the topmost dialog, and the shortcuts of the page or dialog under it would no
 * longer count as applying.
 */

import { isNamedOverlayOpen } from '../utils/overlay.js';
import {
    activeDialog,
    appliesNow,
    areaOf,
    displayKeys,
    installShortcuts,
    isMacPlatform,
    parseShortcut,
    registerShortcut,
    registeredShortcuts,
    setSingleKeys,
    shortcutLabel,
    singleKeysEnabled,
} from '../utils/shortcuts.js';

/**
 * @param {object} config
 * @param {string} config.name - The name of the modal the overview renders.
 * @param {string} config.label - The overview's own entry in its list, the action of `?`.
 * @param {{ global: string, dialog: string, page: string }} config.headings - One heading per area.
 */
export default function wirekitShortcuts(config = {}) {
    const name = String(config.name ?? 'wirekit-shortcuts');
    const headings = config.headings ?? {};

    return {
        groups: [],
        singleKeys: true,
        screenOff: false,
        _unregister: null,

        init() {
            installShortcuts();
            this.singleKeys = singleKeysEnabled(document);

            this._unregister = registerShortcut({
                el: null,
                parsed: parseShortcut('?'),
                global: true,
                label: String(config.label ?? ''),
                run: () => this.toggle(),
            });
        },

        destroy() {
            if (this._unregister) {
                this._unregister();
                this._unregister = null;
            }
        },

        /** `?` opens the overview, and closes it again while it is open. */
        toggle() {
            if (isNamedOverlayOpen('wirekit-modal-show', name)) {
                window.dispatchEvent(new CustomEvent('wirekit-modal-close', { detail: { name } }));

                return;
            }

            this.show();
        },

        show() {
            this.collect();
            window.dispatchEvent(new CustomEvent('wirekit-modal-show', { detail: { name } }));
        },

        /** The shortcuts that apply now, grouped by area, each with the keys as this platform names them. */
        collect() {
            const dialog = activeDialog(document);
            const mac = isMacPlatform();
            const byArea = { global: [], dialog: [], page: [] };
            let id = 0;

            this.screenOff = document.querySelector('[data-wk-single-keys="off"]') !== null;
            this.singleKeys = singleKeysEnabled(document);

            for (const entry of registeredShortcuts()) {
                if (! appliesNow(entry, dialog) || ! listable(entry)) {
                    continue;
                }

                // A single key that the switch or the screen turns off is not listed as applying.
                if (entry.parsed.single && ! this.singleKeys) {
                    continue;
                }

                const area = areaOf(entry);
                const group = area === 'global' ? 'global' : (area === 'page' ? 'page' : 'dialog');
                // A name given with the shortcut first: one that works out what it does may sit
                // on an element named for something else, such as the list it moves through.
                const label = String(entry.label ?? '') || (entry.el ? shortcutLabel(entry.el) : '');

                if (label === '') {
                    continue;
                }

                byArea[group].push({ id: ++id, label, steps: displayKeys(entry.parsed, mac) });
            }

            this.groups = ['global', 'dialog', 'page']
                .filter((group) => byArea[group].length > 0)
                .map((group) => ({ key: group, heading: String(headings[group] ?? group), items: byArea[group] }));
        },

        /** The reader's switch: stored in the browser and announced for the application to keep. */
        switchSingleKeys(enabled) {
            this.singleKeys = Boolean(enabled);
            setSingleKeys(this.singleKeys, document);
            this.collect();
        },
    };
}

/** An entry the overview may name: one without an element, or one whose element renders and acts. */
function listable(entry) {
    if (! entry.el) {
        return true;
    }

    const el = entry.el;

    return el.isConnected !== false
        && typeof el.getClientRects === 'function'
        && el.getClientRects().length > 0
        && ! el.disabled
        && el.getAttribute('aria-disabled') !== 'true';
}
