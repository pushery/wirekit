/**
 * Keyboard shortcuts for a whole application.
 *
 * `x-wk-shortcut="cmd+s"` on the element a shortcut acts on: the shortcut does what a click on the
 * element does (`.focus` focuses it instead), and its name in the overview is the element's own
 * accessible name. A shortcut belongs to an area: the dialog the element sits in, the page, or with
 * `.global` everywhere. A modal dialog hides the page's shortcuts.
 *
 * One listener on `document`, in the bubble phase, decides every press. A widget that took the key
 * (a menu's type-ahead) has canceled it by then, a single character stays a character wherever text
 * is typed, and the reader can switch the single-key shortcuts off (WCAG 2.1.4).
 */

import { controlIsDisabled } from './fieldset-disabled.js';
import { typesText } from './typing-target.js';

/** The browser's memory of the reader's choice, used when the application supplies none. */
export const SINGLE_KEYS_STORAGE = 'wk-shortcuts-single-keys';

/** Dispatched on the window when the reader flips the switch, with `{ enabled }`. */
export const SINGLE_KEYS_EVENT = 'wirekit:shortcuts-single-keys-changed';

/** How long a sequence waits for its next key, in milliseconds. */
export const SEQUENCE_TIMEOUT = 1000;

const MODIFIERS = new Set(['cmd', 'meta', 'ctrl', 'control', 'alt', 'option', 'shift']);

/**
 * The values `KeyboardEvent.key` reports for a key that only changes another. Pressed on its own it
 * is the first half of reaching a character (`?` is Shift and a key on most layouts), so it neither
 * fires anything nor ends a sequence in progress.
 */
const MODIFIER_KEYS = new Set(['Shift', 'Control', 'Alt', 'AltGraph', 'Meta', 'OS', 'Hyper', 'Super', 'Fn', 'FnLock', 'CapsLock', 'NumLock', 'ScrollLock', 'Symbol', 'SymbolLock']);

/**
 * The names a shortcut may use for a key that types no character, each mapped to the value
 * `KeyboardEvent.key` reports for it. A name outside the list is kept as written, so any other
 * key value (`F13`, `MediaPlayPause`) works when spelled the way the browser spells it.
 */
const NAMED_KEYS = new Map([
    ['enter', 'Enter'],
    ['return', 'Enter'],
    ['esc', 'Escape'],
    ['escape', 'Escape'],
    ['space', ' '],
    ['tab', 'Tab'],
    ['backspace', 'Backspace'],
    ['delete', 'Delete'],
    ['del', 'Delete'],
    ['up', 'ArrowUp'],
    ['down', 'ArrowDown'],
    ['left', 'ArrowLeft'],
    ['right', 'ArrowRight'],
    ['arrowup', 'ArrowUp'],
    ['arrowdown', 'ArrowDown'],
    ['arrowleft', 'ArrowLeft'],
    ['arrowright', 'ArrowRight'],
    ['home', 'Home'],
    ['end', 'End'],
    ['pageup', 'PageUp'],
    ['pagedown', 'PageDown'],
]);

/** How the overview draws a key that types no character: on a Mac, and elsewhere. */
const SHOWN_KEYS = new Map([
    ['Enter', ['↩', 'Enter']],
    ['Escape', ['Esc', 'Esc']],
    ['Tab', ['⇥', 'Tab']],
    ['Backspace', ['⌫', 'Backspace']],
    ['Delete', ['⌦', 'Del']],
    ['ArrowUp', ['↑', '↑']],
    ['ArrowDown', ['↓', '↓']],
    ['ArrowLeft', ['←', '←']],
    ['ArrowRight', ['→', '→']],
    [' ', ['Space', 'Space']],
]);

/** A key as written, in the form it is compared in: a character in lower case, a name as the browser reports it. */
function keyName(key) {
    if (key.length === 1) {
        return key.toLowerCase();
    }

    const lower = key.toLowerCase();

    if (NAMED_KEYS.has(lower)) {
        return NAMED_KEYS.get(lower);
    }

    return /^f([1-9]|1[0-9]|2[0-4])$/.test(lower) ? lower.toUpperCase() : key;
}

/**
 * A shortcut as written, parsed into its steps. Keys of one step are joined by `+`, the steps of a
 * sequence by spaces: `cmd+s`, `shift+?`, `g o`, `cmd+enter`. Null for text that names no key.
 *
 * @param {string} text
 * @returns {{ text: string, steps: Array<{ key: string, cmd: boolean, ctrl: boolean, alt: boolean, shift: boolean }>, single: boolean } | null}
 */
