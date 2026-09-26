<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * The server's half of `persist-driver="cookie"`: the flag the browser stored, read while the
 * page renders, so the first paint already shows the reader's choice.
 *
 * One read for every component that offers the driver (`sidebar`, `app-rail` and
 * `sidebar.collapse-toggle`). They must agree about a stored value, and each carrying its own copy
 * of this order is how one of them would quietly stop agreeing.
 *
 * The REQUEST is asked first and the superglobal only as a fallback, and that order is the fix
 * rather than a detail. PHP fills `$_COOKIE` once per PROCESS, not once per request. Under
 * `fpm-fcgi` those are the same thing, a process serves one request and dies, which is why
 * reading it alone looks correct for as long as it does. Every server that keeps a worker alive
 * across requests (Octane, FrankenPHP, RoadRunner) fills it at boot, when there is no request,
 * and never again. Measured on one page with one cookie under two SAPIs: present under FPM,
 * empty under a long-lived CLI SAPI, where a rail then rendered collapsed and the client widened
 * it a frame later, 187px of column movement, 0.1097 CLS against a budget of 0.1. Nothing
 * throws; the other state simply renders.
 *
 * The superglobal stays as a fallback because dropping it would be a regression, not a cleanup.
 * The cookie is written by JavaScript, so it arrives as plaintext, and Laravel's `EncryptCookies`
 * nulls a plaintext cookie it cannot decrypt unless the name is excepted. An application on FPM
 * that never added that exception is served by `$_COOKIE` and must keep working. The fallback can
 * fail to answer but cannot answer wrongly: on a long-lived server nothing writes `$_COOKIE` per
 * request, so it is empty rather than another visitor's value.
 */
final class PersistedCookie
{
    /**
     * The stored flag, or null when there is none. Only '1' reads as true, so no arbitrary value
     * reaches the page: the result is a boolean and nothing else.
     */
    public static function flag(string $key): ?bool
    {
        $stored = request()->cookie($key);

        if (! is_string($stored)) {
            $stored = isset($_COOKIE[$key]) && is_string($_COOKIE[$key]) ? $_COOKIE[$key] : null;
        }

        return $stored === null ? null : $stored === '1';
    }
}
