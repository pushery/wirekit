/**
 * What `combobox` and `multi-select` share about an option's extra keys: which text the search
 * reads, which text the field or a pill shows, and when an avatar falls back to initials.
 *
 * The keys themselves are normalized on the server (`OptionMedia`), so an option reaching this
 * file carries only the keys it uses. Every function here treats a missing key as the option it
 * was before those keys existed, which is what keeps a plain list behaving exactly as it did.
 */

/**
 * Whether an option matches a lower-cased query.
 *
 * The label, the shorter `selectedLabel` and the `keywords` are searched. The description is
 * not: a filter narrows to options whose name fits, and a sentence of context matches far more
 * than the reader typed it for.
 *
 * @param {{ label: string, selectedLabel?: string, keywords?: string }} option
 * @param {string} query  already lower-cased by the caller, once per keystroke
 */
export function optionMatches(option, query) {
    if (option.label.toLowerCase().includes(query)) {
        return true;
    }

    if (option.selectedLabel && option.selectedLabel.toLowerCase().includes(query)) {
        return true;
    }

    return Boolean(option.keywords) && option.keywords.toLowerCase().includes(query);
}

/**
 * The text a chosen option shows where space is short: in the combobox field, in a pill.
 *
 * @param {{ label: string, selectedLabel?: string }} option
 */
export function chosenText(option) {
    return option.selectedLabel || option.label;
}

/**
 * The state behind an avatar's fallback, spread into a factory.
 *
 * Methods only, on purpose: spreading an object copies a getter's VALUE at spread time, so a
 * getter here would freeze. The broken photos are keyed by option value, which is stable across
 * filtering, while the rows that show them are created and destroyed on every keystroke.
 */
export function optionMediaState() {
    return {
        _brokenMedia: {},

        /** A photo that failed to load, so the avatar shows initials from now on. */
        markMediaBroken(option) {
            if (option && option.value !== undefined) {
                this._brokenMedia[option.value] = true;
            }
        },

        /** Whether an avatar shows initials: it has no photo, or its photo failed. */
        showsInitials(option) {
            return ! option.src || this._brokenMedia[option.value] === true;
        },
    };
}
