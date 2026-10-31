/**
 * Which command palette a hotkey reaches when several on one page share it.
 *
 * Every palette with a hotkey listens for it on `document`, and each one a press matches toggles:
 * two palettes on `cmd+k` open together. A palette with `takesHotkey` takes its key over for as
 * long as it is on the page. A press then reaches it alone, and the other palettes the press
 * matches stay quiet. Once it has left the page, as when a Livewire render leaves it out, the press
 * reaches them again. Of two palettes that take one key, the one that came last has it.
 *
 * A palette that is open hears its key before one that takes it, so the key closes the dialog
 * the reader is in rather than opening a second one over it. Without a palette that takes the key,
 * a press reaches every palette it matches.
 *
 * `command-palette.trigger` reads the same register, so that the field states a key only while
 * the key opens its palette, and so does the overview of keyboard shortcuts on `?`, which lists a
 * palette's key while the key opens that palette.
 */

/** The palettes on the page with a hotkey, in the order they came. */
const palettes = new Set();

/** Told whenever a palette with a hotkey comes or goes. */
const watchers = new Set();

/**
 * What each press was decided as. Every palette's listener reads the one answer: a palette that
 * opens on the press changes what a listener after it would decide.
 */
const decided = new WeakMap();

/** Whether a palette is still on the page. One without an element is taken to be. */
const onPage = (palette) => ! (palette.el && palette.el.isConnected === false);

/** The palettes on the page whose hotkey a press is, in the order they came. */
const hearing = (press) => [...palettes].filter((palette) => onPage(palette) && palette.hears(press));

/** The newest of the palettes that take the key, or null. */
const taker = (heard) => {
    const taking = heard.filter((palette) => palette.takes);

    return taking.length > 0 ? taking[taking.length - 1] : null;
};

const changed = () => {
    for (const watcher of [...watchers]) {
        watcher();
    }
};

/**
 * Put a palette's hotkey on the register.
 *
 * @param {object} palette
 * @param {(press: KeyboardEvent) => boolean} palette.hears  whether a press is the palette's hotkey
 * @param {boolean} palette.takes  whether the palette takes the key from the others
 * @param {() => boolean} palette.isOpen
 * @param {string|null} palette.name
 * @param {boolean} palette.namedOnly
 * @param {Element|null} palette.el  the palette's root, which says whether it is still on the page
 * @param {string} [palette.hotkey]  the hotkey as the palette takes it, for the overview
 * @param {string} [palette.label]  the palette's name in the overview
 * @param {'global'|'page'} [palette.area]  where the overview lists the key
 * @returns {() => void} takes the palette off the register again
 */
export function registerPaletteHotkey(palette) {
    palettes.add(palette);
    changed();

    return () => {
        if (palettes.delete(palette)) {
            changed();
        }
    };
}

/**
 * Whether a press reaches a palette whose hotkey it is.
 *
 * @param {KeyboardEvent} press
 * @param {object} palette  as registered
 * @returns {boolean}
 */
export function pressReaches(press, palette) {
    const remembered = typeof press === 'object' && press !== null;
    let reached = remembered ? decided.get(press) : undefined;

    if (reached === undefined) {
        const heard = hearing(press);
        const taking = taker(heard);
        const open = heard.filter((one) => one.isOpen());

        reached = taking === null ? heard : (open.length > 0 ? open : [taking]);

        if (remembered) {
            decided.set(press, reached);
        }
    }

    return reached.includes(palette);
}

/**
 * Whether a palette took the key of a press from the palettes `opens` accepts: with every palette
 * closed, the press would reach a palette that took it, and not one of those.
 *
 * @param {object} press  a key event, or an object with the same key and modifier fields
 * @param {(palette: object) => boolean} opens
 * @returns {boolean}
 */
export function pressTakenFrom(press, opens) {
    const taking = taker(hearing(press));

    return taking !== null && ! opens(taking);
}

/**
 * Whether a press, made on the page with every palette closed, opens this palette.
 *
 * @param {object} press  a key event, or an object with the same key and modifier fields
 * @param {object} palette  as registered
 * @returns {boolean}
 */
export function pressOpens(press, palette) {
    const heard = hearing(press);
    const taking = taker(heard);

    return taking === null ? heard.includes(palette) : taking === palette;
}

/** The palettes on the page with a hotkey, in the order they came. */
export function registeredPaletteHotkeys() {
    return [...palettes].filter(onPage);
}

/**
 * Call `watcher` whenever a palette with a hotkey comes or goes.
 *
 * @param {() => void} watcher
 * @returns {() => void} stops calling it
 */
export function watchPaletteHotkeys(watcher) {
    watchers.add(watcher);

    return () => {
        watchers.delete(watcher);
    };
}
