<?php

declare(strict_types=1);

namespace Pushery\WireKit\Sandbox;

/**
 * Immutable result of a sandbox-prop validation.
 *
 * Carries either the sanitized payload (when valid) or the list of
 * violations (when invalid). The `ok()` predicate is the canonical
 * way to ask "should I render this?".
 */
final class ValidationResult
{
    /**
     * @param  array<string, mixed>  $clean
     * @param  array<int, string>  $violations
     */
    public function __construct(
        public readonly array $clean,
        public readonly array $violations,
    ) {}

    public function ok(): bool
    {
        return empty($this->violations);
    }
}
