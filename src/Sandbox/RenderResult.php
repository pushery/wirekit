<?php

declare(strict_types=1);

namespace Pushery\WireKit\Sandbox;

/**
 * Immutable outcome of a sandbox render attempt.
 *
 *   - `success($html, $schema, $source)`: rendering completed; `$html` carries
 *     the Blade-rendered output, `$schema` the per-prop schema so the iframe page
 *     can render the prop-editor UI alongside, and `$source` the WireKit markup
 *     that produced this render.
 *
 * WHY `$source` EXISTS. A sandbox preview is interactive: the developer changes
 * props and the output re-renders, so the code to show is not the static example
 * in the documentation page — it is whatever the current props amount to. Until
 * this field the renderer built that markup internally and then threw it away,
 * returning only HTML. The docs site had nothing to put in its code panel and
 * showed the rendered markup instead, so a developer looking to copy a WireKit
 * snippet got a wall of compiled HTML. Nothing downstream could have fixed that:
 * the source only exists here.
 *   - `rejected($violations)`: validation or schema-lookup failed;
 *     `$violations` carries the list of human-readable reasons.
 *
 * Caller maps to HTTP status: 200 for success, 422 for rejection.
 *
 * Public-readable property contract — downstream developers (e.g. the docs
 * site's `SandboxController`) read `ok`, `violations`, `html`, `schema` off
 * the object via `get_object_vars()`. Any rename here is a breaking change
 * for those developers.
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
        public readonly ?string $source = null,
    ) {}

    /**
     * @param  array<string, array{type: string, required?: bool, default?: mixed, allowed_values?: array<int, mixed>}>|null  $schema
     */
    public static function success(string $html, ?array $schema = null, ?string $source = null): self
    {
        return new self(true, $html, [], $schema, $source);
    }

    /**
     * @param  array<int, string>  $violations
     */
    public static function rejected(array $violations): self
    {
        return new self(false, null, $violations);
    }
}
