<?php

declare(strict_types=1);

namespace Pushery\WireKit\Sandbox;

use Illuminate\Support\Facades\Blade;

/**
 * render a sandboxed component invocation.
 *
 * Pipeline:
 *   1. ComponentAllowlist::allows($name) — reject unknown / unschema'd
 *      components with a 422-shaped result.
 *   2. PropsValidator::validate(schema, payload) — strip non-allowlisted
 *      props, type-check, sanitize string/array values.
 *   3. Render via Blade with the sanitized payload as data.
 *   4. Audit-log the outcome.
 *
 * The renderer NEVER receives raw user input directly — every value
 * funnels through the validator. The validator HTML-escapes every
 * string defence-in-depth, so even a slot using `{!! !!}` cannot
 * surface raw payload content.
 *
 * Returned `RenderResult` carries either `html` (success) or
 * `violations` (validation failure). Never throws — the caller
 * decides between 200 and 422.
 */
final class SandboxRenderer
{
    /**
     * Per-component body-slot wrappers.
     *
     * Some primitives compose via mandatory sub-components for proper
     * structure (e.g. Card needs `<x-wirekit::card.body>` to give the
     * slot content its padding — without it, "Card body" text presses
     * flush against the rounded card edges and the render reads as a
     * bare pill, not a card). The sandbox's single-slot model can't
     * surface multi-slot composition, so this map auto-wraps the
     * `body` payload in the listed sub-component before rendering.
     *
     * Entries here MUST refer to a real `<x-wirekit::{tag}>` sub-component;
     * the wrap is unconditional once the body is non-empty.
     */
    private const BODY_WRAPPERS = [
        'card' => 'card.body',
    ];

    public static function render(string $component, array $props, string $ipAddress = '0.0.0.0'): RenderResult
    {
        if (! ComponentAllowlist::allows($component)) {
            SandboxAuditLog::record('rejected:component', $component, $ipAddress, 1);

            return RenderResult::rejected(["component '{$component}' not in sandbox allowlist"]);
        }

        $schema = SandboxSchemaRegistry::get($component);
        if ($schema === null) {
            SandboxAuditLog::record('rejected:no-schema', $component, $ipAddress, 1);

            return RenderResult::rejected(["component '{$component}' has no sandbox schema"]);
        }

        $result = PropsValidator::validate($schema, $props);
        if (! $result->ok()) {
            SandboxAuditLog::record('rejected:props', $component, $ipAddress, count($result->violations));

            return RenderResult::rejected($result->violations);
        }

        try {
            $html = self::doRender($component, $result->clean);
        } catch (\Throwable $e) {
            SandboxAuditLog::record('error:render', $component, $ipAddress, 1);

            return RenderResult::rejected(['render failed: '.$e->getMessage()]);
        }

        SandboxAuditLog::record('rendered', $component, $ipAddress, 0);

        // Echo the schema back alongside the HTML so the iframe page can
        // render a prop-editor UI without a second round-trip to the
        // schema registry. Public-readable property contract: see
        // `RenderResult` docblock.
        return RenderResult::success($html, $schema);
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private static function doRender(string $component, array $props): string
    {
        // Build the Blade source: <x-wirekit::{component} :prop1="..." :prop2="...">{$body}</x-wirekit::{component}>
        $tag = 'x-wirekit::'.$component;
        $body = '';
        $attrs = '';

        foreach ($props as $key => $value) {
            if ($key === 'body') {
                // Convention: 'body' prop becomes the slot content.
                $body = is_string($value) ? $value : '';

                continue;
            }
            if (is_bool($value)) {
                if ($value) {
                    $attrs .= ' '.$key;
                }

                continue;
            }
            if (is_int($value) || is_float($value)) {
                $attrs .= ' '.$key.'="'.htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8').'"';

                continue;
            }
            if (is_string($value)) {
                // Validator already HTML-escaped; pass through into the attribute
                $attrs .= ' '.$key.'="'.$value.'"';

                continue;
            }
            // Skip non-scalar (validator already pruned arrays into clean structure;
            // anything else is intentionally dropped).
        }

        // Apply per-component body-slot wrap if registered.
        // See BODY_WRAPPERS docblock for rationale.
        if ($body !== '' && isset(self::BODY_WRAPPERS[$component])) {
            $wrapTag = 'x-wirekit::'.self::BODY_WRAPPERS[$component];
            $body = '<'.$wrapTag.'>'.$body.'</'.$wrapTag.'>';
        }

        $blade = $body === ''
            ? '<'.$tag.$attrs.' />'
            : '<'.$tag.$attrs.'>'.$body.'</'.$tag.'>';

        if (! function_exists('app') || ! app()->bound('view')) {
            // Test environment without a Laravel container — return the raw Blade
            // string so callers can inspect what would render. Prevents the
            // sandbox primitives from being unusable outside Laravel.
            return $blade;
        }

        return (string) Blade::render($blade);
    }
}
