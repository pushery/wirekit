<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * A value from a prop, made fit to stand inside a line of the application log.
 *
 * A prop is often bound to data the application did not write itself (`:intent="$ticket->priority"`,
 * `name="{{ $user->icon }}"`), and Laravel's log formatter keeps line breaks inside a message. A
 * value with a line break in it therefore starts a line of its own, one that reads like any other
 * entry. Every control character becomes U+FFFD here, the way the sandbox audit log treats its
 * fields, and a value longer than {@see self::MAX_LENGTH} characters is cut and marked, so a log
 * line names the value without carrying all of it.
 */
final class LogValue
{
    /** The most characters of a value a log line quotes. */
    public const MAX_LENGTH = 64;

    public static function quote(string $value): string
    {
        // `\pC` is every Unicode "Other": control, format, surrogate and unassigned. The `/u` flag
        // walks code points rather than bytes, and it returns null on malformed UTF-8, where the
        // byte-level class still keeps a line break out.
        $clean = preg_replace('/\pC/u', "\u{FFFD}", $value)
            ?? preg_replace('/[\x00-\x1F\x7F]/', "\u{FFFD}", $value)
            ?? '';

        if (mb_strlen($clean) <= self::MAX_LENGTH) {
            return $clean;
        }

        return mb_substr($clean, 0, self::MAX_LENGTH).'…';
    }
}
