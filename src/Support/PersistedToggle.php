<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * Builds the Alpine `x-data` object for a persisted open/collapsed disclosure.
 *
 * ONE implementation of the localStorage-backed toggle, shared by the sidebar
 * sub-components (`sidebar.group`, `sidebar.collapsible`) instead of copying the
 * `init()`/`toggle()` mechanic inline into each. Internal helper (not part of the
 * developer-facing `WireKit` facade surface) — called from Blade the same way
 * {@see BooleanProp} is.
 *
 * @deprecated Nothing in the package calls this any more, and new code must not.
 *
 * What it emits is a JavaScript object literal that declares METHODS, and method
 * shorthand is not in the grammar Alpine's CSP build parses. A component built
 * this way is inert under a Content-Security-Policy without `'unsafe-eval'` — the
 * disclosure renders, the chevron sits there, and the click does nothing, with
 * nothing logged.
 *
 * It was one implementation, which was the right instinct; it was just an
 * implementation living in a string. The same mechanic is now
 * `resources/js/utils/persisted-flag.js`, used by the `wirekitSidebarRail` and
 * `wirekitSidebarDisclosure` factories — readable, testable, and parseable by
 * both Alpine builds.
 *
 * The class stays for now because removing a shipped class is a
 * backward-compatibility decision rather than a cleanup; `PersistedToggleTest`
 * keeps holding its behavior in the meantime, and a guard there refuses any new
 * caller in the view layer.
 */
final class PersistedToggle
{
    /**
     * @param  string  $property  The boolean state field the markup binds against
     *                            (`'open'` for a disclosure, `'collapsed'` for a rail).
     * @param  bool  $initial  The seed value when nothing is stored.
     * @param  string|null  $key  localStorage key; null keeps the state ephemeral.
     *
     * The returned string is meant to be echoed through Blade `{{ }}` into an
     * `x-data="…"` attribute: the double quotes `json_encode` produces around the
     * key are HTML-escaped by Blade and decoded back by the browser, so Alpine
     * receives valid JS — and a developer-supplied key is escaped by BOTH
     * json_encode and Blade, closing the attribute-injection path. When `$key` is
     * null the state is ephemeral; otherwise it is seeded from and written to
     * `localStorage[$key]` (`'1'`/`'0'`), guarded by try/catch so a disabled or
     * throwing storage never breaks the component.
     */
    public static function data(string $property, bool $initial, ?string $key): string
    {
        $init = $initial ? 'true' : 'false';
        $keyJs = json_encode($key, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return "{ {$property}: {$init}, _key: {$keyJs}, "
            ."init() { if (this._key) { try { const s = localStorage.getItem(this._key); if (s !== null) this.{$property} = s === '1'; } catch (e) {} } }, "
            ."toggle() { this.{$property} = ! this.{$property}; if (this._key) { try { localStorage.setItem(this._key, this.{$property} ? '1' : '0'); } catch (e) {} } } }";
    }
}
