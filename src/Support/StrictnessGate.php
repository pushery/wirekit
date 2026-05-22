<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use InvalidArgumentException;

/**
 * Strictness gate for runtime prop / value validation.
 *
 * Used by WireKit::validateProp() (component-level prop validation) and
 * IconResolver (icon-alias / preset lookups) to decide between two
 * behaviours when an invalid value is supplied:
 *
 *   - STRICT: throw InvalidArgumentException with a Did-you-mean hint.
 *   - LENIENT: log a warning (with fallback annotation) and return the
 *     first allowed value (or a caller-supplied fallback).
 *
 * Strictness is decided by:
 *   1. Explicit override via `wirekit.validation.strict` config
 *      (env `WIREKIT_STRICT_VALIDATION`) — true/false.
 *   2. Default: APP_DEBUG=true → strict, APP_DEBUG=false → lenient.
 *
 * The explicit-override path lets developers FORCE strict in prod
 * (CI / staging hardening) or FORCE lenient in debug (CI snapshots
 * that want to assert rendered output regardless of prop typos).
 */
final class StrictnessGate
{
    /**
     * Whether the gate currently runs in strict mode.
     */
    public static function isStrict(): bool
    {
        $configured = config('wirekit.validation.strict');
        if ($configured !== null) {
            return (bool) $configured;
        }

        return (bool) config('app.debug');
    }

    /**
     * Validate a value against an allowed list. Throws (strict) or
     * logs+returns the fallback (lenient).
     *
     * @param  list<string>  $allowed
     * @param  string|null  $fallback  Lenient-mode fallback override; defaults to $allowed[0].
     */
    public static function enforce(
        string $context,
        string $key,
        string $value,
        array $allowed,
        ?string $fallback = null,
    ): string {
        if (in_array($value, $allowed, true)) {
            return $value;
        }

        $message = self::formatMessage($context, $key, $value, $allowed);

        if (self::isStrict()) {
            throw new InvalidArgumentException($message);
        }

        $effectiveFallback = $fallback ?? ($allowed[0] ?? '');
        // Append the actionable fallback so prod log readers know
        // WHAT the component rendered instead of the requested value.
        logger()->warning(
            $message.' Falling back to "'.$effectiveFallback.'".'
        );

        return $effectiveFallback;
    }

    /**
     * Build the canonical "Invalid X" message with Did-you-mean hint.
     * Exposed so callers that throw their own exception (e.g.
     * IconResolver) can reuse the exact wording without re-implementing
     * the Levenshtein-suggestion contract.
     *
     * @param  list<string>  $allowed
     */
    public static function formatMessage(
        string $context,
        string $key,
        string $value,
        array $allowed,
    ): string {
        $list = implode(', ', $allowed);
        $message = "WireKit [{$context}]: Invalid {$key} \"{$value}\". Allowed: {$list}.";

        $hint = SuggestSimilar::format(SuggestSimilar::byLevenshtein($value, $allowed));
        if ($hint !== null) {
            $message .= ' '.$hint;
        }

        return $message;
    }
}
