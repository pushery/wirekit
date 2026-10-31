/**
 * ⌘↵ or Ctrl+Enter presses the primary control of an open modal dialog, also from a field.
 *
 * Which control confirms is something only the dialog knows. An alert dialog has its confirm
 * control, `alert-dialog.confirm`, and puts focus on Cancel on purpose, so a plain Enter can
 * never approve the action it guards. A modal has its primary button somewhere in its footer. A
 * page marks the one it means with `data-wk-dialog-primary`, on the control or on a wrapper
 * around it, and that mark wins over the alert dialog's own confirm.
 *
 * The shortcut is an ordinary entry of the shortcut registry (utils/shortcuts.js), so it follows
 * every rule a shortcut follows: it belongs to the area of the dialog it sits in and applies while
 * that dialog is the topmost one, it fires from a field because it carries a modifier, and a
 * control that is disabled or `aria-disabled` (an unmet confirmation phrase among them) is not
 * pressed, nor listed in the overview. The control is pressed with `click()`, so its own handlers,
 * Livewire's and the confirmation guard's, decide exactly as they do for a click.
 *
 * Armed when the dialog opens and disarmed when it closes. In between the panel is watched: a
 * Livewire update compares the page with markup that never carried the `aria-keyshortcuts` set
 * here and takes it off, and it can put another control where the primary one stood.
 */
import { ariaKeyShortcuts, installShortcuts, parseShortcut, registerShortcut } from './shortcuts.js';

/** The shortcut, as `x-wk-shortcut` writes it: ⌘↵ on a Mac and Ctrl+Enter elsewhere. */
export const DIALOG_CONFIRM_KEYS = 'cmd+enter';

/** The elements that can be pressed, as `alert-dialog.confirm` finds its control. */
const CONTROL = 'button, a[href], [role="button"], input[type="submit"]';

/** The control a mark sits on, or the first one inside it. */
function controlOf(marked) {
    if (! marked) {
        return null;
    }

    if (typeof marked.matches === 'function' && marked.matches(CONTROL)) {
        return marked;
    }

    return typeof marked.querySelector === 'function' ? marked.querySelector(CONTROL) : null;
}

/**
 * The primary control of a dialog's panel: the one the page marks with `data-wk-dialog-primary`,
 * else the one inside `fallback`, a selector the dialog names for its own confirm.
 *
 * @param {Element|null|undefined} panel
 * @param {string|null} [fallback]
 * @returns {Element|null}
 */
export function dialogPrimaryControl(panel, fallback = null) {
    if (! panel || typeof panel.querySelector !== 'function') {
        return null;
    }

    const marked = panel.querySelector('[data-wk-dialog-primary]');

    if (marked) {
        return controlOf(marked);
    }

    return fallback ? controlOf(panel.querySelector(fallback)) : null;
}

/**
 * Register the shortcut for a dialog that has just opened.
 *
 * The control gets `aria-keyshortcuts` while the shortcut is armed, unless it carries one of its
 * own. A panel without a primary control registers nothing until one appears.
 *
 * While it is armed, a change in the panel is read again: the attribute comes back when an update
 * took it off, and the shortcut moves to another control once that is the primary one. A
 * MutationObserver rather than a Livewire hook, so a dialog outside Livewire reads the same.
 *
 * @param {Element|null|undefined} panel
 * @param {{ fallback?: string|null, keys?: string }} [options]
 * @returns {() => void} disarms it again
 */
export function armDialogConfirm(panel, options = {}) {
    const parsed = parseShortcut(options.keys ?? DIALOG_CONFIRM_KEYS);

    if (! parsed || ! panel || typeof panel.querySelector !== 'function') {
        return () => {};
    }

    const aria = ariaKeyShortcuts(parsed);
    let armed = null;

    const release = () => {
        if (! armed) {
            return;
        }

        armed.unregister();

        // Only the value this armed is taken off: one a page wrote meanwhile stays.
        if (armed.ownsAria && armed.control.getAttribute('aria-keyshortcuts') === aria) {
            armed.control.removeAttribute('aria-keyshortcuts');
        }

        armed = null;
    };

    const sync = () => {
        const control = dialogPrimaryControl(panel, options.fallback ?? null);

        if (armed && armed.control === control) {
            // The markup an update compares with never carries the value set here.
            if (armed.ownsAria && ! control.hasAttribute('aria-keyshortcuts')) {
                control.setAttribute('aria-keyshortcuts', aria);
            }

            return;
        }

        release();

        if (! control) {
            return;
        }

        installShortcuts();

        const ownsAria = aria !== null && ! control.hasAttribute('aria-keyshortcuts');

        if (ownsAria) {
            control.setAttribute('aria-keyshortcuts', aria);
        }

        armed = {
            control,
            ownsAria,
            unregister: registerShortcut({
                el: control,
                parsed,
                run: () => control.click(),
            }),
        };
    };

    sync();

    const observer = typeof MutationObserver === 'function' ? new MutationObserver(sync) : null;

    observer?.observe(panel, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['aria-keyshortcuts', 'data-wk-dialog-primary'],
    });

    return () => {
        observer?.disconnect();
        release();
    };
}
