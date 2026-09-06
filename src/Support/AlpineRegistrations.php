<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * The component names `Alpine.data()` actually registers, read out of shipped JavaScript.
 *
 * ## The question this answers, and why the obvious one is wrong
 *
 * For nearly every Alpine attribute the CSP audit asks "does this expression parse".
 * For `x-data` that is the wrong question. There the rule is "is this a name something
 * registered", and the two answers come apart in both directions:
 *
 *   `x-data="{ open: false }"` parses perfectly and is precisely the form that has no
 *   registered factory behind it.
 *
 *   `x-data="wirekitAlertDialog({…})"` parses too — and leaves the element with NO SCOPE
 *   when that factory was never registered.
 *
 * ⚠️ **The second failure escapes upward, which is its camouflage.** An element whose
 * factory is missing gets no scope. Alpine's init removes `x-cloak` anyway, and
 * `x-show="open"` cannot evaluate, so it never sets `display: none`. The panel is VISIBLE
 * and every control in it is dead — which reads as a layout bug rather than as a missing
 * script, and sends the reader to the stylesheet.
 *
 * ## Why the pattern is not anchored on `Alpine.data(`
 *
 * A minified bundle renames the parameter. This package's own shipped bundle registers
 * through a SINGLE LETTER, so a pattern containing `Alpine.` reads 262 KiB full of
 * registrations as empty — and "no registrations found" is indistinguishable from
 * "nothing is registered", which is a finding rather than a broken extractor.
 *
 * The pattern therefore matches the CALL, not its receiver. It over-approximates: any
 * `.data("literal"` counts. That direction is the safe one — an over-wide registration
 * set can only ever fail to report an offender, never invent one — and a scan that
 * invents offenders in an adopting application is a command nobody runs twice.
 */
final class AlpineRegistrations
{
    /**
     * Every name registered across the given JavaScript files.
     *
     * @param  array<int, string>  $files
     * @return list<string>
     */
    public static function inFiles(array $files): array
    {
        $names = [];

        foreach ($files as $file) {
            if (! is_file($file)) {
                continue;
            }

            $names = array_merge($names, self::inSource((string) file_get_contents($file)));
        }

        $names = array_values(array_unique($names));
        sort($names);

        return $names;
    }

    /**
     * @return list<string>
     */
    public static function inSource(string $source): array
    {
        // `.data(` followed by a STRING LITERAL. The receiver is deliberately unmatched —
        // see the class docblock — and so is anything computed: a name that is not a
        // literal cannot be compared against a template's `x-data` either, so counting it
        // would add a name nobody can write.
        preg_match_all('/\.\s*data\s*\(\s*([\'"])([A-Za-z_$][\w$]*)\1/', $source, $m);

        return $m[2];
    }

    /**
     * The name an `x-data` expression needs registered, or null when it needs none.
     *
     * Three shapes, and only the first two are answerable from the source:
     *
     *   `wirekitDropdown({...})` and `wirekitDropdown` — a factory, name returned.
     *   `{ open: false }` — an inline object, which needs no registration and is its own
     *   finding under a CSP build. Reported by the caller, not here.
     *   anything else — returns null rather than guessing.
     */
    public static function requiredName(string $expression): ?string
    {
        $expression = trim($expression);

        if ($expression === '' || str_starts_with($expression, '{')) {
            return null;
        }

        // A bare identifier, or one followed by a call. Anchored at both ends so a member
        // expression (`foo.bar()`) or anything with an operator in it falls through to null
        // — this decides a registration question, and a guess here becomes a false finding
        // in somebody else's application.
        if (preg_match('/^([A-Za-z_$][\w$]*)\s*(?:\(.*\))?$/s', $expression, $m) === 1) {
            return $m[1];
        }

        return null;
    }
}
