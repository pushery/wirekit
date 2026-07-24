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
