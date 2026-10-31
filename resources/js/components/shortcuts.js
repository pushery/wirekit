/**
 * The overview of the keyboard shortcuts, `<x-wirekit::shortcuts />`.
 *
 * It binds `?` everywhere, lists the shortcuts that apply at the moment it opens, by area, and holds
 * the switch for single-key shortcuts. The list is read before the overview opens: once it is open
 * it is itself the topmost dialog, and the shortcuts of the page or dialog under it would no
 * longer count as applying.
 *
 * A command palette handles its own hotkey, so it registers no shortcut here, and the key acts
 * once. The overview reads the palettes from their own register instead, and lists a palette's key
 * under the palette's name while the key opens that palette.
 */

import { controlIsDisabled } from '../utils/fieldset-disabled.js';
import { isNamedOverlayOpen } from '../utils/overlay.js';
import { pressOpens, registeredPaletteHotkeys } from '../utils/palette-hotkeys.js';
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
 * @param {Object<string, string>} [config.keyNames] - The words among the drawn keys in the
 *   reader's language, as `KeyShortcuts::keyNames()` translates them.
 */
export default function wirekitShortcuts(config = {}) {
    const name = String(config.name ?? 'wirekit-shortcuts');
    const headings = config.headings ?? {};
    const keyNames = config.keyNames ?? {};

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

            // The palettes first: their keys open what a reader reaches for most.
            for (const palette of registeredPaletteHotkeys()) {
                const parsed = parseShortcut(String(palette.hotkey ?? ''));
                const label = String(palette.label ?? '');

                // A palette of the page stays shut while a dialog is open, and its key with it.
                if (! parsed || parsed.steps.length !== 1 || label === '' || (palette.area === 'page' && dialog)) {
                    continue;
                }

                if (parsed.single && ! this.singleKeys) {
                    continue;
                }

                // A key that another palette has taken opens that one, and is listed there.
                if (! pressOpens(pressOf(parsed.steps[0], mac), palette)) {
                    continue;
                }

                byArea[palette.area === 'page' ? 'page' : 'global'].push({ id: ++id, label, steps: displayKeys(parsed, mac, keyNames) });
            }

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

                byArea[group].push({ id: ++id, label, steps: displayKeys(entry.parsed, mac, keyNames) });
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

/**
 * The press a reader on this platform makes for one step of a shortcut, as a key event reports it:
 * `cmd` is ⌘ on a Mac and Ctrl elsewhere, as the overview draws it.
 *
 * @param {{ key: string, cmd: boolean, ctrl: boolean, alt: boolean, shift: boolean }} step
 * @param {boolean} mac
 * @returns {object}
 */
function pressOf(step, mac) {
    return {
        key: step.key,
        code: '',
        metaKey: step.cmd && mac,
        ctrlKey: step.ctrl || (step.cmd && ! mac),
        altKey: step.alt,
        shiftKey: step.shift,
        repeat: false,
        composedPath: () => [],
    };
}

/**
 * An entry the overview may name: one without an element, or one whose element renders and acts,
 * which a control a disabled fieldset disables does not (utils/fieldset-disabled.js).
 */
function listable(entry) {
    if (! entry.el) {
        return true;
    }

    const el = entry.el;

    return el.isConnected !== false
        && typeof el.getClientRects === 'function'
        && el.getClientRects().length > 0
        && ! controlIsDisabled(el)
        && el.getAttribute('aria-disabled') !== 'true';
}
