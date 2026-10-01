/**
 * A number as text, in the application's locale, for a figure a reader sees.
 *
 * `toFixed()` always writes a point, so a German page read "1.5 MB" beside server-rendered figures
 * that said "1,5 MB". No thousands grouping, as `toFixed` had none, so only the decimal separator
 * moves. Without a locale, or with a tag the runtime rejects, the English spelling is kept, which
 * is what `toFixed` wrote; a digit count the runtime cannot format falls back to `toFixed` itself.
 *
 * @param {number} value
 * @param {string|null|undefined} locale  BCP-47, as the view spells it
 * @param {number} maximumFractionDigits
 * @param {number} [minimumFractionDigits]
 * @returns {string}
 */
export function formatDecimal(value, locale, maximumFractionDigits, minimumFractionDigits = 0) {
    const options = { useGrouping: false, minimumFractionDigits, maximumFractionDigits };

    try {
        return new Intl.NumberFormat(locale || 'en', options).format(value);
    } catch {
        // The tag, or a digit count an older engine caps at 20. `toFixed` takes 0 to 100.
        try {
            return new Intl.NumberFormat('en', options).format(value);
        } catch {
            return Number(value).toFixed(maximumFractionDigits);
        }
    }
}

export default formatDecimal;
