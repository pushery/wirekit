/**
 * External link question — asks before a link takes the reader to another site.
 *
 * `<x-wirekit::external-link-question>` stands once in a layout. A click on any link of the page,
 * an `a` or the `area` of an image map, whose address is another site, one neither the page's own
 * nor trusted, is held, and the kit's
 * alert dialog names the host and the whole address before the reader goes on. A plain click, a
 * click with a key that opens another tab or window, and a click of the middle button all ask, and
 * Visit then goes where that click would have gone: this window, the window above a frame that the
 * link's `target` names (`_top`, `_parent`), or a new tab. The target is read as the browser reads
 * it (`utils/link-window.js`), and a window the link names by a name of its own is a new tab. The
 * middle button asks where the browser reports it as `auxclick`, which Safari does from 18.2 on: a
 * progressive enhancement, with no listener where the event does not exist.
 *
 * Heard on the document after the link's own listeners, so a link the page or Livewire handles
 * itself, which prevents the click, is left to them.
 *
 * The address shown is the one the browser calls, `URL.href`: a host outside ASCII is already its
 * punycode there, and a user name before the `@` stands in plain sight. Where the link writes its
 * host in other characters, that spelling is shown beside it, so both are visible.
 *
 * Trusted are the page's own origin, the hosts of the configuration and of the `trusted` prop, the
 * hosts a page names at run time in `wirekit:external-links-trust` on `window`, the hosts the reader
 * chose not to be warned about again, and a link with `data-wk-trusted`. A host is matched as it is
 * or, written with a leading `*.`, with every host under that domain and the domain itself.
 *
 * A link that decides its referrer itself, with `noreferrer` in its `rel` or a `referrerpolicy` of
 * its own, keeps that decision: a navigation by script sends what the page's policy allows, so
 * Visit takes such a link through an anchor that carries both (`followThroughAnAnchor()`).
 *
 * The link takes the focus before the dialog opens, so the dialog closes back onto it.
 *
 * Cleanup: the document's two listeners and the window's one, each held under a `_` field and
 * released in `destroy()`.
 */

import { clickedLink, windowTheLinkOpens } from '../utils/link-window.js';

export const WK_EXTERNAL_LINKS_TRUST_EVENT = 'wirekit:external-links-trust';
export const WK_EXTERNAL_LINK_TRUSTED_EVENT = 'wirekit:external-link-trusted';

/**
 * Where the hosts a reader chose not to be warned about again are kept, with `remember="browser"`.
 * Written with hyphens, as `wirekit-theme` is: a name with `wirekit:` is an event of the kit.
 */
export const WK_EXTERNAL_LINKS_STORAGE_KEY = 'wirekit-external-links-trusted';

/**
 * A host as one entry of a trust list, read as the browser reads the host of an address:
 * lowercased, without a trailing dot, in the form the browser calls it (`müller.de` is
 * `xn--mller-kva.de`, an IPv6 address stands in brackets), and without what is not the host. An
 * entry written as an address, `https://docs.example.com/guide` or `docs.example.com:8443`, names
 * `docs.example.com` on every port, as the page's own host is matched on every port. A leading
 * `*.` stays, and a value that names no host is null.
 *
 * @param {unknown} value
 * @returns {string|null}
 */
export function normalizedHost(value) {
    if (typeof value !== 'string') {
        return null;
    }

    // A scheme before `//` is left off; the rest is read as the browser reads an address after it.
    let entry = value.trim().toLowerCase().replace(/^[a-z][a-z0-9+.-]*:[/\\]{2}/, '');
    const wildcard = entry.startsWith('*.');

    if (wildcard) {
        entry = entry.slice(2);
    }

    // An IPv6 address written without the brackets a link carries it in.
    if (/^[0-9a-f.]*:[0-9a-f.]*:[0-9a-f:.]*$/.test(entry)) {
        entry = `[${entry}]`;
    }

    let host;

    try {
        host = new URL(`http://${entry}`).hostname.replace(/\.$/, '');
    } catch {
        return null;
    }

    return /^[a-z0-9.\-[\]:]+$/.test(host) ? (wildcard ? '*.' : '') + host : null;
}

/**
 * Whether one entry of a trust list matches a host: the same host, or, for `*.example.com`,
 * `example.com` and every host under it.
 *
 * @param {string} host the host the browser calls, lowercased
 * @param {string} entry a normalized entry
 */
export function hostMatches(host, entry) {
    if (entry.startsWith('*.')) {
        const domain = entry.slice(2);

        return host === domain || host.endsWith(`.${domain}`);
    }

    return host === entry;
}

/**
 * The address a link leads to when it opens a site, resolved as the browser resolves it, or null
 * for a link that opens none: `mailto:`, `tel:`, a script, an address the browser cannot read.
 *
 * @param {Element} link
 * @param {string} base
 * @returns {URL|null}
 */
