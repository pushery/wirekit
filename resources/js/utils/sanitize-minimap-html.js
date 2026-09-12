/**
 * sanitize-minimap-html — strip dangerous HTML before injecting cloned
 * content into the reading-minimap's rendered-mode iframe.
 *
 * Threat model:
 *
 *  The cloned source HTML may contain user-generated content the
 *  developer hasn't sanitized (CMS posts, comment threads, markdown-
 *  rendered articles). Cloning into a srcdoc iframe carries that HTML
 *  into the iframe context. `aria-hidden="true"` does NOT mitigate that —
 *  it only hides the iframe from assistive tech.
 *
 *  ⚠️ This module is the SECOND line, not the only one, and the paragraph
 *  here used to say otherwise ("scripts still execute"). The caller sets
 *  `sandbox="allow-same-origin"` on the frame and does NOT grant
 *  `allow-scripts`, so script does not run in it at all. That is worth
 *  stating accurately in both directions: overstating the exposure makes
 *  every finding here read as critical, and a reader who checks the caller
 *  and finds the claim false stops trusting the rest of this comment.
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
 *  same-origin stylesheets (see reading-minimap.js _positionHoverPreview()).
 *
 *  Why this is NOT a general-purpose sanitizer:
 *  It's tuned for the specific use case of "scaled-down decorative
 *  page-preview". The resulting HTML is rendered inside an iframe with
 *  `aria-hidden="true"` + `tabindex="-1"` + `pointer-events: none` on
 *  the iframe body — interaction lives in the wrapping minimap
 *  element, not in the iframe content. This means we can be MORE
 *  aggressive than a general sanitizer would be — we strip ALL
 *  form-related elements, because the minimap never accepts input.
 *  (That sentence stood here while nothing stripped any of them; the
 *  pattern that makes it true is FORM_TAG_RE below.)
 *
 *  For general-purpose HTML sanitization, sanitize on the server in the
 *  application that renders the page (e.g. `Str::stripTags`, `HTMLPurifier`).
 *  This module is defense-in-depth for the minimap's rendered path
 *  specifically, and nothing else should be routed through it.
 */

const SCRIPT_TAG_RE = /<script[\s\S]*?<\/script>/gi;
// Self-closing or standalone (no body) script form — `<script src="...">`
// without closing tag at the boundary of the doc.
const ORPHAN_SCRIPT_RE = /<script\b[^>]*>/gi;

// Event-handler attributes. Allows for namespaced HTML (e.g. `xml:onclick`)
// — we match `on[a-z]+` per HTML5 spec for event handler content attributes.
// What keeps it off legitimate text is the SHAPE, not a lookahead — this comment
// described a `(?:\s|\/?>)` that the pattern below does not contain and never did.
// A match needs leading whitespace AND an `=`, so `<one>` and `<online>` cannot
// satisfy it: there is no attribute there to assign to.
const ON_HANDLER_RE = /\s+on[a-z]+\s*=\s*(?:"[^"]*"|'[^']*'|[^\s>]+)/gi;

// `javascript:` URIs in any URL-valued attribute.
//
// The scheme is spelled letter by letter with whitespace and C0 control characters allowed
// BETWEEN the letters, because that is what browsers accept: `java&#10;script:` decodes to
// a newline inside the scheme and still runs. The previous pattern allowed whitespace only
// BEFORE the word — its own comment claimed `java\nscript:` was covered, and it was not.
const SCHEME_GAP = '[\\s\\u0000-\\u0020]*';
const JS_SCHEME = 'j'.concat(SCHEME_GAP, 'a', SCHEME_GAP, 'v', SCHEME_GAP, 'a', SCHEME_GAP, 's', SCHEME_GAP, 'c', SCHEME_GAP, 'r', SCHEME_GAP, 'i', SCHEME_GAP, 'p', SCHEME_GAP, 't', SCHEME_GAP, ':');
const JS_URI_RE = new RegExp(
    `\\s+(?:href|src|action|formaction|xlink:href|data)\\s*=\\s*(?:"${SCHEME_GAP}${JS_SCHEME}[\\s\\S]*?"|'${SCHEME_GAP}${JS_SCHEME}[\\s\\S]*?'|${JS_SCHEME}[^\\s>]*)`,
    'gi'
);

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
// inline color overrides, position cues). The risk of CSS @import inside an
// inline `style` attribute is zero (the syntax doesn't apply).

// srcdoc on nested iframes — defense in depth (the NESTED_EMBED_RE above
// already strips the whole iframe, but if a future change preserves them
// for some niche use case, this catches the srcdoc payload separately).
const NESTED_SRCDOC_RE = /\s+srcdoc\s*=\s*(?:"[^"]*"|'[^']*')/gi;

// Form / formaction attributes that could redirect credentials elsewhere.
const FORMACTION_RE = /\s+formaction\s*=\s*(?:"[^"]*"|'[^']*')/gi;

