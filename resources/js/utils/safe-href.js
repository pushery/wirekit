/**
 * A link target that came from data, reduced to one that cannot run script.
 *
 * Escaping does not stop a `javascript:` URL, and a value bound with `:href` is not escaped at
 * all: the browser runs it in the page's origin on the click. Rows, notifications and anything
 * else that arrives as data is often data somebody else typed, so every factory that binds such a
 * value to an `href` passes it through here first.
 *
 * The scheme is read the way the URL parser reads it. It drops control characters and spaces from
 * the start and removes tabs and newlines anywhere before it looks for a scheme, so
 * `\x01javascript:` and `java\tscript:` are both `javascript:` to it, and a check on the raw string
 * lets both through. Anything else before the first colon, a slash or a space, makes the URL
 * relative, and a relative URL stays on the page's own scheme.
 *
 * The URL is read a second time with its character references decoded. On the server that closes
 * a way past the first reading: an application can tell Blade to leave a reference standing, and
 * the browser then reads `&#106;avascript:` in an attribute as `javascript:`. A value bound here is
 * never parsed as HTML, so nothing decodes it. This half applies the reading all the same, because
 * a target the server refuses is not one the browser links.
 *
 * `Pushery\WireKit\Support\SafeUrl` applies the same rule on the server, and both give the same
 * answer for the same value.
 */

/** The schemes a link may use. A URL without a scheme is relative and always kept. */
export const LINK_SCHEMES = Object.freeze(['http', 'https', 'mailto', 'tel']);

/** The schemes a frame or an image may load from. */
export const LOAD_SCHEMES = Object.freeze(['http', 'https']);

/**
 * The named character references that decode to a character a scheme can hold, or one the URL
 * parser removes before it reads the scheme. Every other name decodes to a character that ends
 * the scheme, which is what the ampersand it begins with does as well.
 */
const SCHEME_REFERENCES = Object.freeze({ Tab: '\t', NewLine: '\n', colon: ':', plus: '+', period: '.', fjlig: 'fj' });

/**
 * The scheme the browser reads from the URL, lowercase, or null for a relative URL.
 *
 * @param {string} url
 * @returns {string|null}
 */
export function hrefScheme(url) {
    let start = 0;

    while (start < url.length && url.charCodeAt(start) <= 0x20) {
        start++;
    }

    const match = url.slice(start).replace(/[\t\n\r]/g, '').match(/^([a-z][a-z0-9+.-]*):/i);

    return match ? match[1].toLowerCase() : null;
}

/**
 * The URL as an HTML parser reads it from an attribute whose character references were left
 * standing: every numeric reference, with or without its semicolon, and the named ones that
 * matter to a scheme.
 *
 * @param {string} url
 * @returns {string}
 */
function withReferencesDecoded(url) {
    return url.replace(/&(?:#(?:[xX]([0-9A-Fa-f]+)|([0-9]+));?|(Tab|NewLine|colon|plus|period|fjlig);)/g, (reference, hexadecimal, decimal, name) => {
        if (name) {
            return SCHEME_REFERENCES[name];
        }

        const digits = (hexadecimal ?? decimal).replace(/^0+/, '');

        // U+10FFFF is the last code point: six hexadecimal digits, seven decimal ones.
        const point = digits === '' || digits.length > 7 ? 0 : parseInt(digits, hexadecimal ? 16 : 10);

        // The parser puts U+FFFD in place of a reference to U+0000, to a surrogate or to a number
        // past the last code point.
        if (point === 0 || point > 0x10FFFF || (point >= 0xD800 && point <= 0xDFFF)) {
            return '\uFFFD';
        }

        return String.fromCodePoint(point);
    });
}

/**
 * The URL without surrounding whitespace, or '' when its scheme is not one of `schemes`, read
 * from the text or from the text with its character references decoded.
 *
 * '' is also what an empty or absent value gives, so a caller has one value to test for "no link".
 *
 * @param {*} value
 * @param {ReadonlyArray<string>} [schemes] lowercase
 * @returns {string}
 */
export function safeHref(value, schemes = LINK_SCHEMES) {
    const href = value === null || value === undefined ? '' : String(value).trim();
    const allowed = (url) => {
        const scheme = hrefScheme(url);

        return scheme === null || schemes.includes(scheme);
    };

    // Only an ampersand can begin a character reference.
    return allowed(href) && (! href.includes('&') || allowed(withReferencesDecoded(href))) ? href : '';
}
