/**
 * The levels of a command palette: the top level, and the pages an entry opens.
 *
 * Every function here reads an element's own place in its tree and nothing else, so it answers
 * the same in the live list and in the copy Livewire's morph builds from a server render, where
 * Alpine evaluates the palette's bindings before the copy is merged into the page.
 */

import { foldForSearch } from './search-fold.js';

/** The attribute that names a page, on the page's own element. */
export const PAGE_ATTRIBUTE = 'data-wk-command-page';

/**
 * The page an element belongs to: the name of the nearest page around it, or '' on the top level.
 *
 * @param {Element|null|undefined} el
 * @returns {string}
 */
export function pageOf(el) {
    const page = typeof el?.closest === 'function' ? el.closest(`[${PAGE_ATTRIBUTE}]`) : null;

    return page ? (page.getAttribute(PAGE_ATTRIBUTE) ?? '') : '';
}

/**
 * Whether the page around an element takes its search from the host rather than from the palette.
 *
 * @param {Element|null|undefined} el
 * @returns {boolean}
 */
export function onRemotePage(el) {
    const page = typeof el?.closest === 'function' ? el.closest(`[${PAGE_ATTRIBUTE}]`) : null;

    return Boolean(page && page.hasAttribute('data-wk-command-remote'));
}

/**
 * The text a search compares: the kit's fold, which every other search of the kit uses, with its
 * spaces collapsed. `entwürfe` finds "Entwürfe" and `istanbul` finds "İstanbul" whatever language
 * the browser speaks, and a mark that makes another letter, as in ガ, stays.
 *
 * @param {unknown} text
 * @returns {string}
 */
export function searchText(text) {
    return foldForSearch(text).replace(/\s+/g, ' ').trim();
}

/**
 * The words an entry is found by: its label, without the key caps and the hints around it.
 *
 * @param {Element} option
 * @returns {string}
 */
export function labelOf(option) {
    const label = typeof option?.querySelector === 'function' ? option.querySelector('[data-wk-command-label]') : null;

    return (label ?? option)?.textContent ?? '';
}

/**
 * Whether an entry matches what the reader typed. An empty query matches every entry.
 *
 * @param {Element} option
 * @param {string} query
 * @returns {boolean}
 */
export function matchesQuery(option, query) {
    const wanted = searchText(query);

    return wanted === '' || searchText(labelOf(option)).includes(wanted);
}

/**
 * The entries a reader can choose on one level, in the order the page shows them: those that
 * belong to the level itself, not to a page inside it, and that are not disabled.
 *
 * @param {Element} container the page's element, or the list for the top level
 * @param {string} level the level's name, '' for the top level
 * @returns {Element[]}
 */
export function choicesOf(container, level) {
    if (typeof container?.querySelectorAll !== 'function') {
        return [];
    }

    return [...container.querySelectorAll('[role="option"]')]
        .filter((option) => option.getAttribute?.('aria-disabled') !== 'true' && pageOf(option) === level);
}

/**
 * The digit 1 to 9 a key names on a page, or 0. A full-width digit reads as its ASCII self after
 * NFKC, and the locale's own digits as the digits they are: the key 1 of a Persian keyboard types
 * `۱`, and the digit choosing nothing there left a whole keyboard without the shortcuts.
 *
 * @param {string} key a KeyboardEvent's `key`
 * @param {string} [locale] the application's locale
 * @returns {number}
 */
export function digitOfKey(key, locale = 'en') {
    const text = typeof key === 'string' ? key.normalize('NFKC') : '';

    if (/^[1-9]$/.test(text)) {
        return Number(text);
    }

    const own = localeDigits(locale).indexOf(text);

    return own >= 1 ? own : 0;
}

const digitsByLocale = new Map();

/**
 * The ten digits a locale writes, read once per locale. None where the engine knows no such
 * locale: then only the ASCII digits choose.
 *
 * @param {string} locale
 * @returns {string[]}
 */
function localeDigits(locale) {
    const tag = typeof locale === 'string' && locale !== '' ? locale.replace(/_/g, '-') : 'en';

    if (! digitsByLocale.has(tag)) {
        let digits;

        try {
            const format = new Intl.NumberFormat(tag, { useGrouping: false });

            digits = Array.from({ length: 10 }, (_, digit) => format.format(digit));
        } catch {
            digits = [];
        }

        digitsByLocale.set(tag, digits);
    }

    return digitsByLocale.get(tag);
}
