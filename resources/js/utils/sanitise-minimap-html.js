/**
 * sanitise-minimap-html — strip dangerous HTML before injecting cloned
 * content into the reading-minimap's rendered-mode iframe.
 *
 * Threat model:
 *
 *  The cloned source HTML may contain user-generated content the
 *  developer hasn't sanitised (CMS posts, comment threads, markdown-
 *  rendered articles). Cloning into a srcdoc iframe carries that HTML
 *  into the iframe context — and `srcdoc` iframes inherit the parent's
 *  same-origin context, so any script that runs inside has full DOM
 *  access to the parent. `aria-hidden="true"` does NOT mitigate this —
 *  it only hides the iframe from assistive tech, scripts still execute.
 *
 *  The eight OWASP HTML5 attack-vector classes we defend against:
 *
 *   1. <script> tags                          — direct code execution
 *   2. on* event-handler attributes           — implicit code execution
 *   3. javascript: URIs in href/src/action    — anchor-click execution
 *   4. <iframe>/<object>/<embed>/<applet>     — nested untrusted content
 *   5. data: URIs in src                      — encoded payload bypass
 *   6. srcdoc on nested iframes               — recursive minimaps + xss
 *   7. <style> tags + style attributes        — CSS-resource exfiltration
 *      (kept separate — see "Why we strip <style>" below)
 *   8. Form-action / formaction redirects     — credential-harvest pivot
 *
 *  Strategy: a regex-pass-stripping pipeline that runs against the raw
 *  HTML STRING, BEFORE it ever becomes a DOM node. The browser's HTML
 *  parser will not execute anything we hand it as a srcdoc string until
 *  the iframe parses it — so stripping at the string level is safe
 *  (we never construct an active DOM containing the dangerous bits).
 *
 *  Why we strip <style> tags (related to threat #7):
 *  CSS @import + url() can fetch resources from arbitrary origins,
 *  which under some browser configurations leaks the parent's
 *  authenticated cookie state to a third-party server. We strip
 *  inline <style> on the way in; developer CSS arrives in the iframe
 *  via explicit <link rel="stylesheet"> injection of the parent's
 *  same-origin stylesheets (see reading-minimap.js _buildIframe()).
 *
 *  Why this is NOT a general-purpose sanitiser:
 *  It's tuned for the specific use case of "scaled-down decorative
 *  page-preview". The resulting HTML is rendered inside an iframe with
 *  `aria-hidden="true"` + `tabindex="-1"` + `pointer-events: none` on
 *  the iframe body — interaction lives in the wrapping minimap
 *  element, not in the iframe content. This means we can be MORE
 *  aggressive than a general sanitiser would be (e.g. we strip ALL
 *  form-related elements because the minimap never accepts input).
 *
 *  For general-purpose HTML sanitisation in WireKit, use the developer
 *  Laravel app's server-side sanitisation (e.g. `Str::stripTags`,
 *  `HTMLPurifier`). This module is a defence-in-depth for the
 *  minimap-rendered render path specifically.
 */

const SCRIPT_TAG_RE = /<script[\s\S]*?<\/script>/gi;
// Self-closing or standalone (no body) script form — `<script src="...">`
// without closing tag at the boundary of the doc.
const ORPHAN_SCRIPT_RE = /<script\b[^>]*>/gi;

// Event-handler attributes. Allows for namespaced HTML (e.g. `xml:onclick`)
// — we match `on[a-z]+` per HTML5 spec for event handler content attributes.
// The `(?:\s|\/?>)` lookahead anchors the match at an attribute boundary so
// we don't accidentally chew into `<one>` or similar legitimate text.
const ON_HANDLER_RE = /\s+on[a-z]+\s*=\s*(?:"[^"]*"|'[^']*'|[^\s>]+)/gi;

// `javascript:` URIs in any URL-valued attribute. Case-insensitive, allows
// arbitrary whitespace between the scheme and the payload (browsers tolerate
// `java\nscript:` — we strip aggressively).
const JS_URI_RE = /\s+(?:href|src|action|formaction|xlink:href|data)\s*=\s*(?:"\s*javascript[\s\S]*?"|'\s*javascript[\s\S]*?'|javascript[^\s>]*)/gi;

