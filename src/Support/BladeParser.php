<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * Token-stream-based parser for Blade-content-as-data.
 *
 * Companion to `PropsParser`. Where PropsParser focuses narrowly on the
 * `@props([…])` block, BladeParser exposes the broader Blade-content
 * surface: named slots, directive usages, and pass-through comment
 * extraction.
 *
 * Every developer that needs to introspect Blade content (CLI commands,
 * drift audits, future schema-export pipelines) routes through here OR
 * through PropsParser. Direct regex scanning of Blade source for this
 * data should be flagged by a future drift-audit guard (next iteration).
 *
 * Why this exists alongside PropsParser: the parser strategies overlap
 * but the use cases differ. PropsParser parses ONE PHP-syntax block
 * (the array literal inside `@props(...)`). BladeParser scans the
 * whole Blade file with semantic awareness of Blade's own syntax
 * (`@directive`, `{{ }}`, `{{-- --}}`, `<x-…>`).
 */
final class BladeParser
{
    /**
     * Extract named slots referenced from a Blade file.
     *
     * Slot-detection strategy: slots are reliably identified by
     * `@isset($name)` checks — the canonical "is this slot supplied?"
     * pattern. Bare `$slot` (the default slot) is always included if
     * the file references it. Bare `{{ $name }}` is too noisy to use
     * as a slot signal (catches every prop interpolation and Blade
     * local), so we ignore it for slot detection.
     *
     * Filtering: known prop names from the same component's @props
     * block are removed, and Blade-reserved names (loop, attributes,
     * errors, slot) are excluded from the @isset capture but `slot`
     * is added back if the file uses {{ $slot }}.
     *
     * @return list<string>
     */
    public static function extractSlots(string $bladePath): array
    {
        if (! file_exists($bladePath)) {
            return [];
        }
        $contents = (string) file_get_contents($bladePath);
        if ($contents === '') {
            return [];
        }

        return self::extractSlotsFromSource($contents, $bladePath);
    }

    /**
     * @return list<string>
     */
    public static function extractSlotsFromSource(string $contents, ?string $bladePathForPropExclusion = null): array
    {
        $records = self::extractSlotsWithMetadataFromSource($contents, $bladePathForPropExclusion);

        return array_values(array_map(fn (array $r) => $r['name'], $records));
    }

    /**
     * Extract named slots with per-slot metadata (currently just
     * `required: bool`). The metadata flavour catches a bug class the
     * plain extraction misses: components that reference a named slot
     * directly via `{{ $name }}` WITHOUT an `@isset($name)` guard
     * render `Undefined variable $name` when the developer omits the
     * slot. popover / hover-card / context-menu all do this for their
     * `trigger` slot — schema previously reported them as default-slot
     * only, hiding the requirement.
     *
     * Detection heuristic:
     *   - A slot wrapped in `@isset($name)` / `isset($name)` is OPTIONAL
     *     (the component explicitly checks presence before rendering).
     *   - A slot referenced bare via `{{ $name }}` or `{!! $name !!}`
     *     OR a method call (`$name->isNotEmpty()`) without an enclosing
     *     `@isset` guard is REQUIRED.
     *   - The default `$slot` is always REQUIRED when referenced (Laravel
     *     provides it automatically, but the component's rendering
     *     contract assumes it).
     *
     * Heuristic limitations: the scanner is line-aware, not
     * AST-aware — a `{{ $trigger }}` reference inside an `@isset($trigger)`
     * branch IS scanned as bare. For the current component catalogue
     * this is correct because `@isset` blocks DON'T re-interpolate
     * the slot inside themselves (they conditionally include OTHER
     * markup based on slot presence). If a component starts doing
     * `@isset($trigger) {{ $trigger }} @endisset`, this heuristic
     * would mark it as required when it's optional — at that point
     * the heuristic needs to widen, OR the component author should
     * use the canonical `{{ $trigger ?? '' }}` shape.
     *
     * @return list<array{name: string, required: bool}>
     */
    /**
     * @param  list<string>  $additionalExcludes  Extra names to drop from the
     *                                            detected slot set. Used by the
     *                                            JSON-manifest exporter to pass
     *                                            a class-based component's
     *                                            public-property names, which
     *                                            appear as bare `{{ $name }}`
     *                                            references in the template
     *                                            but are NOT real slots.
     */
    public static function extractSlotsWithMetadataFromSource(string $contents, ?string $bladePathForPropExclusion = null, array $additionalExcludes = []): array
    {
        // Strip Blade `{{-- … --}}` comments BEFORE scanning so phantom
        // slot signals inside documentation comments don't leak into
        // the detected slot list.
        $contents = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $contents);

