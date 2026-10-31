/**
 * The scripts in which a combining mark makes another letter rather than an accent: a vowel sign in
 * Devanagari or Thai, the voicing mark on a Japanese kana. Kept in step with the server's fold in
 * `scope-switcher`, which builds the text a query is compared with.
 */
const MARKS_MAKE_LETTERS = /[\p{Script=Devanagari}\p{Script=Bengali}\p{Script=Gurmukhi}\p{Script=Gujarati}\p{Script=Oriya}\p{Script=Tamil}\p{Script=Telugu}\p{Script=Kannada}\p{Script=Malayalam}\p{Script=Sinhala}\p{Script=Thai}\p{Script=Lao}\p{Script=Tibetan}\p{Script=Myanmar}\p{Script=Khmer}\p{Script=Hiragana}\p{Script=Katakana}]/u;

/**
 * Text as a search compares it: accents folded away and lower-cased, so a reader who types
 * `munchen` finds `München` and `istanbul` finds `İstanbul`.
 *
 * NFD splits a letter into its base and its combining marks, and stripping the marks leaves the
 * base. `toLowerCase()` alone turns the Turkish dotted capital into `i` plus a combining dot,
 * which a typed `i` never matches. A table of replacements would be wrong for the next language
 * somebody uses; a letter that is not a base with marks, such as `ß` or `æ`, stays itself.
 *
 * A mark stays where it makes another letter: カ and ガ, कमल and कोमल are different words, and a
 * search for one must not find the other.
 *
 * @param {unknown} value
 * @returns {string}
 */
export function foldForSearch(value) {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/(\P{M})(\p{M}+)/gu, (sequence, base) => (MARKS_MAKE_LETTERS.test(base) ? sequence : base))
        .toLowerCase();
}

export default foldForSearch;
