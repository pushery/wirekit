/**
 * A number as text, in the application's locale, for a figure a reader sees.
 *
 * `toFixed()` always writes a point, so a German page read "1.5 MB" beside server-rendered figures
 * that said "1,5 MB". Without a locale, or with a tag the runtime rejects, the English spelling is
 * kept, which is what `toFixed` wrote; a digit count the runtime cannot format falls back to
 * `toFixed` itself.
 *
 * Ungrouped by default, as `toFixed` was, so only the decimal mark moves. `grouping` true groups the
 * thousands the locale's way, `278.984,46` in German. Given marks (`{ group, decimal }`), it groups
 * as the locale does and writes those two marks instead, for an application whose numbers follow an
 * account's settings rather than the language.
 *
 * @param {number} value
 * @param {string|null|undefined} locale  BCP-47, as the view spells it
 * @param {number} maximumFractionDigits
 * @param {number} [minimumFractionDigits]
 * @param {boolean|{group: string, decimal: string}} [grouping]
 * @returns {string}
 */
export function formatDecimal(value, locale, maximumFractionDigits, minimumFractionDigits = 0, grouping = false) {
    const marks = grouping && typeof grouping === 'object' && typeof grouping.group === 'string' && typeof grouping.decimal === 'string' ? grouping : null;
    const options = { useGrouping: Boolean(grouping), minimumFractionDigits, maximumFractionDigits };
    const write = (formatter) => {
        // A value whose size is written as zero is written as zero. The formatter keeps the sign of
        // a negative zero, and ApexCharts builds the zero line of an axis in tenths a hair below it.
        const number = Number(value);
        const shown = number <= 0 && number > -1 && formatter.format(-number) === formatter.format(0) ? 0 : value;

        return marks === null
            ? formatter.format(shown)
            : formatter.formatToParts(shown).map((part) => (part.type === 'group' ? marks.group : part.type === 'decimal' ? marks.decimal : part.value)).join('');
    };

    try {
        return write(new Intl.NumberFormat(locale || 'en', options));
    } catch {
        // The tag, or a digit count an older engine caps at 20. `toFixed` takes 0 to 100.
        try {
            return write(new Intl.NumberFormat('en', options));
        } catch {
            const fixed = Number(value).toFixed(maximumFractionDigits);

            return Number(fixed) === 0 ? fixed.replace('-', '') : fixed;
        }
    }
}

/**
 * The most places after the point that any of `numbers` carries.
 *
 * Binary floating point holds no tenth exactly, so 0.1 + 0.2 is 0.30000000000000004. The numbers
 * a slider is configured with say how many places its values can need, and rounding to that many
 * gives the value a decimal step means. `String()` writes very small numbers with an exponent
 * (`1e-7`), which is counted as places too.
 *
 * @param {...number} numbers
 * @returns {number}
 */
export function decimalPlaces(...numbers) {
    return Math.max(0, ...numbers.map((number) => {
        const [mantissa, exponent = '0'] = String(number).toLowerCase().split('e');
        const fraction = (mantissa.split('.')[1] || '').replace(/0+$/, '');

        return Math.min(100, Math.max(0, fraction.length - Number(exponent)));
    }));
}

/**
 * A number on a scale, the value of a slider or the label of one of its marks: the decimal mark of
 * the locale, or of the application's own marks where it has them, and no grouping. A scale may
 * count years, and `2.026` reads as neither. Mirrors `LocalizedNumber::scale()` on the server.
 *
 * @param {number} value
 * @param {string|null|undefined} locale  BCP-47, as the view spells it
 * @param {number} maximumFractionDigits
 * @param {{group: string, decimal: string}|null} [marks]
 * @returns {string}
 */
export function scaleText(value, locale, maximumFractionDigits, marks = null) {
    if (marks && typeof marks.decimal === 'string') {
        return formatDecimal(value, 'en', maximumFractionDigits).replace('.', marks.decimal);
    }

    return formatDecimal(value, locale, maximumFractionDigits);
}

/**
 * A count as the reader sees it: grouped as the locale groups one, `12.500` in German, or in the
 * application's own marks where it has them. Without a locale it is written as digits alone.
 * Mirrors `LocalizedNumber::count()` on the server for a whole number.
 *
 * @param {number} value
 * @param {string|null|undefined} locale  BCP-47, as the view spells it
 * @param {{group: string, decimal: string}|null} [marks]
 * @returns {string}
 */
export function formatCount(value, locale, marks = null) {
    return typeof locale === 'string' && locale !== '' ? formatDecimal(value, locale, 0, 0, marks ?? true) : String(value);
}

export default formatDecimal;
