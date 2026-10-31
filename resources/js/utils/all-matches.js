/**
 * The words of a selection that can reach past the page of a server list: the notice above the
 * rows, the action it offers, and the sentence a screen reader hears.
 *
 * The forms are the server's, rendered at sample counts (`Support\PluralPhrases`), and the counts
 * are the browser's. The notice is text a reader sees, so it writes its numbers as the page writes
 * them, "1,284"; the spoken sentence takes bare digits, which every voice reads right.
 */
import { pluralize, pluralizeGrouped } from './plural.js';

/**
 * A number of matches the notice can offer: a whole, finite, non-negative count, or null.
 *
 * @param {unknown} value
 * @returns {number|null}
 */
export function matchCount(value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const number = Number(value);

    return Number.isInteger(number) && number >= 0 ? number : null;
}

/**
 * What the notice says and offers for a state, or null when it has nothing to offer.
 *
 * The page's rows all selected and more matches than the page holds: the page's count and the
 * offer of every match. Every match selected: that, and the way back to the page.
 *
 * @param {{pageAll: boolean, allMatches: boolean, pageCount: number, total: number|null}} state
 * @param {{page: Object, offer: Object, all: Object, only: string}} phrases
 * @param {string} locale the application's locale
 * @param {{group: string, decimal: string}|null} [marks] the application's own number marks
 * @returns {{text: string, action: string, spoken: string, offers: 'all'|'page'}|null}
 */
export function allMatchesNotice(state, phrases, locale, marks = null) {
    const total = matchCount(state.total);

    if (total === null) {
        return null;
    }

    if (state.allMatches) {
        return {
            text: pluralizeGrouped(phrases.all, total, locale, marks),
            action: phrases.only,
            spoken: pluralize(phrases.all, total, locale),
            offers: 'page',
        };
    }

    if (state.pageAll && state.pageCount > 0 && total > state.pageCount) {
        return {
            text: pluralizeGrouped(phrases.page, state.pageCount, locale, marks),
            action: pluralizeGrouped(phrases.offer, total, locale, marks),
            spoken: `${pluralize(phrases.page, state.pageCount, locale)} ${pluralize(phrases.offer, total, locale)}`,
            offers: 'all',
        };
    }

    return null;
}
