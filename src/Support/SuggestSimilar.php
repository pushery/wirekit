<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * Uniform "Did you mean?" helper for every WireKit CLI surface (and
 * runtime validation messages).
 *
 * Strategy: levenshtein() distance, capped at $maxDistance. Ties
 * broken by shorter haystack entry first (the common-case typo
 * correction bias — a short-name typo usually meant a short name).
 *
 * Cross-cutting use sites:
 *  - Console: thirteen commands, from `wirekit:show` to `wirekit:install` — the current set
 *    is `grep -rl SuggestSimilar src/`, which cannot go stale the way a list does
 *  - Runtime: `WireKit::validateProp()` error messages, and `IconResolver`
 *
 * ⚠️ This list used to name five commands plus "Drift audits" and "Sandbox" reporters. The
 * five were a subset of thirteen, and NEITHER of the last two exists — nothing under
 * `src/Drift/` or `src/Sandbox/` references this class. A caller list in a docblock is read
 * as the blast radius of a change, so understating it by eight and inventing two is the worst
 * of both directions: a reader checking the named ones concludes they have checked.
 *
 * Performance note: levenshtein() over ~120 strings is ~120 microseconds
 * on a modern CPU. Negligible for CLI / one-shot use. Do NOT call inside
 * hot render paths.
 */
final class SuggestSimilar
{
    /**
     * Return up to $max candidates from $haystack ranked by similarity to $needle.
     *
     * Tie-breaking: when two candidates share the same distance score, the
     * shorter entry sorts first. Empty needle or empty haystack returns [].
     *
     * @param  list<string>  $haystack
     * @return list<string>
     */
    public static function byLevenshtein(string $needle, array $haystack, int $max = 3, int $maxDistance = 3): array
    {
        if ($needle === '' || $haystack === []) {
            return [];
        }

        $scored = [];
        foreach ($haystack as $candidate) {
            if (! is_string($candidate) || $candidate === '') {
                continue;
            }
            $distance = levenshtein($needle, $candidate);
            if ($distance <= $maxDistance) {
                $scored[] = ['name' => $candidate, 'distance' => $distance];
            }
        }

        usort($scored, function ($a, $b) {
            if ($a['distance'] !== $b['distance']) {
                return $a['distance'] <=> $b['distance'];
            }

            return strlen($a['name']) <=> strlen($b['name']);
        });

        return array_values(array_map(
            fn (array $entry): string => $entry['name'],
            array_slice($scored, 0, $max)
        ));
    }

    /**
     * Same as byLevenshtein() but returns the full (name, distance) shape
     * so callers can weight or filter on the score.
     *
     * Exposed for MCP-server-style developers that want recently-used-component
     * bias on top of pure-distance ranking.
     *
     * @param  list<string>  $haystack
     * @return list<array{name: string, distance: int}>
     */
    public static function byLevenshteinScored(string $needle, array $haystack, int $max = 3, int $maxDistance = 3): array
    {
        if ($needle === '' || $haystack === []) {
            return [];
        }

        $scored = [];
        foreach ($haystack as $candidate) {
            if (! is_string($candidate) || $candidate === '') {
                continue;
            }
            $distance = levenshtein($needle, $candidate);
            if ($distance <= $maxDistance) {
                $scored[] = ['name' => $candidate, 'distance' => $distance];
            }
        }

        usort($scored, function ($a, $b) {
            if ($a['distance'] !== $b['distance']) {
                return $a['distance'] <=> $b['distance'];
            }

            return strlen($a['name']) <=> strlen($b['name']);
        });

        return array_values(array_slice($scored, 0, $max));
    }

    /**
     * Pretty-print the suggestion list as a human-readable string.
     * Returns null when no suggestions exist (so callers can branch on
     * "show the line vs skip it" without re-checking emptiness).
     *
     * @param  list<string>  $suggestions
     */
    public static function format(array $suggestions): ?string
    {
        if ($suggestions === []) {
            return null;
        }
        if (count($suggestions) === 1) {
            return "Did you mean: {$suggestions[0]}?";
        }

        return 'Did you mean: '.implode(', ', $suggestions).'?';
    }
}
