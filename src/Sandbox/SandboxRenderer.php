<?php

declare(strict_types=1);

namespace Pushery\WireKit\Sandbox;

use Illuminate\Support\Facades\Blade;

/**
 * Renders a sandboxed component invocation.
 *
 * Pipeline:
 *   1. ComponentAllowlist::allows($name) — reject unknown / unschema'd
 *      components with a 422-shaped result.
 *   2. PropsValidator::validate(schema, payload) — strip non-allowlisted
 *      props, type-check, sanitize string/array values.
 *   3. Render via Blade with the validated payload as data.
 *   4. Audit-log the outcome.
 *
 * The renderer NEVER receives raw user input directly — every value
 * funnels through the validator first, which hands back two views of the
 * same payload. The slot is echoed raw, so it takes the escaped view. A
 * prop takes the value as given, because the component escapes it on
 * output the way it does in any application, and a second escape reaches
 * the page as text: a title reading `&amp;`, a query string with a
 * parameter called `amp;b`. That no component in the sandbox echoes a
 * string prop raw is asserted over the whole schema registry by
 * `SandboxRendersWhatAnApplicationRendersTest`, not assumed.
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

    /** @param  array<string, mixed>  $props */
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

        // Declared before the by-ref call. PHP would create it implicitly, but a variable
        // that first appears as an argument is a variable the next reader has to trace.
        $source = '';

        try {
            $html = self::doRender($component, $result->values, $result->clean, $source);
        } catch (\Throwable $e) {
            SandboxAuditLog::record('error:render', $component, $ipAddress, 1);

            return RenderResult::rejected(['render failed: '.$e->getMessage()]);
        }

        SandboxAuditLog::record('rendered', $component, $ipAddress, 0);

        // Echo the schema back alongside the HTML so the iframe page can
        // render a prop-editor UI without a second round-trip to the
        // schema registry. Public-readable property contract: see
        // `RenderResult` docblock.
        return RenderResult::success($html, $schema, $source);
    }

    /**
     * @param  array<string, mixed>  $props  the validated values as given — what a bound prop receives
     * @param  array<string, mixed>  $escaped  the same values HTML-escaped — what the raw slot echo receives
     */
    // `$source` is written on every path, never left null — the nullable by-ref type made
    // callers guard against a state this method does not produce.
    private static function doRender(string $component, array $props, array $escaped, string &$source = ''): string
    {
        // Build: <x-wirekit::{component} :prop="$__wk_pN" …>{!! $__wk_body !!}</…>
        //
        // SECURITY (SSTI/RCE): every developer-controlled value is bound as
        // runtime DATA (the `$data` map below) and referenced from the template
        // through a Blade expression — NEVER concatenated into the template
        // source. This is the load-bearing defense: HTML escaping does NOT
        // neutralize Blade's own compile tokens (`{{ … }}`, `{!! … !!}`,
        // `@directive`). Previously the values were string-concatenated
        // straight into the Blade source, so a prop value of `{{ 7*7 }}` (or
        // `{{ system(chr(105).chr(100)) }}` for a no-quote RCE) reached the
        // Blade compiler intact and executed. Binding the values as data
        // instead means their content is echoed literally at render time and
        // never re-parsed as Blade — there is no token blacklist to bypass.
        $tag = 'x-wirekit::'.$component;
        $body = '';
        $attrs = '';
        $data = [];
        $i = 0;
        $sourceAttrs = '';

        foreach ($props as $key => $value) {
            if ($key === 'body') {
                // Convention: 'body' prop becomes the slot content. The slot is echoed raw
                // below, so it takes the ESCAPED view — never the value a bound prop receives.
                $body = is_string($escaped['body'] ?? null) ? $escaped['body'] : '';

                continue;
            }
            if (is_bool($value)) {
                // Boolean attribute: bare name (no value) when true. `$key` is a
                // schema-defined prop name — PropsValidator rejects any key not
                // in the schema — so it is always a safe identifier, never
                // developer free-text.
                if ($value) {
                    $attrs .= ' '.$key;
                    $sourceAttrs .= ' '.$key;
                }

                continue;
            }
            if (is_int($value) || is_float($value) || is_string($value)) {
                // Bind the value to a generated variable and reference it as a
                // bound attribute. The value travels as data, so its content is
                // never compiled as template source.
                //
                // It is bound as given, not escaped. The component escapes a prop
                // when it echoes it, exactly as it does in an application, so an
                // escape here is a second one, and a second escape reaches the page
                // as text.
                $var = '__wk_p'.$i++;
                $data[$var] = $value;
                $attrs .= ' :'.$key.'="$'.$var.'"';
                // The same attribute with the literal value, for the snippet the
                // caller shows. The bound form ABOVE is how the value reaches
                // Blade without being compiled as template source; it is not
                // something anyone can paste into an application.
                //
                // A number is bound here too, but for a different reason: the
                // schema declares it as an int, and `level="4"` passes the string
                // "4". Both render identically across the catalog today, since
                // nothing compares a numeric prop strictly — so this is about the
                // snippet teaching the right shape rather than about a bug. The
                // first `=== 4` added anywhere would turn every quoted snippet
                // into a silent miss, and the reader would have pasted it.
                $sourceAttrs .= is_string($value)
                    ? ' '.self::snippetAttribute($key, $value)
                    : ' :'.$key.'="'.$value.'"';

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

        // The snippet the caller shows, built alongside rather than recovered from
        // the rendered markup. Everything the bound form exists for — passing
        // values to Blade without compiling them as template source — is plumbing
        // that resolves to nothing when pasted into an application, so the snippet
        // carries literal values and the literal body instead.
        //
        // This is the whole point of returning it at all: a sandbox preview is
        // interactive, so the code to show is whatever the CURRENT props amount
        // to, and only this function knows that.
        $sourceBody = $body === '' ? '' : self::snippetSlotText($body);

        if ($sourceBody !== '' && isset(self::BODY_WRAPPERS[$component])) {
            $wrapTag = 'x-wirekit::'.self::BODY_WRAPPERS[$component];
            $sourceBody = '<'.$wrapTag.'>'.$sourceBody.'</'.$wrapTag.'>';
        }

        $source = $sourceBody === ''
            ? '<'.$tag.$sourceAttrs.' />'
            : '<'.$tag.$sourceAttrs.'>'.$sourceBody.'</'.$tag.'>';

        if (! function_exists('app') || ! app()->bound('view')) {
            // Test environment without a Laravel container — return the raw Blade
            // string so callers can inspect what would render. Prevents the
            // sandbox primitives from being unusable outside Laravel.
            return $blade;
        }

        return (string) Blade::render($blade, $data);
    }

    /**
     * One string prop as an attribute a developer can paste, rendering the value exactly as given.
     *
     * Blade reads a static attribute value as a PHP string literal: it compiles `{{ }}`,
     * `{!! !!}` and `@directives` inside it, a backslash can escape the quote that closes it,
     * and neither quote character can appear inside the quotes that delimit it. So the ordinary
     * value is written as it is — between single quotes when it carries a double quote — and a
     * value the grammar would reinterpret is written as a bound PHP string with every sensitive
     * character spelled as an escape sequence, which nothing downstream reads as syntax.
     */
    private static function snippetAttribute(string $key, string $value): string
    {
        $reinterpreted = str_contains($value, '\\')
            || str_contains($value, '{{')
            || str_contains($value, '{!!')
            || str_contains($value, '<?')
            || preg_match('/(?<!\w)@(?=[@\w])/', $value) === 1
            || (str_contains($value, '"') && str_contains($value, "'"));

        if (! $reinterpreted) {
            return str_contains($value, '"')
                ? $key."='".$value."'"
                : $key.'="'.$value.'"';
        }

        $literal = strtr($value, [
            '\\' => '\\\\',
            '$' => '\\$',
            '"' => '\\x22',
            "'" => '\\x27',
            '{' => '\\x7b',
            '@' => '\\x40',
            '<' => '\\x3c',
        ]);

        return ':'.$key.'=\'"'.$literal.'"\'';
    }

    /**
     * Slot text a developer can paste. It arrives HTML-escaped, which is right for a slot; what
     * is left is Blade's own syntax, which COMPILES in the template it is pasted into. A `{`
     * that starts an echo and an `@` that Blade would read as a directive are spelled as
     * entities, which render as the character and which Blade does not read.
     */
    private static function snippetSlotText(string $escaped): string
    {
        return (string) preg_replace_callback(
            '/\{(?=[{!])|(?<!\w)@(?=[@\w])/',
            /** @param  array<int, string>  $match */
            static function (array $match): string {
                return $match[0] === '{' ? '&#123;' : '&#64;';
            },
            $escaped,
        );
    }
}
