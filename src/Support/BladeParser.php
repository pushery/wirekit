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
        // Strip Blade `{{-- … --}}` comments BEFORE scanning so phantom
        // slot signals inside documentation comments don't leak into
        // the detected slot list. Without this, a `{{-- $foo is rendered
        // below --}}` comment would surface `foo` as a real slot.
        $contents = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $contents);

        $slots = [];

        // Primary signal: isset($name) blocks identify slot-presence checks.
        // Matches both @isset(...) Blade directive AND isset(...) inside
        // @if / @elseif clauses (e.g. stat uses @elseif(isset($iconSlot))).
        if (preg_match_all('/\bisset\s*\(\s*\$([a-zA-Z][a-zA-Z0-9]*)\s*\)/', $contents, $matches)) {
            foreach ($matches[1] as $name) {
                $slots[$name] = true;
            }
        }

        // Default slot: include 'slot' if the file outputs {{ $slot }} or
        // checks $slot->isNotEmpty(). Some components use the default
        // slot without an isset check (it's always defined).
        if (preg_match('/\$slot\b/', $contents)) {
            $slots['slot'] = true;
        }

        // Drop prop names and Blade-reserved names.
        $propNames = $bladePathForPropExclusion !== null
            ? array_map(fn ($p) => $p['name'], PropsParser::parseBlade($bladePathForPropExclusion))
            : [];
        $reserved = ['loop', 'attributes', 'errors'];
        $slotNames = array_diff(array_keys($slots), $propNames, $reserved);

        return array_values(array_unique($slotNames));
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
