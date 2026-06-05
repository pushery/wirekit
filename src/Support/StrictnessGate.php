<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use InvalidArgumentException;

/**
 * Strictness gate for runtime prop / value validation.
 *
 * Used by WireKit::validateProp() (component-level prop validation) and
 * IconResolver (icon-alias / preset lookups) to decide between two
 * behaviors when an invalid value is supplied:
 *
 *   - STRICT  → throw InvalidArgumentException with a Did-you-mean hint.
 *   - LENIENT → log a warning (with fallback annotation) and return the
 *     first allowed value (or a caller-supplied fallback).
 *
 * Strictness is decided by:
 *   1. Explicit override via `wirekit.validation.strict` config
 *      (env `WIREKIT_STRICT_VALIDATION`) — true/false.
 *   2. Default: APP_DEBUG=true → strict, APP_DEBUG=false → lenient.
 *
 * Throw-on-invalid is a SECOND decision: even in strict mode, the gate
 * only throws when (a) running in console / artisan / Pest, OR (b) the
 * `wirekit.validation.throw_on_invalid` config is explicitly true. In
 * HTTP dev requests (strict + browser), the gate logs at ERROR level
 * and renders the fallback so a single prop typo doesn't 500 the whole
 * blade view. the old
 * always-throw-in-debug behavior took down the entire page on a typo
 * that was purely cosmetic.
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
     * Validate a value against an allowed list. Throws (strict + CLI/throw-
     * override) or logs+returns the fallback (lenient OR strict-HTTP).
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
        $effectiveFallback = $fallback ?? ($allowed[0] ?? '');

        if (self::isStrict()) {
            // Two strict-mode paths:
            //   - CLI / test / explicit throw-on-invalid → throw (fail-fast)
            //   - HTTP dev request → log at ERROR + render fallback
            // The HTTP-dev fall-through a
            // typo in one prop shouldn't 500 the whole blade view.
            $shouldThrow = app()->runningInConsole()
                || (bool) config('wirekit.validation.throw_on_invalid', false);

            if ($shouldThrow) {
                throw new InvalidArgumentException($message);
            }

            logger()->error($message.' Falling back to "'.$effectiveFallback.'".');

            return $effectiveFallback;
        }

        // Lenient (prod) — log at warning level and render the fallback.
        logger()->warning(
            $message.' Falling back to "'.$effectiveFallback.'".'
        );

        return $effectiveFallback;
    }

    /**
     * warn on unknown prop KEYS in dev.
     *
     * Pre-fix, Wirekit's prop validation only checked VALUES of declared
     * props — unknown keys (e.g. `<x-wirekit::button variant="ghost">`
     * when the prop is `surface`) were passed through to the attribute
     * bag silently and the component rendered with default `surface=
     * "filled"`. The dev got no signal that their intended `ghost`
     * treatment didn't apply.
     *
     * This helper compares an actual attribute-bag's keys against the
     * declared `@props` keys + a per-component allowlist of legitimate
     * passthrough attribute prefixes (`aria-`, `data-`, `wire:`, `x-`,
     * `@`, plus reserved Blade attrs `style`, `class`, `id`, `name`,
     * `slot`).
     *
     * Unknown keys → log at warning level with a Levenshtein-ranked
     * Did-you-mean hint pointing at the closest declared prop. NEVER
     * throws — silent in prod, visible in dev logs.
     *
     * @param  string  $context  Component name (e.g. `button`, `alert`).
     * @param  array<string, mixed>  $actual  The attribute bag (`$attributes->getAttributes()`).
     * @param  list<string>  $declared  The list of declared `@props` keys.
     */
    public static function warnUnknownProps(string $context, array $actual, array $declared): void
    {
        // Skip the check outside dev — no value in noisy prod logs over
        // attribute passthroughs the framework supports by design.
        if (! (bool) config('app.debug') && ! app()->runningInConsole()) {
            return;
        }

        // Allowed passthrough prefixes: Blade-framework reserved, ARIA,
        // data-, Livewire wire:, Alpine x-/@/:, and the bare reserved
        // HTML attributes Blade always strips into the bag.
        $reserved = ['style', 'class', 'id', 'name', 'slot', 'rel', 'role', 'target',
            'tabindex', 'title', 'autofocus', 'autocomplete', 'disabled',
            'readonly', 'required', 'placeholder', 'value', 'checked',
            'selected', 'multiple', 'min', 'max', 'step', 'pattern',
            'href', 'type', 'for', 'form', 'method', 'action',
            'src', 'srcset', 'alt', 'loading', 'decoding', 'width', 'height',
            'colspan', 'rowspan', 'scope', 'headers', 'datetime',
            'open', 'hidden', 'inert', 'contenteditable', 'spellcheck',
            'draggable', 'translate'];
        $prefixes = ['aria-', 'data-', 'wire:', 'x-', '@', ':', 'v-'];

        foreach (array_keys($actual) as $key) {
            if (! is_string($key) || $key === '') {
                continue;
            }
            if (in_array($key, $declared, true) || in_array($key, $reserved, true)) {
                continue;
            }
            foreach ($prefixes as $p) {
                if (str_starts_with($key, $p)) {
                    continue 2;
                }
            }

            // Levenshtein-rank against declared props for a Did-you-mean.
            $hint = SuggestSimilar::format(SuggestSimilar::byLevenshtein($key, $declared));
            $message = "WireKit [{$context}]: Unknown prop \"{$key}\". Declared: ".implode(', ', $declared).'.';
            if ($hint !== null) {
                $message .= ' '.$hint;
            }

            logger()->warning($message);
        }
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