        $issetNames = [];
        $bareNames = [];

        // Primary signal: isset($name) blocks identify slot-presence checks.
        if (preg_match_all('/\bisset\s*\(\s*\$([a-zA-Z][a-zA-Z0-9]*)\s*\)/', $contents, $matches)) {
            foreach ($matches[1] as $name) {
                $issetNames[$name] = true;
            }
        }

        // Bare references: `{{ $name }}`, `{!! $name !!}`, `$name->method()`,
        // `$name->isEmpty()`. These signal a hard dependency — if the
        // developer doesn't supply the slot, the component errors.
        if (preg_match_all('/\{\{\s*\$([a-zA-Z][a-zA-Z0-9]*)\b/', $contents, $bareMatches)) {
            foreach ($bareMatches[1] as $name) {
                $bareNames[$name] = true;
            }
        }
        if (preg_match_all('/\{!!\s*\$([a-zA-Z][a-zA-Z0-9]*)\b/', $contents, $rawMatches)) {
            foreach ($rawMatches[1] as $name) {
                $bareNames[$name] = true;
            }
        }
        // Method calls on slot vars — e.g. `$slot->isEmpty()`,
        // `$trigger->toHtml()`.
        if (preg_match_all('/\$([a-zA-Z][a-zA-Z0-9]*)->[a-zA-Z]/', $contents, $methodMatches)) {
            foreach ($methodMatches[1] as $name) {
                $bareNames[$name] = true;
            }
        }

        // Drop props + reserved names + locals from @php blocks. Without
        // the @php-local filter, every `@php $x = ... @endphp` followed
        // by `{{ $x }}` would falsely surface `x` as a required slot.
        $propNames = $bladePathForPropExclusion !== null
            ? array_map(fn ($p) => $p['name'], PropsParser::parseBlade($bladePathForPropExclusion))
            : [];
        $reserved = ['loop', 'attributes', 'errors', 'this', 'errors', 'message'];
        $phpLocals = self::extractPhpLocalsFromSource($contents);

        $exclude = array_unique(array_merge($propNames, $reserved, $phpLocals, $additionalExcludes));

        // Build the merged slot set:
        //   - Every isset-checked name → OPTIONAL.
        //   - Every bare-referenced name NOT in the isset set → REQUIRED.
        //   - Bare-referenced `slot` (the default) → REQUIRED when used.
        $records = [];
        foreach (array_keys($issetNames) as $name) {
            if (in_array($name, $exclude, true)) {
                continue;
            }
            $records[$name] = ['name' => $name, 'required' => false];
        }
        foreach (array_keys($bareNames) as $name) {
            if (in_array($name, $exclude, true)) {
                continue;
            }
            // A name that ALSO appears in isset stays optional — the
            // explicit guard wins. Otherwise it's required.
            if (! isset($records[$name])) {
                $records[$name] = ['name' => $name, 'required' => true];
            }
        }

