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
     * `required: bool`). The metadata flavor catches a bug class the
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
     * branch IS scanned as bare. For the current component catalog
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

        // Collected as flag-less maps: the KEY is the name, and the value is
        // never read. Storing `true` in them invited a mutation that flips it to
        // `false` and changes nothing — a mutant no honest test can kill, since
        // there is no behavior to assert. `null` says the same thing about the
        // value while leaving nothing to flip.
        $issetNames = [];
        $bareNames = [];

        // Primary signal: isset($name) blocks identify slot-presence checks.
        if (preg_match_all('/\bisset\s*\(\s*\$([a-zA-Z][a-zA-Z0-9]*)\s*\)/', $contents, $matches)) {
            foreach ($matches[1] as $name) {
                $issetNames[$name] = null;
            }
        }

        // Bare references: `{{ $name }}`, `{!! $name !!}`, `$name->method()`,
        // `$name->isEmpty()`. These signal a hard dependency — if the
        // developer doesn't supply the slot, the component errors.
        if (preg_match_all('/\{\{\s*\$([a-zA-Z][a-zA-Z0-9]*)\b/', $contents, $bareMatches)) {
            foreach ($bareMatches[1] as $name) {
                $bareNames[$name] = null;
            }
        }
        if (preg_match_all('/\{!!\s*\$([a-zA-Z][a-zA-Z0-9]*)\b/', $contents, $rawMatches)) {
            foreach ($rawMatches[1] as $name) {
                $bareNames[$name] = null;
            }
        }
        // Method calls on slot vars — e.g. `$slot->isEmpty()`,
        // `$trigger->toHtml()`.
        if (preg_match_all('/\$([a-zA-Z][a-zA-Z0-9]*)->[a-zA-Z]/', $contents, $methodMatches)) {
            foreach ($methodMatches[1] as $name) {
                $bareNames[$name] = null;
            }
        }

        // Drop props + reserved names + locals from @php blocks. Without
        // the @php-local filter, every `@php $x = ... @endphp` followed
        // by `{{ $x }}` would falsely surface `x` as a required slot.
        $propNames = $bladePathForPropExclusion !== null
            ? array_map(fn ($p) => $p['name'], PropsParser::parseBlade($bladePathForPropExclusion))
            : [];
        // `errors` appeared twice here. Harmless to the result — the list is only
        // ever read through in_array — but a duplicate in a hand-kept list is how
        // the next name gets added twice instead of once.
        $reserved = ['loop', 'attributes', 'errors', 'this', 'message'];
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
     * `@php` blocks AND `@php(...)` inline directives. Used to filter the
     * `@php`-declared locals out of the slot-detection set so they don't
     * false-positive as required slots.
     *
     * Heuristic only — captures the simple assignment shape; a
     * destructuring assignment (`[$a, $b] = ...`) wouldn't be picked
     * up. For the current component catalog this covers every case.
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
     * Extract every Blade directive used in a file (e.g. `@if`, `@foreach`,
     * `@wirekitStyles`).
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
        // sort() reindexes in place, so the list is already a list.
        sort($directives);

        return $directives;
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
        // sort() reindexes in place, so the list is already a list.
        sort($names);

        return $names;
    }

    /**
     * Every `<x-wirekit::…>` usage in a source string, with the attribute names it carries.
     *
     * WALKED, not matched, and the two boundaries are the whole reason this exists rather
     * than a regex at the call site. Both were reported from a downstream repo that built
     * the naive version first:
     *
     *   1. `<x-wirekit::[^>]*>` ends the tag at the first `>`, and a perfectly ordinary
     *      `x-show="count > 3"` contains one. Every attribute after it is lost — silently,
     *      so a guard built on that pattern reports a clean sweep over half a tag.
     *   2. Collecting `name=` occurrences across the tag body reaches INSIDE values.
     *      `class="flex items-center gap-[…]"` produced three phantom attributes and
     *      `label="Live Visitors"` a fourth, so the first version of that guard reported
     *      five findings that were not there.
     *
     * So the scan tracks quote state and only reads a name when it is genuinely between
     * attributes. Values are not returned: the question this serves is whether a name is a
     * declared prop, and carrying values would invite a second, weaker parser for them.
     *
     * @return list<array{name: string, attributes: list<string>}>
     */
    public static function extractWireKitComponentUsagesFromSource(string $contents): array
    {
        $usages = [];
        $length = strlen($contents);
        $offset = 0;

        while (($start = strpos($contents, '<x-wirekit::', $offset)) !== false) {
            $cursor = $start + strlen('<x-wirekit::');

            // The component name: lowercase segments, optionally one dotted sub-component.
            $name = '';
            while ($cursor < $length && preg_match('/[a-z0-9\-.]/', $contents[$cursor]) === 1) {
                $name .= $contents[$cursor];
                $cursor++;
            }

            if ($name === '') {
                $offset = $cursor + 1;

                continue;
            }

            // Walk to the tag's real end, honoring quotes. A `>` inside a quoted value is
            // part of the value, not the end of the tag.
            $attributes = [];
            $quote = null;
            $token = '';

            while ($cursor < $length) {
                $char = $contents[$cursor];

                if ($quote !== null) {
                    // A backslash escapes the next character, so `\"` inside a
                    // double-quoted value does NOT close it. Without this, a real
                    // documented snippet — `:sort-action="\"sortBy('{$field}')\""` on
                    // `table.th` — ended its value at the first `\"`, and the walker then
                    // read the PHP that followed as attribute names, reporting `sortBy()\`
                    // as an undeclared prop. Skipping two characters is the whole fix.
                    if ($char === '\\' && $cursor + 1 < $length) {
                        $cursor += 2;

                        continue;
                    }

                    if ($char === $quote) {
                        $quote = null;
                    }
                    $cursor++;

                    continue;
                }

                if ($char === '"' || $char === "'") {
                    $quote = $char;
                    $cursor++;

                    continue;
                }

                if ($char === '>') {
                    break;
                }

                /*
                 * An unquoted `<` means this tag never closed.
                 *
                 * In well-formed Blade a `<` inside a tag is always inside a quoted value —
                 * an unquoted one starts a NEW element, which means the previous one was
                 * never terminated. Without this bound the walker keeps consuming whatever
                 * follows as attribute names, and the failure is the expensive kind: it does
                 * not throw, it returns plausible-looking output.
                 *
                 * Reported from a downstream tree that pointed this at PHP fixtures, where a
                 * tag split across string concatenation produced entries like
                 * `<button $html>` and `<ticker \;>` — eight of nine findings were artifacts,
                 * on a guard whose whole purpose was to report real ones. Silent plausible
                 * output is worse than a throw, because everything built on top of it inherits
                 * the confidence without the correctness.
                 *
                 * The input was out of contract — this reads Blade — and it is bounded anyway:
                 * a helper that is cheap to make safe should not require its callers to have
                 * read its docblock.
                 */
                if ($char === '<') {
                    $name = '';

                    break;
                }

                // An attribute name runs until whitespace, `=`, `/` or `>`. Anything
                // collected while a quote was open never reaches here, which is what keeps
                // values out of the result.
                if (preg_match('/[\s\/=]/', $char) === 1) {
                    if ($token !== '') {
                        $attributes[] = $token;
                        $token = '';
                    }
                    $cursor++;

                    continue;
                }

                $token .= $char;
                $cursor++;
            }

            if ($token !== '') {
                $attributes[] = $token;
            }

            // Blade's own prop-binding colon is not part of the name: `:value="$x"` binds
            // the `value` prop. Left in, every bound prop would read as unknown.
            $attributes = array_values(array_unique(array_map(
                static fn (string $a): string => ltrim($a, ':'),
                // Every entry here is already non-empty; the filter never removed anything.
                $attributes,
            )));

            // A tag the walker could not terminate is DISCARDED rather than reported with
            // whatever it managed to collect. `$name` is cleared at the bound above; this is
            // where that decision takes effect.
            if ($name !== '') {
                $usages[] = ['name' => $name, 'attributes' => $attributes];
            }

            $offset = max($cursor, $start + 1);
        }

        return $usages;
    }
}
