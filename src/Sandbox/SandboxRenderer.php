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
 * string defense-in-depth, so even a slot using `{!! !!}` cannot
 * surface raw payload content.
 *
 * SSTI defense: developer values are bound as runtime DATA and referenced
 * from the assembled template through Blade expressions — they are NEVER
 * concatenated into the template source `Blade::render()` compiles. HTML
 * escaping alone does not neutralize Blade's compile tokens (`{{ }}`, `@…`),
 * so concatenating a value into the source would let `{{ 7*7 }}` execute.
 * See `doRender()` for the binding mechanism.
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
        // Build: <x-wirekit::{component} :prop="$__wk_pN" …>{!! $__wk_body !!}</…>
        //
        // SECURITY (SSTI/RCE): every developer-controlled value is bound as
        // runtime DATA (the `$data` map below) and referenced from the template
        // through a Blade expression — NEVER concatenated into the template
        // source. This is the load-bearing defense: `PropsValidator::sanitize`
        // HTML-escapes `<>&"'` but does NOT neutralize Blade's own compile
        // tokens (`{{ … }}`, `{!! … !!}`, `@directive`). Previously the values
        // were string-concatenated straight into the Blade source, so a prop
        // value of `{{ 7*7 }}` (or `{{ system(chr(105).chr(100)) }}` for a
        // no-quote RCE) reached the Blade compiler intact and executed. Binding
        // the values as data instead means their content is echoed literally at
        // render time and never re-parsed as Blade — there is no token blacklist
        // to bypass.
        $tag = 'x-wirekit::'.$component;
        $body = '';
        $attrs = '';
        $data = [];
        $i = 0;

        foreach ($props as $key => $value) {
            if ($key === 'body') {
                // Convention: 'body' prop becomes the slot content.
                $body = is_string($value) ? $value : '';

                continue;
            }
            if (is_bool($value)) {
                // Boolean attribute: bare name (no value) when true. `$key` is a
                // schema-defined prop name — PropsValidator rejects any key not
                // in the schema — so it is always a safe identifier, never
                // developer free-text.
                if ($value) {
                    $attrs .= ' '.$key;
                }

                continue;
            }
            if (is_int($value) || is_float($value) || is_string($value)) {
                // Bind the value to a generated variable and reference it as a
                // bound attribute. The value travels as data, so its content is
                // never compiled as template source.
                $var = '__wk_p'.$i++;
                $data[$var] = $value;
                $attrs .= ' :'.$key.'="$'.$var.'"';

                continue;
            }
            // Skip non-scalar (validator already pruned arrays into clean structure;
            // anything else is intentionally dropped).
        }

        // Body slot: a RAW echo of the body data variable. Its content is
        // already HTML-escaped by the validator (so it can only emit inert
        // entities, never live markup), and a raw echo of a DATA variable is
        // never re-parsed as Blade — so a `{{ … }}` / `@…` in the body stays
        // literal. This reproduces the previous single-escaped slot-text output.
        $data['__wk_body'] = $body;
        $bodyExpr = $body === '' ? '' : '{!! $__wk_body !!}';

        // Apply per-component body-slot wrap if registered.
        // See BODY_WRAPPERS docblock for rationale.
        if ($bodyExpr !== '' && isset(self::BODY_WRAPPERS[$component])) {
            $wrapTag = 'x-wirekit::'.self::BODY_WRAPPERS[$component];
            $bodyExpr = '<'.$wrapTag.'>'.$bodyExpr.'</'.$wrapTag.'>';
        }

        $blade = $bodyExpr === ''
            ? '<'.$tag.$attrs.' />'
            : '<'.$tag.$attrs.'>'.$bodyExpr.'</'.$tag.'>';

        if (! function_exists('app') || ! app()->bound('view')) {
            // Test environment without a Laravel container — return the raw Blade
            // string so callers can inspect what would render. Prevents the
            // sandbox primitives from being unusable outside Laravel.
            return $blade;
        }

        return (string) Blade::render($blade, $data);
    }
}