        return array_values($records);
    }

    /**
     * Best-effort extraction of `$name = ...` assignments inside
     * `@php` blocks AND `@php(...)` inline directives. Used to filter
     *
     * @php-declared locals out of the slot-detection set so they don't
     * false-positive as required slots.
     *
     * Heuristic only — captures the simple assignment shape; a
     * destructuring assignment (`[$a, $b] = ...`) wouldn't be picked
     * up. For the current component catalogue this covers every case.
     *
     * @return list<string>
     */
    private static function extractPhpLocalsFromSource(string $contents): array
    {
        $locals = [];

        // @php blocks: `@php ... @endphp`.
        if (preg_match_all('/@php\s*(.*?)@endphp/s', $contents, $blockMatches)) {
            foreach ($blockMatches[1] as $body) {
                self::collectAssignmentsFrom($body, $locals);
            }
        }
        // @php(expr) inline: single statement.
        if (preg_match_all('/@php\s*\((.*?)\)/s', $contents, $inlineMatches)) {
            foreach ($inlineMatches[1] as $body) {
                self::collectAssignmentsFrom($body, $locals);
            }
        }

        return array_values(array_unique($locals));
    }

    /**
     * Scan a PHP-source string for `$name = ...` assignments and
     * append each captured name to the `$locals` accumulator. Catches
     * the common `$foo = ...;` shape; nested-array / destructuring
     * shapes fall through silently.
     *
     * @param  list<string>  $locals
     */
    private static function collectAssignmentsFrom(string $body, array &$locals): void
    {
        if (preg_match_all('/\$([a-zA-Z][a-zA-Z0-9]*)\s*=(?!=)/', $body, $matches)) {
            foreach ($matches[1] as $name) {
                $locals[] = $name;
            }
        }
        // foreach (`@foreach($items as $item)`) declares $item locally;
        // same risk class. Catch the obvious shape.
        if (preg_match_all('/foreach\s*\(\s*[^\s]+\s+as\s+\$([a-zA-Z][a-zA-Z0-9]*)/', $body, $foreachMatches)) {
            foreach ($foreachMatches[1] as $name) {
                $locals[] = $name;
            }
        }
    }

    /**
     * Extract every Blade directive used in a file (e.g. @if, @foreach,
     *
     * @wirekitStyles).
     *
     * Useful for drift audits ("does this file use a directive that
     * isn't registered?") and CLI inspection.
     *
     * @return list<string> sorted, deduplicated directive names (without the leading `@`)
     */
    public static function extractDirectives(string $bladePath): array
    {
        if (! file_exists($bladePath)) {
            return [];
        }
        $contents = (string) file_get_contents($bladePath);
        if ($contents === '') {
            return [];
        }

        return self::extractDirectivesFromSource($contents);
    }

    /**
     * @return list<string>
     */
    public static function extractDirectivesFromSource(string $contents): array
    {
        // `@word` directives — exclude email-style `@gmail.com` patterns
        // by requiring `@` either at line-start or preceded by whitespace
        // or a non-word character.
        if (! preg_match_all('/(?:^|[^\w@])@([a-zA-Z][a-zA-Z0-9_]*)/m', $contents, $matches)) {
            return [];
        }
        $directives = array_unique($matches[1]);
        sort($directives);

        return array_values($directives);
    }

    /**
     * Extract every Blade comment (`{{-- … --}}`) from a file.
     *
     * Useful for content-audit tools (e.g. "find every TODO comment"
     * across the component tree).
     *
     * @return list<string> raw inner-comment text per match, in source order
     */
    public static function extractComments(string $bladePath): array
    {
        if (! file_exists($bladePath)) {
            return [];
        }
        $contents = (string) file_get_contents($bladePath);
        if ($contents === '') {
            return [];
        }

        return self::extractCommentsFromSource($contents);
    }

    /**
     * @return list<string>
     */
    public static function extractCommentsFromSource(string $contents): array
    {
        if (! preg_match_all('/\{\{--(.*?)--\}\}/s', $contents, $matches)) {
            return [];
        }

        return array_map('trim', $matches[1]);
    }

    /**
     * Extract every WireKit component reference (`<x-wirekit::name>`)
     * from a Blade file, returning unique component names sorted.
     *
     * @return list<string>
     */
    public static function extractWireKitComponentReferences(string $bladePath): array
    {
        if (! file_exists($bladePath)) {
            return [];
        }
        $contents = (string) file_get_contents($bladePath);
        if ($contents === '') {
            return [];
        }

        return self::extractWireKitComponentReferencesFromSource($contents);
    }

    /**
     * @return list<string>
     */
    public static function extractWireKitComponentReferencesFromSource(string $contents): array
    {
        if (! preg_match_all('/<x-wirekit::([a-z][a-z0-9\-]*(?:\.[a-z][a-z0-9\-]*)?)\b/', $contents, $matches)) {
            return [];
        }
        $names = array_unique($matches[1]);
        sort($names);

        return array_values($names);
    }
}
