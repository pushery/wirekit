<?php

declare(strict_types=1);

namespace Pushery\WireKit\Sandbox;

/**
 * Immutable outcome of a sandbox render attempt.
 *
 *   - `success($html, $schema)`: rendering completed; `$html` carries the
 *     Blade-rendered output, `$schema` carries the per-prop schema so
 *     the iframe page can render the prop-editor UI alongside.
 *   - `rejected($violations)`: validation or schema-lookup failed;
 *     `$violations` carries the list of human-readable reasons.
 *
 * Caller maps to HTTP status: 200 for success, 422 for rejection.
 *
 * Public-readable property contract — the wirekit-docs `SandboxController`
 * reads `ok`, `violations`, `html`, `schema` off the object via
 * `get_object_vars()`. Any rename here breaks the docs-app wrapper.
 */
final class RenderResult
{
    /**
     * @param  array<int, string>  $violations
     * @param  array<string, array{type: string, required?: bool, default?: mixed, allowed_values?: array<int, mixed>}>|null  $schema
     */
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $html,
        public readonly array $violations,
        public readonly ?array $schema = null,
    ) {}

    /**
     * @param  array<string, array{type: string, required?: bool, default?: mixed, allowed_values?: array<int, mixed>}>|null  $schema
     */
    public static function success(string $html, ?array $schema = null): self
    {
        return new self(true, $html, [], $schema);
    }

    /**
     * @param  array<int, string>  $violations
     */
    public static function rejected(array $violations): self
    {
        return new self(false, null, $violations);
    }
}