export function parseShortcut(text) {
    const source = String(text ?? '').trim();

    if (source === '') {
        return null;
    }

    const steps = [];

    for (const chunk of source.split(/\s+/)) {
        // A `+` on its own, or at the end after another `+`, is the key itself (`cmd++`).
        let parts;

        if (chunk === '+') {
            parts = ['+'];
        } else if (chunk.endsWith('++')) {
            parts = [...chunk.slice(0, -2).split('+').filter((part) => part !== ''), '+'];
        } else {
            parts = chunk.split('+').filter((part) => part !== '');
        }

        const key = parts.length > 0 ? parts[parts.length - 1] : '';

        if (key === '' || MODIFIERS.has(key.toLowerCase())) {
            return null;
        }

        const modifiers = parts.slice(0, -1).map((part) => part.toLowerCase());

        if (modifiers.some((part) => ! MODIFIERS.has(part))) {
            return null;
        }

        steps.push({
            key: keyName(key),
            cmd: modifiers.includes('cmd') || modifiers.includes('meta'),
            ctrl: modifiers.includes('ctrl') || modifiers.includes('control'),
            alt: modifiers.includes('alt') || modifiers.includes('option'),
            shift: modifiers.includes('shift'),
        });
    }

    // Single keys are the ones a reader may type as text: no Cmd, Ctrl or Alt on any step.
    const single = steps.every((step) => ! step.cmd && ! step.ctrl && ! step.alt);

    return { text: source, steps, single };
}

/**
 * Whether a key press is one step of a shortcut.
 *
 * The key is compared with the character the layout produced (`event.key`), so `?` is `?` on every
 * layout. With Alt or Shift in the step a letter or digit also matches by its position
 * (`event.code`), because those modifiers change the character a key types.
 *
 * @param {{ key: string, cmd: boolean, ctrl: boolean, alt: boolean, shift: boolean }} step
 * @param {KeyboardEvent} event
 * @returns {boolean}
 */
export function matchesStep(step, event) {
    const cmd = Boolean(event.metaKey || event.ctrlKey);

    if (step.cmd ? ! cmd : (! step.ctrl && cmd)) {
        return false;
    }

    if (step.ctrl && ! event.ctrlKey) {
        return false;
    }

    if (step.alt !== Boolean(event.altKey)) {
        return false;
    }

    // Shift is part of a printable character (`?` is Shift on most layouts), so it is required when
    // written and otherwise left to the character, except on a letter: Shift+G types `G`, which is
    // not the shortcut `g`. Caps Lock types `G` without Shift and still matches.
    if (step.shift && ! event.shiftKey) {
        return false;
    }

    if (! step.shift && event.shiftKey && /^[a-z]$/.test(step.key)) {
        return false;
    }

    const key = typeof event.key === 'string' ? event.key : '';

    if (key.length === 1 ? key.toLowerCase() === step.key : key === step.key) {
        return true;
    }

    if ((step.alt || step.shift) && typeof event.code === 'string') {
        if (/^[a-z]$/.test(step.key)) {
            return event.code === 'Key' + step.key.toUpperCase();
        }

        if (/^[0-9]$/.test(step.key)) {
            return event.code === 'Digit' + step.key;
        }
    }

    return false;
}

/**
 * The shortcut in the form `aria-keyshortcuts` takes, or null for a sequence, which that attribute
 * cannot express. `cmd` names both of the keys it stands for.
 *
 * @param {ReturnType<typeof parseShortcut>} parsed
 * @returns {string|null}
 */
export function ariaKeyShortcuts(parsed) {
    if (! parsed || parsed.steps.length !== 1) {
        return null;
    }

    const step = parsed.steps[0];
    const key = step.key === ' ' ? 'Space' : (step.key.length === 1 ? step.key.toUpperCase() : step.key);
    const rest = [step.ctrl ? 'Control' : null, step.alt ? 'Alt' : null, step.shift ? 'Shift' : null].filter(Boolean);

    // A shortcut that names Control beside `cmd` needs Control either way, so it has the one form,
    // as KeyShortcuts::aria() writes it.
    if (step.cmd) {
        return step.ctrl
            ? ['Meta', ...rest, key].join('+')
            : [['Control', ...rest, key].join('+'), ['Meta', ...rest, key].join('+')].join(' ');
    }

    return [...rest, key].join('+');
}