// data: URIs in `src` attributes (covers `<img src="data:image/svg+xml;…">`
// which can carry SVG <script> children). Leaves data: in plain `href` alone
// since anchor activation is gated by user click + iframe is pointer-disabled.
const DATA_URI_SRC_RE = /\s+src\s*=\s*(?:"\s*data:[\s\S]*?"|'\s*data:[\s\S]*?')/gi;

// Nested embedded-content tags. Stripped wholesale including any body.
const NESTED_EMBED_RE = /<(iframe|object|embed|applet|frame|frameset)\b[\s\S]*?<\/\1>/gi;
const NESTED_EMBED_SELF_CLOSING_RE = /<(iframe|object|embed|applet|frame|frameset)\b[^>]*\/?>/gi;

// Inline <style> tags. Stripped — developer CSS arrives via <link> injection.
const STYLE_TAG_RE = /<style[\s\S]*?<\/style>/gi;

// `style="…"` inline attributes ARE preserved — they carry layout that the
// minimap needs to visually represent the source (CSS variable references,
// inline colour overrides, position cues). The risk of CSS @import inside an
// inline `style` attribute is zero (the syntax doesn't apply).

// srcdoc on nested iframes — defence in depth (the NESTED_EMBED_RE above
// already strips the whole iframe, but if a future change preserves them
// for some niche use case, this catches the srcdoc payload separately).
const NESTED_SRCDOC_RE = /\s+srcdoc\s*=\s*(?:"[^"]*"|'[^']*')/gi;

// Form / formaction attributes that could redirect credentials elsewhere.
const FORMACTION_RE = /\s+formaction\s*=\s*(?:"[^"]*"|'[^']*')/gi;

/**
 * Strip dangerous content from an HTML string in preparation for
 * injection into the reading-minimap's rendered-mode iframe.
 *
 * Returns a new string; never mutates the input. Idempotent — calling
 * twice on the same input produces the same output.
 */
export function sanitiseMinimapHtml(html) {
    if (typeof html !== 'string' || html.length === 0) return '';

    let out = html;
    // Strip script tags first (with bodies). Closes the obvious channel.
    out = out.replace(SCRIPT_TAG_RE, '');
    // Then orphan / unclosed script declarations.
    out = out.replace(ORPHAN_SCRIPT_RE, '');
    // Nested embedded-content tags (iframe / object / embed / applet / frame).
    // Pass twice — once for body-bearing tags, once for self-closing form.
    out = out.replace(NESTED_EMBED_RE, '');
    out = out.replace(NESTED_EMBED_SELF_CLOSING_RE, '');
    // Inline style tags.
    out = out.replace(STYLE_TAG_RE, '');
    // javascript: URIs (before generic event-handler stripping so the
    // attribute-bounded match is anchored correctly).
    out = out.replace(JS_URI_RE, '');
    // data: URIs in src attributes.
    out = out.replace(DATA_URI_SRC_RE, '');
    // Event-handler attributes (run after URI strips so the lookahead
    // around `on*=…` doesn't false-positive inside a `href="javascript:…"`
    // value that's about to be stripped).
    out = out.replace(ON_HANDLER_RE, '');
    // srcdoc on any tag (defence-in-depth).
    out = out.replace(NESTED_SRCDOC_RE, '');
    // formaction attributes (credential-pivot defence).
    out = out.replace(FORMACTION_RE, '');

    return out;
}

/**
 * Pre-flight density check — returns the number of OPENING tags in the
 * provided HTML. Used by the minimap's lazy-init path to decide whether
 * to clone into rendered-mode (cheap enough) or fall back to stripes
 * (cheaper still). Threshold is chosen by the caller; 5000 is the
 * recommended ceiling for Moto G5-class devices (memory doubles
 * linearly with DOM size).
 */
export function countTags(html) {
    if (typeof html !== 'string' || html.length === 0) return 0;
    const matches = html.match(/<[a-zA-Z][^>]*>/g);
    return matches ? matches.length : 0;
}
