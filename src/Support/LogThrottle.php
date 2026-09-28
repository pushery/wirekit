<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Lets a diagnostic log line through once per key and time window, across requests.
 *
 * Static state lasts as long as the PHP script that holds it. FPM starts the script afresh for
 * every HTTP request, so a static "already logged" flag keeps a line from repeating within one
 * request and no further: a misconfiguration that persists would write one line per request.
 * The application cache remembers the key across requests and workers for the window given.
 *
 * With no cache bound, or a store that throws, the answer is yes, so the line is logged as it
 * would be without this class. A diagnostic must neither go silent nor break the render because
 * the cache failed. A store that forgets between requests, the `array` store, throttles within
 * a request only.
 */
final class LogThrottle
{
    /** How long a key stays remembered, in seconds. */
    public const WINDOW = 3600;

    /** Namespaces the keys, so an application's own cache entries cannot collide with them. */
    private const PREFIX = 'wirekit:log-throttle:';

    /** Whether the line keyed by $key may be logged now: true once per key and window. */
    public static function firstAcrossRequests(string $key, int $seconds = self::WINDOW): bool
    {
        if (! function_exists('app') || ! app()->bound('cache')) {
            return true;
        }

        try {
            return Cache::add(self::PREFIX.$key, true, $seconds);
        } catch (Throwable) {
            return true;
        }
    }
}
