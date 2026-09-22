/**
 * Says so when the browser writes dates in a different order than the page does.
 *
 * A native `<input type="date">` draws its order from the BROWSER's user-interface language,
 * never from the language of the page around it, and no author can change that — the format of
 * a native date field is not an authorable property in any shipped browser. So a German page
 * read on an English laptop asks for `mm/dd/yyyy` while every word beside it is German.
 *
 * ⚠️ AND THE MISREADING IS SILENT, which is the whole reason this exists. `03.04.2026` and
 * `04/03/2026` are both valid dates; nothing is rejected, no error appears, and the record
 * simply carries a different day than the person meant. Reported against a shop whose
 * operators work on company laptops with an English system language.
 *
 * What this does NOT do: change the order. It states it, in the language of the page, with a
 * worked example rather than a letter pattern — `dd/mm/yyyy` has to be decoded, "4 March 2026 is
 * written 03/04/2026 here" does not.
 *
 * It renders NOTHING when the two agree, which is the overwhelmingly common case. An empty
 * string rather than a hidden element: the paragraph is already in `aria-describedby`, and an
 * element with no text contributes nothing to a computed description.
 *
 * @param {Object} config
 * @param {string} config.locale    the APPLICATION's locale, BCP-47, from `app()->getLocale()`
 * @param {string} config.template  the translated sentence, with `:appDate` and `:fieldDate`
 */
export default function wirekitDateFormatHint(config = {}) {
    return {
        message: '',

        _locale: config.locale || 'en',
        _template: config.template || '',

        init() {
            this.message = this.resolve();
        },

        /**
         * The order the given locale writes a date in, as the sequence of its date parts.
         *
         * `formatToParts` rather than a formatted string, because reading the order back out of
         * "03/04/2026" means guessing which number is which — the exact ambiguity this component
         * is about.
         */
        order(locale) {
            try {
                return new Intl.DateTimeFormat(locale, { dateStyle: 'short' })
                    .formatToParts(new Date(Date.UTC(2026, 2, 4)))
                    .filter((part) => part.type === 'day' || part.type === 'month' || part.type === 'year')
                    .map((part) => part.type)
                    .join('-');
            } catch {
                // An unknown locale tag throws rather than falling back. Returning null makes
                // the comparison below answer "cannot tell", which renders nothing — the same
                // outcome as agreement, and the right one: a hint nobody can verify is worse
                // than no hint.
                return null;
            }
        },

        /**
         * The browser's own tag, which is what a native date field follows.
         *
         * `navigator.language` is the UI language and is what the field reads. Not
         * `Intl.DateTimeFormat().resolvedOptions().locale`, which resolves through the same
         * negotiation but can land on a region the field does not use.
         */
        browserLocale() {
            return (typeof navigator !== 'undefined' && navigator.language) || null;
        },

        resolve() {
            const browser = this.browserLocale();

            if (! browser || ! this._template) {
                return '';
            }

            const appOrder = this.order(this._locale);
            const fieldOrder = this.order(browser);

            if (appOrder === null || fieldOrder === null || appOrder === fieldOrder) {
                return '';
            }

            // A date whose day and month cannot be confused for one another would defeat the
            // example. The 4th of March is deliberately ambiguous as digits and unambiguous
            // when spelled, which is exactly the pair the sentence contrasts.
            const sample = new Date(Date.UTC(2026, 2, 4));

            let appDate;
            let fieldDate;

            try {
                appDate = new Intl.DateTimeFormat(this._locale, { dateStyle: 'long', timeZone: 'UTC' }).format(sample);
                fieldDate = new Intl.DateTimeFormat(browser, { dateStyle: 'short', timeZone: 'UTC' }).format(sample);
            } catch {
                return '';
            }

            return this._template
                .replace(':appDate', appDate)
                .replace(':fieldDate', fieldDate);
        },
    };
}
