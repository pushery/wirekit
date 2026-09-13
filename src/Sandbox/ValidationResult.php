<?php

declare(strict_types=1);

namespace Pushery\WireKit\Sandbox;

/**
 * Immutable result of a sandbox-prop validation.
 *
 * Carries either the validated payload (when valid) or the list of
 * violations (when invalid). The `ok()` predicate is the canonical
 * way to ask "should I render this?".
 *
 * The payload comes in two views, and which one to use depends on where
 * the value is going:
 *
 *   - `clean` — every string HTML-escaped. For a value that is echoed RAW,
 *     like the slot the renderer builds: the one escape it gets here is
 *     the one it needs.
 *   - `values` — the same validated, coerced values exactly as given. For a
 *     value passed to a component as a prop, which the component escapes
 *     itself when it echoes it. Passing such a value `clean` escapes it
 *     twice: a title shows `&amp;` as text, and a link's query string
 *     gains a parameter called `amp;b`.
 */
final class ValidationResult
{
    /**
     * @param  array<string, mixed>  $clean
     * @param  array<int, string>  $violations
     * @param  array<string, mixed>  $values
     */
    public function __construct(
        public readonly array $clean,
        public readonly array $violations,
        public readonly array $values = [],
    ) {}

    public function ok(): bool
    {
        return empty($this->violations);
    }
}
