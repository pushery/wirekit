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
 * `Pushery\WireKit\Support\SafeUrl` applies the same rule on the server, and both give the same
 * answer for the same value.
 */

/** The schemes a link may use. A URL without a scheme is relative and always kept. */
export const LINK_SCHEMES = Object.freeze(['http', 'https', 'mailto', 'tel']);

/** The schemes a frame or an image may load from. */
export const LOAD_SCHEMES = Object.freeze(['http', 'https']);

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
 * The URL without surrounding whitespace, or '' when its scheme is not one of `schemes`.
 *
 * '' is also what an empty or absent value gives, so a caller has one value to test for "no link".
 *
 * @param {*} value
 * @param {ReadonlyArray<string>} [schemes] lowercase
 * @returns {string}
 */
export function safeHref(value, schemes = LINK_SCHEMES) {
    const href = value === null || value === undefined ? '' : String(value).trim();
    const scheme = hrefScheme(href);

    return scheme === null || schemes.includes(scheme) ? href : '';
}