/**
 * The keys of a shortcut as a reader sees them, one list per step: ⌘ ⌥ ⇧ on a Mac, Ctrl Alt Shift
 * elsewhere. `names` gives the words among them in the reader's language (`Ctrl` is `Strg` on a
 * German keyboard), as `KeyShortcuts::keyNames()` translates them; a word it lacks stays English.
 * `KeyShortcuts::display()` draws a shortcut on the server by the same rules.
 *
 * @param {ReturnType<typeof parseShortcut>} parsed
 * @param {boolean} mac
 * @param {Object<string, string>} [names]
 * @returns {string[][]}
 */
export function displayKeys(parsed, mac, names = {}) {
    if (! parsed) {
        return [];
    }

    const word = (key) => (typeof names?.[key] === 'string' && names[key] !== '' ? names[key] : key);

    return parsed.steps.map((step) => {
        const keys = [];

        if (step.cmd) keys.push(mac ? '⌘' : word('Ctrl'));
        // On a Mac Control is a key of its own beside ⌘; elsewhere `cmd` is Ctrl already.
        if (step.ctrl && (mac || ! step.cmd)) keys.push(mac ? '⌃' : word('Ctrl'));
        if (step.alt) keys.push(mac ? '⌥' : word('Alt'));
        if (step.shift) keys.push(mac ? '⇧' : word('Shift'));

        const shown = SHOWN_KEYS.get(step.key);

        keys.push(shown ? word(shown[mac ? 0 : 1]) : (step.key.length === 1 ? step.key.toUpperCase() : step.key));

        return keys;
    });
}

/**
 * Whether the reader is on a Mac, read from what the browser says about the platform: its client
 * hint where it gives one, else `navigator.platform`, which an iPad in desktop mode reports as
 * `MacIntel`. A hint may be empty where the platform is unknown, and then the older answer counts.
 */
export function isMacPlatform(nav = typeof navigator !== 'undefined' ? navigator : null) {
    const platform = String(nav?.userAgentData?.platform || nav?.platform || '');

    return /mac|iphone|ipad|ipod/i.test(platform);
}

/** The registered shortcuts: one entry per element that carries the directive. */
const entries = new Set();

/**
 * Register a shortcut. `run` is called when it fires; an entry with an `el` belongs to the area
 * the element sits in.
 *
 * @param {{ el?: Element|null, parsed: object, global?: boolean, label?: string, run: Function }} entry
 * @returns {() => void} unregisters the entry
 */
export function registerShortcut(entry) {
    warnOnDuplicate(entry);
    entries.add(entry);

    return () => entries.delete(entry);
}

/**
 * Register a shortcut that works out what it does, rather than clicking or focusing one element:
 * `j` for the next link in a list, `e` to fold the group around the current one. Exposed as
 * `window.wirekitShortcut` by every bundle that carries `x-wk-shortcut`.
 *
 * It follows the rules every shortcut follows: a widget that takes the key keeps it, a field
 * keeps its typing, the reader's switch turns a single key off, and an open modal dialog stands
 * for its own area. With `el` the shortcut belongs to the area that element sits in and applies
 * while it renders; without, it is the page's, or every area's with `global`. The overview names
 * it by `label`.
 *
 * @param {{ keys: string, label?: string, run: function(KeyboardEvent): void, el?: Element|null, global?: boolean }} options
 * @returns {() => void} unregisters it; options that name no shortcut or no `run` register nothing
 */
export function defineShortcut(options) {
    const parsed = parseShortcut(String(options?.keys ?? ''));

    if (! parsed || typeof options.run !== 'function') {
        return () => {};
    }

    installShortcuts();

    return registerShortcut({
        el: options.el ?? null,
        parsed,
        global: options.global === true,
        label: String(options.label ?? ''),
        run: options.run,
    });
}

/** Every registered entry, for the overview and for tests. */
export function registeredShortcuts() {
    return Array.from(entries);
}

/** Forget every entry and any sequence in progress; for tests. */
export function resetShortcuts() {
    entries.clear();
    clearPending();
}

/** The topmost rendered modal dialog in the document, or null. */
export function activeDialog(doc = typeof document !== 'undefined' ? document : null) {
    if (! doc) {
        return null;
    }

    const dialogs = Array.from(doc.querySelectorAll('[aria-modal="true"]')).filter(isRendered);

    return dialogs.length > 0 ? dialogs[dialogs.length - 1] : null;
}

