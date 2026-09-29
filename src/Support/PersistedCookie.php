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
 * rather than a detail. PHP fills `$_COOKIE` when it starts a script. Under `fpm-fcgi` it starts
 * the script afresh for every HTTP request, even though one worker process serves many of them,
 * which is why reading the superglobal alone looks correct for as long as it does. A worker that
 * runs one script across many requests and receives them through its own client (Octane on
 * Swoole or RoadRunner) has it filled once, at boot, when there is no request, and nothing writes
 * it again; FrankenPHP's worker mode is the exception and refills it for each request it hands
 * over. There a remembered rail would render in its other state and the client would correct it
 * a frame later, moving the column. Nothing throws; the other state simply renders.
 *
 * The superglobal stays as a fallback because dropping it would be a regression, not a cleanup.
 * The cookie is written by JavaScript, so it arrives as plaintext, and Laravel's `EncryptCookies`
 * nulls a plaintext cookie it cannot decrypt unless the name is excepted. An application on FPM
 * that never added that exception is served by `$_COOKIE` and must keep working. The fallback can
 * fail to answer but cannot answer wrongly: a long-lived worker either leaves `$_COOKIE` empty or
 * refills it for the request in hand, so it never holds another visitor's value.
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
