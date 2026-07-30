/**
 * Reaction — an emoji, a count, and one boolean that both of them follow.
 *
 * This factory mounts ONLY when the component opts into optimistic UI. Without
 * it the reaction is server-rendered markup with no Alpine at all, exactly as it
 * has always been.
 *
 * **The count is derived, and that is what makes the rollback correct.** A
 * reaction changes two things at once — whether you reacted, and how many
 * people did — and rolling back two values independently is how they end up
 * disagreeing. Only `active` is state here; the count is `base + (active ? 1 :
 * 0)`, so putting `active` back puts the count back with it, and no code has to
 * remember to.
 *
 * The accessible name is built HERE rather than on the server, which is the
 * whole reason this file exists. `trans_choice` on the server would name the
 * count the server saw: the page would show six and announce five, and the
 * rollback would restore a number the user was never told about. The forms and
 * the locale travel instead, and `Intl.PluralRules` chooses — the rule, not a
 * ternary, because Polish has three categories and Arabic six.
 *
 * @param {Object}  config
 * @param {boolean} config.active   whether the current reader has reacted
 * @param {number}  config.count    the total INCLUDING the reader's own
 * @param {string}  config.emoji    read before the count, e.g. "👍, 5 people reacted"
 * @param {Object}  config.phrases  sample count -> template, from PluralPhrases
 * @param {string}  config.locale   the application's locale
 */
import { pluralize } from '../utils/plural.js';

export default function wirekitReaction(config = {}) {
    return {
        active: config.active === true,

        // The total WITHOUT the reader's own reaction. Derived once at mount so
        // the count can be recomputed from `active` alone from then on.
        _base: Math.max(0, Number(config.count || 0) - (config.active === true ? 1 : 0)),
        _emoji: config.emoji || '',
        _phrases: config.phrases || {},
        _locale: config.locale || 'en',

        get count() {
            return this._base + (this.active ? 1 : 0);
        },

        get label() {
            return this._emoji + ', ' + pluralize(this._phrases, this.count, this._locale);
        },
    };
}