/**
 * The area an entry belongs to: 'global', the modal dialog around it, or 'page'.
 *
 * Only a modal dialog is an area. A panel that is a dialog without being modal (a filter
 * builder's, a hover card's) leaves the page usable beside it, so its shortcuts are the page's.
 * A modal one carries `aria-modal` with either value: an overlay covered by another says
 * `false` until it is on top again, and stays its own area meanwhile.
 */
export function areaOf(entry) {
    if (entry.global) {
        return 'global';
    }

    const dialog = entry.el && typeof entry.el.closest === 'function'
        ? entry.el.closest('[role="dialog"][aria-modal], [role="alertdialog"][aria-modal]')
        : null;

    return dialog ?? 'page';
}

/** Whether an entry applies with the given dialog open (or none). */
export function appliesNow(entry, dialog) {
    const area = areaOf(entry);

    if (area === 'global') {
        return true;
    }

    return dialog ? area === dialog : area === 'page';
}

function isRendered(el) {
    return typeof el.getClientRects === 'function' && el.getClientRects().length > 0 && ! el.closest('[inert]');
}

/**
 * Whether the element of a shortcut can act. A control a disabled fieldset disables keeps its own
 * `disabled` false, so `:disabled` is asked as well (utils/fieldset-disabled.js).
 */
function isUsable(entry) {
    if (! entry.el) {
        return true;
    }

    const el = entry.el;

    return el.isConnected !== false && isRendered(el) && ! controlIsDisabled(el) && el.getAttribute('aria-disabled') !== 'true';
}

/**
 * Whether single-key shortcuts may fire. A screen turns them off with `data-wk-single-keys="off"`
 * anywhere in the page; otherwise the application's choice on the overview counts, and without
 * one the browser's memory.
 */
export function singleKeysEnabled(doc = typeof document !== 'undefined' ? document : null) {
    if (doc && doc.querySelector('[data-wk-single-keys="off"]')) {
        return false;
    }

    return readerSingleKeysEnabled(doc);
}

/**
 * The reader's own choice for single-key shortcuts, whatever the screen says: the application's
 * choice on the overview, and without one the browser's memory.
 */
export function readerSingleKeysEnabled(doc = typeof document !== 'undefined' ? document : null) {
    const choice = doc ? doc.querySelector('[data-wk-shortcuts][data-wk-single-keys-choice]') : null;

    if (choice) {
        return choice.getAttribute('data-wk-single-keys-choice') !== 'false';
    }

    try {
        return globalThis.localStorage?.getItem(SINGLE_KEYS_STORAGE) !== 'off';
    } catch (_) {
        return true;
    }
}

/**
 * Store the reader's choice in the browser and announce it, so an application can keep it with the
 * person. A choice the application rendered is updated in place, so it holds until the next page.
 */
export function setSingleKeys(enabled, doc = typeof document !== 'undefined' ? document : null) {
    try {
        if (enabled) {
            globalThis.localStorage?.removeItem(SINGLE_KEYS_STORAGE);
        } else {
            globalThis.localStorage?.setItem(SINGLE_KEYS_STORAGE, 'off');
        }
    } catch (_) {
        // No storage: the choice holds until the page is left.
    }

    for (const root of doc ? Array.from(doc.querySelectorAll('[data-wk-shortcuts][data-wk-single-keys-choice]')) : []) {
        root.setAttribute('data-wk-single-keys-choice', enabled ? 'true' : 'false');
    }

    if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent(SINGLE_KEYS_EVENT, { detail: { enabled: Boolean(enabled) } }));
    }
}

/** The sequence in progress: the entries that may still complete, and the step each expects next. */
let pending = null;

function clearPending() {
    if (pending?.timer) {
        clearTimeout(pending.timer);
    }

    pending = null;
}

/**
 * Decide one key press. Returns the entry that fired, or null.
 *
 * @param {KeyboardEvent} event
 * @param {{ doc?: Document|null }} [options]
 */