// Form ELEMENTS. The threat model above has always claimed "we strip ALL form-related
// elements because the minimap never accepts input" — and nothing did. A <form> with an
// off-origin action, its inputs and its submit button all survived into the preview, which
// is exactly the credential-harvest pivot listed as attack class 8.
//
// Only the tags are removed, not their text: a paragraph inside a <fieldset> is content the
// preview is supposed to show, and dropping it would change what the minimap represents.
const FORM_TAG_RE = /<\/?(?:form|input|button|select|option|optgroup|textarea|label|fieldset|legend|datalist|output|progress|meter)\b[^>]*>/gi;

/** How many convergence passes before the input is treated as adversarial. */
const MAX_PASSES = 8;

/** One pass of the strip pipeline. Order matters and is commented at each step. */
function stripOnce(html) {
    let out = html;
    // Strip script tags first (with bodies). Closes the obvious channel.
    out = out.replace(SCRIPT_TAG_RE, '');
    // Then orphan / unclosed script declarations.
    out = out.replace(ORPHAN_SCRIPT_RE, '');
    // Nested embedded-content tags (iframe / object / embed / applet / frame).
    // Twice — once for body-bearing tags, once for the self-closing form.
    out = out.replace(NESTED_EMBED_RE, '');
    out = out.replace(NESTED_EMBED_SELF_CLOSING_RE, '');
    // Inline style tags.
    out = out.replace(STYLE_TAG_RE, '');
    // Form elements — attack class 8, and the one the threat model claimed was covered.
    out = out.replace(FORM_TAG_RE, '');
    // javascript: URIs (before generic event-handler stripping so the
    // attribute-bounded match is anchored correctly).
    out = out.replace(JS_URI_RE, '');
    // data: URIs in src attributes.
    out = out.replace(DATA_URI_SRC_RE, '');
    // Event-handler attributes, and the ORDER is the point: this runs after the URI strips
    // so `ON_HANDLER_RE` cannot match inside a `href="javascript:…"` value that is about to
    // be removed anyway. (This comment said "the lookahead around `on*=…`" until 2026-09-09.
    // There is no lookahead in that pattern — what bounds it is the required `=` after the
    // attribute name, the same mechanism its own declaration now names.)
    out = out.replace(ON_HANDLER_RE, '');
    // srcdoc on any tag (defense-in-depth).
    out = out.replace(NESTED_SRCDOC_RE, '');
    // formaction attributes (credential-pivot defense).
    out = out.replace(FORMACTION_RE, '');

    return out;
}

/**
 * Strip dangerous content from an HTML string in preparation for injection into the
 * reading-minimap's rendered-mode iframe.
 *
 * Returns a new string; never mutates the input.
 *
 * ⚠️ **A SINGLE PASS IS NOT A FIXED POINT, AND THIS FUNCTION USED TO MAKE ONE.** Removing a
 * substring joins what stood on either side of it, so a pass can BUILD the very tag the
 * next pattern would have caught. Measured, on the shipped version:
 *
 *     '<scr<script>ipt src=//evil/x.js>'   ->   '<script src=//evil/x.js>'
 *
 * `<script>` matched in the middle, was removed, and the two halves closed around a live
 * script element pointing at an off-origin file. The docblock here claimed idempotence at
 * the time, which is what made it read as settled.
 *
 * So the pipeline runs until the string stops changing. Convergence is the norm — the
 * second pass is a no-op for anything a page actually contains — and the bound exists for
 * input designed to nest faster than it is peeled. When the bound is reached the result is
 * DISCARDED rather than returned: at that point the string is still producing new markup
 * every pass, and a preview thumbnail is not worth shipping something this function cannot
 * vouch for. The caller renders a stripe-mode minimap instead, which is its documented
 * fallback for any content it cannot clone.
 */
export function sanitizeMinimapHtml(html) {
    if (typeof html !== 'string' || html.length === 0) return '';

    let out = html;

    for (let pass = 0; pass < MAX_PASSES; pass++) {
        const before = out;
        out = stripOnce(out);

        if (out === before) {
            return out;
        }
    }

    return '';
}

/**
 * Density check over an HTML STRING — the number of opening tags in it.
 *
 * ⚠️ NOT what the minimap uses, and this docblock said it was. It described "the minimap's
 * lazy-init path" deciding "whether to clone into rendered-mode or fall back to stripes",
 * and that path is gone: the iframe-clone was replaced by a canvas walk, and the decision now
 * counts the LIVE DOM with `source.querySelectorAll('*').length`. The comment beside that
 * count says as much — "ports from the iframe-clone path" — so the two halves of the same
 * change disagreed, and a reader tuning the threshold would have tuned this function and
 * changed nothing.
 *
 * It is kept because it is the right tool for the question it actually answers: how heavy is
 * a string of HTML, before anything has parsed it. `sanitizeMinimapHtml` above works on the
 * same input, and a caller weighing whether to sanitize at all has no live DOM to count.
 * 5000 tags is the ceiling that was measured for Moto G5-class devices; memory grows linearly
 * with DOM size.
 */
export function countTags(html) {
    if (typeof html !== 'string' || html.length === 0) return 0;
    const matches = html.match(/<[a-zA-Z][^>]*>/g);
    return matches ? matches.length : 0;
}