export function siteAddressOf(link, base) {
    try {
        const url = new URL(String(link.getAttribute('href')), base);

        return url.protocol === 'http:' || url.protocol === 'https:' ? url : null;
    } catch {
        return null;
    }
}

/**
 * The host as the link writes it, where that differs from the host the browser calls: a host in
 * characters outside ASCII, which the browser calls by its punycode. Null where both agree, and
 * for an address relative to the page.
 *
 * The address is split where the browser splits one of `http` and `https`: the authority ends at
 * the first `/`, `\`, `?` or `#`, a user name in it at the last `@`, and the host at the colon of
 * a port or, for an IPv6 address, at its closing bracket. So `https://evil.net\@example.com/`
 * writes `evil.net`, the host the browser calls, and nothing is named beside it. A tab or a line
 * break in the address is left out, as the browser leaves it out.
 *
 * @param {Element} link
 * @param {URL} url
 * @returns {string|null}
 */
export function writtenHostOf(link, url) {
    const href = String(link.getAttribute('href') ?? '').trim().replace(/[\t\n\r]/g, '');
    const match = /^[a-z][a-z0-9+.-]*:[/\\]{2}([^/\\?#]*)/i.exec(href);

    if (! match) {
        return null;
    }

    const authority = match[1].slice(match[1].lastIndexOf('@') + 1);
    const host = authority.startsWith('[') ? authority.slice(0, authority.indexOf(']') + 1) : authority.split(':')[0];
    const written = host.toLowerCase().replace(/\.$/, '');

    return written !== '' && written !== url.hostname.replace(/\.$/, '') ? written : null;
}

/**
 * Whether a link decides itself what it sends as the address it came from: `noreferrer` in its
 * `rel`, or a `referrerpolicy` of its own.
 *
 * @param {Element} link
 * @returns {boolean}
 */
export function decidesItsReferrer(link) {
    const rel = String(link?.getAttribute?.('rel') ?? '').toLowerCase().split(/\s+/);

    return rel.includes('noreferrer') || link?.hasAttribute?.('referrerpolicy') === true;
}

/**
 * Go to the held address through an anchor in the link's document that carries the link's `rel`
 * and `referrerpolicy` and the window the click would have opened, so the browser sends what the
 * link decided. `data-wk-trusted` lets the anchor's own click pass the question. False where there
 * is no document to put it in, and the caller then goes the way it goes for any other link.
 *
 * @param {{url: string, link: Element, opens: string}} held
 * @returns {boolean}
 */
export function followThroughAnAnchor(held) {
    const doc = held.link?.ownerDocument ?? (typeof document === 'undefined' ? null : document);

    if (typeof doc?.createElement !== 'function' || typeof doc.body?.appendChild !== 'function') {
        return false;
    }

    const anchor = doc.createElement('a');

    anchor.setAttribute('href', held.url);
    anchor.setAttribute('target', held.opens);
    anchor.setAttribute('data-wk-trusted', '');
    anchor.setAttribute('hidden', '');

    for (const name of ['rel', 'referrerpolicy']) {
        const value = held.link.getAttribute(name);

        if (value !== null) {
            anchor.setAttribute(name, value);
        }
    }

    doc.body.appendChild(anchor);
    anchor.click();
    anchor.remove();

    return true;
}

/** A sentence of the catalog with its `:host` placeholder filled. */
function withHost(text, host) {
    return typeof text === 'string' ? text.split(':host').join(host) : host;
}

/** The characters an address breaks before, as print sets an address (`break="address"`). */
const ADDRESS_SEAMS = ['@', '.', '-', '?', '#', '&', '=', '/'];

const isSpaceCharacter = (character) => character !== undefined && /^\s$/u.test(character);

/**
 * Whether an address may break before the character at this position: before a seam that stands
 * between two characters that are no space and not after another seam, and not before the slash
 * after a colon; and after the double slash of a scheme.
 */
function seamBefore(characters, position) {
    const character = characters[position];
    const before = characters[position - 1];

    if (before === undefined || isSpaceCharacter(before)) {
        return false;
    }

    if (ADDRESS_SEAMS.includes(character)) {
        const after = characters[position + 1];

        return after !== undefined && ! isSpaceCharacter(after) && ! ADDRESS_SEAMS.includes(before) && ! (character === '/' && before === ':');
    }

    return before === '/' && characters[position - 2] === '/' && characters[position - 3] === ':' && ! isSpaceCharacter(character);
}

/**
 * Where a run of hyphens that starts at this position ends, with the character after it: a line
 * may break behind a hyphen that no digit follows, and a reader takes a hyphen at the end of a
 * line for a hyphenation. Null for no run, or a run before a space, a digit or the end.
 */
function joinEnd(characters, position) {
    if (characters[position] !== '-' || characters[position - 1] === '-') {
        return null;
    }

    let end = position;

    while (characters[end] === '-') {
        end++;
    }

    const after = characters[end];

    return after === undefined || isSpaceCharacter(after) || /^\p{Nd}$/u.test(after) ? null : end;
}

/**
 * An address as the pieces it is written in: runs of text, `null` for a place it may break, and
 * `{ join }` for a run of hyphens with the character after it, which breaks nowhere. The same seams
 * as `break="address"` sets on the server, for an address that is known only in the browser.
 *
 * @param {string} address
 * @returns {Array<string|null|{join: string}>}
 */
export function addressParts(address) {
    const characters = Array.from(typeof address === 'string' ? address : '');
    const parts = [];
    let text = '';

    const flush = () => {
        if (text !== '') {
            parts.push(text);
            text = '';
        }
    };

    for (let position = 0; position < characters.length; position++) {
        if (seamBefore(characters, position)) {
            flush();
            parts.push(null);
        }

        const end = joinEnd(characters, position);

        if (end !== null) {
            flush();
            parts.push({ join: characters.slice(position, end + 1).join('') });
            position = end;

            continue;
        }

        text += characters[position];
    }

    flush();

    return parts;
}

/** Writes an address into an element as text nodes, `<wbr>` and joins: never as markup. */
function renderAddress(element, address) {
    const doc = element.ownerDocument;

    element.replaceChildren(...addressParts(address).map((part) => {
        if (part === null) {
            return doc.createElement('wbr');
        }

        if (typeof part === 'string') {
            return doc.createTextNode(part);
        }

        const join = doc.createElement('span');
        join.setAttribute('data-wk-join', '');
        join.textContent = part.join;

        return join;
    }));
}

/** The hosts the reader chose not to be warned about again, from the browser's store. */
function storedHosts(key) {
    try {
        const raw = window.localStorage?.getItem(key);
        const list = raw ? JSON.parse(raw) : [];

        return Array.isArray(list) ? list.map(normalizedHost).filter((host) => host !== null) : [];
    } catch {
        return [];
    }
}

/** Keep one more host in the browser's store. A private window may answer without one. */
function storeHost(key, host) {
    try {
        const hosts = storedHosts(key);

        if (! hosts.includes(host)) {
            window.localStorage?.setItem(key, JSON.stringify([...hosts, host]));
        }
    } catch {
        // Nothing to keep it in: the choice holds for this page.
    }
}

/**
 * @param {object} [config]
 * @param {string} [config.name] the name of the alert dialog the question is asked in
 * @param {string[]} [config.trusted] hosts that never ask
 * @param {'browser'|'none'} [config.remember] where a host the reader trusts is kept
 * @param {string} [config.titleText] the title, with `:host` for the host
 * @param {string} [config.forgetText] the checkbox's words, with `:host` for the host
 * @param {string} [config.writtenText] the line for a host the link writes otherwise, with `:host`
 */
export default function wirekitExternalLinkQuestion(config = {}) {
    const remember = config.remember === 'none' ? 'none' : 'browser';
    const storageKey = WK_EXTERNAL_LINKS_STORAGE_KEY;

    return {
        // What the open question shows: the host the browser calls, the address as it calls it,
        // and the host as the link writes it where that differs.
        host: '',
        address: '',
        written: '',
        // The checkbox: not to be warned about this host again.
        forget: false,

        /** The title of the open question: the host, in the page's language. */
        get title() {
            return withHost(config.titleText, this.host);
        },

        /** The checkbox's words for the open question's host. */
        get forgetLabel() {
            return withHost(config.forgetText, this.host);
        },

        /** The line that names the host as the link writes it, where that differs. */
        get writtenLine() {
            return this.written === '' ? '' : withHost(config.writtenText, this.written);
        },

        /**
         * Writes the open question's address into its element, broken at the seams an address is
         * set at in print, as `break="address"` sets them on the server: the address is known only
         * in the browser. The element calls it from `x-effect`, which runs it again whenever the
         * address changes; the dialog is teleported, so the element is passed rather than found.
         */
        paintAddress(element) {
            if (element && typeof element.replaceChildren === 'function') {
                renderAddress(element, this.address);
            }
        },

        _name: typeof config.name === 'string' ? config.name : '',
        _trusted: [],
        // The visit the question holds: the address, the link it came from and the window it
        // goes to.
        _held: null,
        _onClick: null,
        _onAuxClick: null,
        _onTrust: null,

        init() {
            const configured = Array.isArray(config.trusted) ? config.trusted : [];

            this._trusted = [...configured.map(normalizedHost), ...(remember === 'browser' ? storedHosts(storageKey) : [])]
                .filter((host) => host !== null);

            // Both clicks after the link's own listeners, in the bubbling phase. A click with a key
            // that opens another tab or window is a `click` too; the middle button sends `auxclick`.
            this._onClick = (event) => this._consider(event, event.button === 0 && (event.metaKey || event.ctrlKey || event.shiftKey));
            this._onAuxClick = (event) => {
                if (event.button === 1) {
                    this._consider(event, true);
                }
            };
            this._onTrust = (event) => this.trust(event?.detail?.hosts);

            document.addEventListener('click', this._onClick);
            // `auxclick` comes from Safari 18.2 on, above the kit's floor, so it is a progressive
            // enhancement: where the browser does not send it, no listener waits for it and the
            // middle button is left to the browser.
            if ('onauxclick' in document) {
                document.addEventListener('auxclick', this._onAuxClick);
            }
            window.addEventListener(WK_EXTERNAL_LINKS_TRUST_EVENT, this._onTrust);
        },

        destroy() {
            document.removeEventListener('click', this._onClick);
            if ('onauxclick' in document) {
                document.removeEventListener('auxclick', this._onAuxClick);
            }
            window.removeEventListener(WK_EXTERNAL_LINKS_TRUST_EVENT, this._onTrust);
            this._onClick = null;
            this._onAuxClick = null;
            this._onTrust = null;
            this._held = null;
        },

        /**
         * Trust more hosts from now on, as `wirekit:external-links-trust` does.
         *
         * @param {unknown} hosts
         */
        trust(hosts) {
            const list = Array.isArray(hosts) ? hosts : [hosts];

            for (const host of list.map(normalizedHost)) {
                if (host !== null && ! this._trusted.includes(host)) {
                    this._trusted.push(host);
                }
            }
        },

        /**
         * Whether a host never asks: the page's own, or one of the trusted. A host written with a
         * dot at its end is the same host, as the entries are read without one.
         */
        trusts(host) {
            const bare = String(host).replace(/\.$/, '');

            return bare === window.location.hostname || this._trusted.some((entry) => hostMatches(bare, entry));
        },

        /**
         * Hold a click on a link to another site and ask. A click another listener prevented, a
         * link the page marks trusted and one that opens no site go on as they would.
         *
         * @param {MouseEvent} event
         * @param {boolean} newWindow whether the click asked for another tab or window
         */
        _consider(event, newWindow) {
            if (! this._onClick || event.defaultPrevented || (event.button !== 0 && event.button !== 1) || event.altKey) {
                return;
            }

            const link = clickedLink(event.target);

            if (! link || link.hasAttribute('download') || link.closest('[data-wk-trusted]')) {
                return;
            }

            const base = typeof document.baseURI === 'string' ? document.baseURI : window.location.href;
            const url = siteAddressOf(link, base);

            if (! url || this.trusts(url.hostname)) {
                return;
            }

            event.preventDefault();

            // The window the click would have opened: a new tab where it asked for one, else the
            // window its target names, read as the browser reads it, `<base target>` included.
            this._held = { url: url.href, link, opens: newWindow ? '_blank' : windowTheLinkOpens(link) };
            this.host = url.hostname;
            this.address = url.href;
            this.written = writtenHostOf(link, url) ?? '';
            this.forget = false;

            // The link takes the focus before the dialog opens, so the dialog gives it back there
            // however it closes, Stay, Escape or the backdrop: WebKit gives a pressed link no focus,
            // and the dialog would otherwise hand it to the page.
            link.focus?.({ preventScroll: true });

            this.$dispatch?.('wirekit-alert-dialog-show', { name: this._name });
        },

        /** Visit: remember the host if asked to, then go where the click would have gone. */
        visit() {
            const held = this._held;
            this._held = null;

            if (! held) {
                return;
            }

            if (this.forget) {
                this.trust(this.host);

                if (remember === 'browser') {
                    storeHost(storageKey, this.host);
                }

                window.dispatchEvent?.(new CustomEvent(WK_EXTERNAL_LINK_TRUSTED_EVENT, { detail: { host: this.host } }));
            }

            this.$dispatch?.('wirekit-alert-dialog-close', { name: this._name });

            if (held.opens === '_blank') {
                window.open(held.url, '_blank', 'noopener,noreferrer');

                return;
            }

            // A navigation by script sends the referrer the page's policy allows, so a link that
            // decides its own goes through an anchor that carries its `rel` and `referrerpolicy`.
            if (decidesItsReferrer(held.link) && followThroughAnAnchor(held)) {
                return;
            }

            if (held.opens === '_self') {
                window.location.assign(held.url);
            } else {
                // `_top` or `_parent` in a frame: the window above goes there, as the click would
                // have taken it.
                window.open(held.url, held.opens);
            }
        },

        /** Stay: the visit is let go, and the dialog closes onto the link. */
        stay() {
            this._held = null;
            this.$dispatch?.('wirekit-alert-dialog-close', { name: this._name });
        },
    };
}