export function handleShortcutKeydown(event, options = {}) {
    const doc = options.doc ?? (typeof document !== 'undefined' ? document : null);

    // A widget took the key, or an input method is composing: never a shortcut.
    if (event.defaultPrevented || event.isComposing || event.keyCode === 229) {
        clearPending();

        return null;
    }

    if (MODIFIER_KEYS.has(event.key)) {
        return null;
    }

    const dialog = activeDialog(doc);
    const typing = typesText(event);
    const singles = singleKeysEnabled(doc);

    const live = registeredShortcuts().filter((entry) => {
        if (! appliesNow(entry, dialog) || ! isUsable(entry)) {
            return false;
        }

        return ! entry.parsed.single || (singles && ! typing);
    });

    // A key held down repeats its keydown. A shortcut does what one click does, so it fires once
    // per press. The repeats of a shortcut that applies are still taken from the browser, or
    // holding Cmd+S would open the browser's own dialog after the first save; a sequence waiting
    // for its next key keeps waiting.
    if (event.repeat) {
        if (live.some((entry) => entry.parsed.steps.length === 1 && matchesStep(entry.parsed.steps[0], event))) {
            event.preventDefault();
        }

        return null;
    }

    if (pending) {
        const advanced = [];

        for (const { entry, next } of pending.candidates) {
            if (! live.includes(entry) || ! matchesStep(entry.parsed.steps[next], event)) {
                continue;
            }

            if (next + 1 === entry.parsed.steps.length) {
                clearPending();

                return fire(entry, event);
            }

            advanced.push({ entry, next: next + 1 });
        }

        clearPending();

        if (advanced.length > 0) {
            startPending(advanced);

            return null;
        }
    }

    const starting = live.filter((entry) => matchesStep(entry.parsed.steps[0], event));
    const complete = starting.find((entry) => entry.parsed.steps.length === 1);

    if (complete) {
        return fire(complete, event);
    }

    const sequences = starting.filter((entry) => entry.parsed.steps.length > 1);

    if (sequences.length > 0) {
        startPending(sequences.map((entry) => ({ entry, next: 1 })));
    }

    return null;
}

function startPending(candidates) {
    pending = {
        candidates,
        timer: setTimeout(clearPending, SEQUENCE_TIMEOUT),
    };
}

function fire(entry, event) {
    event.preventDefault();
    entry.run(event);

    return entry;
}

/**
 * Warn in development when an element's shortcut is one the grammar does not read. It binds
 * nothing, and without a word a typo such as `cmd+shfit+s` reads as a shortcut that works.
 */
function warnOnUnread(expression, el) {
    const text = String(expression ?? '').trim();

    if (text === '' || typeof document === 'undefined' || ! document.querySelector('[data-wk-shortcuts][data-wk-shortcuts-debug]')) {
        return;
    }

    console.warn(`[wirekit::shortcuts] "${text}" is no shortcut, so nothing is bound: modifiers (cmd, ctrl, alt, shift) joined to one key with +, the steps of a sequence apart by a space.`, el);
}

/**
 * Warn in development when an area gets the same shortcut twice, or a single key that also starts a
 * sequence there: in both cases one of them never fires.
 */
function warnOnDuplicate(entry) {
    if (typeof document === 'undefined' || ! document.querySelector('[data-wk-shortcuts][data-wk-shortcuts-debug]')) {
        return;
    }

    const area = areaOf(entry);
    const text = normalized(entry.parsed);

    for (const other of registeredShortcuts()) {
        if (areaOf(other) !== area) {
            continue;
        }

        const otherText = normalized(other.parsed);

        if (otherText === text) {
            console.warn(`[wirekit::shortcuts] "${entry.parsed.text}" is registered twice in the same area; only the first fires.`, other.el ?? other, entry.el ?? entry);

            return;
        }

        if (text.startsWith(otherText + ' ') || otherText.startsWith(text + ' ')) {
            console.warn(`[wirekit::shortcuts] "${other.parsed.text}" and "${entry.parsed.text}" share a first key in the same area; the single key fires and the sequence never does.`, other.el ?? other, entry.el ?? entry);

            return;
        }
    }
}

/** A shortcut written one way for comparison: modifiers in a fixed order, keys in lower case. */
function normalized(parsed) {
    return parsed.steps.map((step) => [
        step.cmd ? 'cmd' : null,
        step.ctrl ? 'ctrl' : null,
        step.alt ? 'alt' : null,
        step.shift ? 'shift' : null,
        step.key.toLowerCase(),
    ].filter(Boolean).join('+')).join(' ');
}

const INSTALLED_FLAG = '__wirekitShortcutsInstalled';

/** Install the one listener, once per page. */
export function installShortcuts() {
    if (typeof window === 'undefined' || typeof document === 'undefined' || window[INSTALLED_FLAG]) {
        return;
    }

    window[INSTALLED_FLAG] = true;
    document.addEventListener('keydown', (event) => handleShortcutKeydown(event));
}

/** The accessible name of an element, as the overview lists it. */
export function shortcutLabel(el) {
    if (! el) {
        return '';
    }

    const explicit = el.getAttribute('data-wk-shortcut-label') || el.getAttribute('aria-label');

    if (explicit) {
        return explicit.trim();
    }

    const labelledBy = el.getAttribute('aria-labelledby');

    if (labelledBy && el.ownerDocument) {
        const text = labelledBy.split(/\s+/).map((id) => el.ownerDocument.getElementById(id)?.textContent ?? '').join(' ').trim();

        if (text !== '') {
            return text.replace(/\s+/g, ' ');
        }
    }

    if (el.labels && el.labels.length > 0) {
        return String(el.labels[0].textContent ?? '').replace(/\s+/g, ' ').trim();
    }

    return String(el.textContent ?? '').replace(/\s+/g, ' ').trim();
}

/** `aria-keyshortcuts` for a shortcut of one press, unless the element carries one of its own. */
function applyAriaKeyShortcuts(el, parsed) {
    const aria = ariaKeyShortcuts(parsed);

    if (aria !== null && typeof el?.hasAttribute === 'function' && ! el.hasAttribute('aria-keyshortcuts')) {
        el.setAttribute('aria-keyshortcuts', aria);
    }
}

const KEY_DISPLAY_FLAG = '__wirekitKeyDisplayMarked';

/**
 * Say on the root what the drawn keys of a shortcut depend on, for the stylesheet. `<x-wirekit::kbd
 * shortcut="…">` draws the keys of both kinds of platform, and `data-wk-platform` (`mac` or
 * `other`) says which one shows. `data-wk-single-keys-off` stands while the reader has switched
 * single-key shortcuts off, which hides the drawn keys of one; a screen that turns them off says
 * so itself, and the stylesheet reads that with `:has()`.
 *
 * Marked again when the reader flips the switch, and after `wire:navigate` brings in a new page.
 */
export function markKeyDisplay(doc = typeof document !== 'undefined' ? document : null) {
    const root = doc?.documentElement;

    if (! root || typeof root.setAttribute !== 'function' || typeof root.toggleAttribute !== 'function') {
        return;
    }

    const mark = () => {
        root.setAttribute('data-wk-platform', isMacPlatform() ? 'mac' : 'other');
        root.toggleAttribute('data-wk-single-keys-off', ! readerSingleKeysEnabled(doc));
    };

    mark();

    if (typeof window === 'undefined' || window[KEY_DISPLAY_FLAG]) {
        return;
    }

    window[KEY_DISPLAY_FLAG] = true;
    window.addEventListener(SINGLE_KEYS_EVENT, mark);
    doc.addEventListener('livewire:navigated', mark);
}

/**
 * Register the `x-wk-shortcut` directive, and mark the root for the keys the page draws (see
 * `markKeyDisplay()`), which every bundle carrying the directive draws as well.
 *
 * @param {object} Alpine
 */
export function registerShortcutDirective(Alpine) {
    markKeyDisplay();

    const skipDuringClone = typeof Alpine.skipDuringClone === 'function' ? Alpine.skipDuringClone : (callback) => callback;

    // A Livewire morph compares the page with a clone of the new markup that Alpine initializes
    // without this directive, and takes off every attribute the clone lacks. The clone therefore
    // gets `aria-keyshortcuts` as well, and only that: registering a shortcut for a node the morph
    // throws away would leave an entry no cleanup ever removes.
    const cloned = (el, { expression }) => applyAriaKeyShortcuts(el, parseShortcut(expression));

    Alpine.directive('wk-shortcut', skipDuringClone((el, { expression, modifiers }, { cleanup }) => {
        const parsed = parseShortcut(expression);

        if (! parsed) {
            warnOnUnread(expression, el);

            return;
        }

        installShortcuts();
        applyAriaKeyShortcuts(el, parsed);

        const focus = modifiers.includes('focus');
        const unregister = registerShortcut({
            el,
            parsed,
            global: modifiers.includes('global'),
            run: () => {
                if (focus) {
                    el.focus();

                    return;
                }

                el.click();
            },
        });

        cleanup(unregister);
    }, cloned));

    // A button or a link is rarely inside an `x-data` of its own: the selectors make Alpine reach
    // it, with each combination of the modifiers.
    if (typeof Alpine.addInitSelector === 'function') {
        Alpine.addInitSelector(() => [
            '[x-wk-shortcut]',
            '[x-wk-shortcut\\.focus]',
            '[x-wk-shortcut\\.global]',
            '[x-wk-shortcut\\.focus\\.global]',
            '[x-wk-shortcut\\.global\\.focus]',
        ].join(', '));
    }
}
