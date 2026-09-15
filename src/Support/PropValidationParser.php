<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * The allow-lists `WireKit::validateProp()` enforces, read from a component template.
 *
 * A prop that only accepts a fixed set of values has exactly one place where that set is written
 * down: the fourth argument of its `validateProp()` call. Nothing machine-readable carried it —
 * the docs page restates it in prose, and a reader that wants to OFFER the values (the docs app's
 * props playground, an agent picking one) had to guess from that prose. This class is the source
 * the manifest publishes from.
 *
 * THREE SPELLINGS, and a reader that knows only one is worse than none: a short allow-list looks
 * exactly like a complete one, and nothing goes red over it.
 *
 *   1. A literal array — `['sm', 'md', 'lg']`, the common case.
 *   2. `array_keys($map)`, where `$map` is a literal map assigned earlier in the same template.
 *      `spinner` picks its intents that way, `grid` its 36 column tokens.
 *   3. A class constant — `\Pushery\WireKit\VariantResolver::INTENTS`, which `button` uses for
 *      the two props every other component's button copies. Resolved THROUGH the constant, so a
 *      seventh intent arrives here the day it is added rather than when somebody retypes it.
 *
 * A fourth spelling must not pass silently, so an argument this class cannot resolve comes back
 * as an entry with an empty `values` and its `expression` intact. `ProseSkipsComponentElements`'
 * sibling guard on the catalog asserts there are none: a new shape is then a red test rather than
 * a quietly shorter list.
 *
 * An allow-list is not the whole contract, either. `card` turns `variant="outline"` into `outlined`
 * a line above its call, so a caller's spelling can be one `validateProp()` never sees. Each entry
 * carries those spellings as `aliases`, read through the value the call validates.
 *
 * Tokenized rather than pattern-matched, for the reason `PropsParser` gives at length: a Blade
 * file is prose before it is code, and an apostrophe in a comment has already made a hand-rolled
 * scan read a component as having no props at all. Every scan here starts at the bracket or paren
 * it is about, so nothing in front of it can be misjudged.
 */
final class PropValidationParser
{
    /**
     * Every `validateProp()` allow-list in one template, in source order.
     *
     * `kind` says which of three things the fourth argument was: an enumeration (`values`), the
     * error text of a rule enforced elsewhere (`message`), or a shape this class cannot prove
     * (`unresolved`). `values` is filled for the first only, and `expression` always holds what
     * stood there, so a guard can name a new shape instead of measuring less.
     *
     * `aliases` holds the spellings the template maps onto an allowed value before the call: a
     * caller may pass them, and `values` never lists them. Empty for most entries.
     *
     * @return list<array{component: string, prop: string, values: list<string>, expression: string, kind: string, aliases: list<string>}>
     */
    public static function parseSource(string $source): array
    {
        $found = [];
        $offset = 0;

        while (preg_match('/validateProp\s*\(/', $source, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $openParen = $match[0][1] + strlen($match[0][0]) - 1;
            $offset = $openParen + 1;

            $arguments = self::arguments($source, $openParen);

            // `validateProp($component, $prop, $value, $allowed)` — anything shorter is a
            // different function that happens to end in the same name.
            if ($arguments === null || count($arguments) < 4) {
                continue;
            }

            $component = self::stringLiteral($arguments[0]);

            // A call whose COMPONENT is not a literal is a different function that happens to end in
            // the same name, and there is nothing to publish it under.
            if ($component === null) {
                continue;
            }

            // Once per call: the map feeds the VALUE, and a value does not know which of the call's
            // names it arrived under, so every name accepts the same spellings.
            $aliases = self::aliasesOf($arguments[2], $source);

            foreach (self::propBranches($arguments[1], $arguments[3], $source) as [$prop, $list]) {
                if ($prop === null) {
                    // ⚠️ AN UNREADABLE PROP NAME IS NAMED, NOT DROPPED. This arm used to `continue`,
                    // so a call whose name was a variable left no trace at all: six of them, and the
                    // manifest published no values for callout, alert, text, card, feature or
                    // reading-progress while every guard over this class stayed green.
                    $found[] = [
                        'component' => $component,
                        'prop' => $arguments[1],
                        'values' => [],
                        'expression' => $arguments[1],
                        'kind' => 'unresolved',
                        'aliases' => $aliases,
                    ];

                    continue;
                }

                $values = self::resolve($list, $source);

                $found[] = [
                    'component' => $component,
                    'prop' => $prop,
                    'values' => $values ?? [],
                    'expression' => $list,
                    'kind' => match (true) {
                        $values === null => 'unresolved',
                        $values === [] => 'message',
                        default => 'values',
                    },
                    'aliases' => $aliases,
                ];
            }
        }

        return $found;
    }

    /**
     * @return list<array{component: string, prop: string, values: list<string>, expression: string, kind: string, aliases: list<string>}>
     */
    public static function parseBlade(string $bladePath): array
    {
        if (! is_file($bladePath)) {
            return [];
        }

        return self::parseSource((string) file_get_contents($bladePath));
    }

    /**
     * The resolved allow-lists of one template, as prop => values.
     *
     * A prop validated in more than one place — a sub-component that re-checks what its parent
     * checked — keeps the FIRST list, because that is the one its own `@props` entry sits under.
     *
     * @return array<string, list<string>>
     */
    public static function valuesByProp(string $source): array
    {
        $byProp = [];

        foreach (self::parseSource($source) as $entry) {
            if ($entry['values'] === [] || isset($byProp[$entry['prop']])) {
                continue;
            }

            $byProp[$entry['prop']] = $entry['values'];
        }

        return $byProp;
    }

    /**
     * The call's arguments as source text, or null when the call never closes.
     *
     * Lexed from the opening paren so the prose above it cannot be misread as code, and split on
     * commas the LEXER saw at argument depth — a comma inside a nested array, a string or a
     * closure is part of one argument, not a separator.
     *
     * @return list<string>|null
     */
    private static function arguments(string $source, int $openParen): ?array
    {
        $tokens = token_get_all('<?php '.substr($source, $openParen));
        array_shift($tokens);

        $depth = 0;
        $arguments = [];
        $current = '';

        foreach ($tokens as $token) {
            $text = is_array($token) ? $token[1] : $token;

            if ($text === '(' || $text === '[' || $text === '{') {
                $depth++;

                if ($depth === 1) {
                    continue;
                }
            } elseif ($text === ')' || $text === ']' || $text === '}') {
                $depth--;

                if ($depth === 0) {
                    $arguments[] = trim($current);

                    return $arguments;
                }
            } elseif ($text === ',' && $depth === 1) {
                $arguments[] = trim($current);
                $current = '';

                continue;
            }

            if ($depth >= 1) {
                $current .= $text;
            }
        }

        return null;
    }

    /**
     * The prop name or names one call validates under, each paired with the allow-list it uses.
     *
     * A literal name is the common case: one pair, the list as written. The other case is a call
     * that names the prop the CALLER wrote. `callout` accepts `intent` and its older `variant`, and
     * reports an error under whichever one was set, so the name is a variable a ternary picks:
     *
     *     $intentPropName = $intent !== null ? 'intent' : 'variant';
     *
     * Both names validate against the list, so both publish it. When the list itself depends on
     * the same variable, as `card`'s does (`outline` for `surface`, `outlined` for `variant`), each
     * name takes its own branch; one shared list would publish a value the other prop rejects.
     *
     * A name this cannot read comes back as `[null, …]`, which the caller turns into an
     * `unresolved` entry rather than silence.
     *
     * @return list<array{0: ?string, 1: string}>
     */
    private static function propBranches(string $nameArgument, string $listArgument, string $source): array
    {
        $literal = self::stringLiteral($nameArgument);

        if ($literal !== null) {
            return [[$literal, $listArgument]];
        }

        $variable = trim($nameArgument);
        $names = preg_match('/^\$\w+$/', $variable) === 1 ? self::ternaryNames($source, $variable) : null;

        if ($names === null) {
            return [[null, $listArgument]];
        }

        $branches = self::branchesOn($listArgument, $variable);

        // No branches: one list serves both names. Otherwise the name the condition tests takes the
        // `then` branch, and the other name the `else` branch.
        return array_map(
            static fn (string $name): array => [$name, match (true) {
                $branches === null => $listArgument,
                $name === $branches['match'] => $branches['then'],
                default => $branches['else'],
            }],
            $names,
        );
    }

    /**
     * The two names a variable can hold, when it is assigned `<condition> ? 'a' : 'b'`.
     *
     * @return list<string>|null
     */
    private static function ternaryNames(string $source, string $variable): ?array
    {
        if (preg_match('/'.preg_quote($variable, '/').'\s*=(?!=)/', $source, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $start = $match[0][1] + strlen($match[0][0]);
        $end = strpos($source, ';', $start);

        if ($end === false) {
            return null;
        }

        $parts = self::topLevelTernary(substr($source, $start, $end - $start));

        if ($parts === null) {
            return null;
        }

        $first = self::stringLiteral($parts[1]);
        $second = self::stringLiteral($parts[2]);

        return $first !== null && $second !== null ? [$first, $second] : null;
    }

    /**
     * The two branches of an allow-list that depends on the name variable, as the name the
     * condition tests and the list for it and for every other name. Null when the list is
     * `$variable === 'x' ? [A] : [B]` in no spelling, which means one list serves both names.
     *
     * @return array{match: string, then: string, else: string}|null
     */
    private static function branchesOn(string $listArgument, string $variable): ?array
    {
        $parts = self::topLevelTernary($listArgument);

        if ($parts === null) {
            return null;
        }

        $condition = trim($parts[0]);
        $pattern = '/^(?:'.preg_quote($variable, '/')."\s*===\s*('[^']*')|('[^']*')\s*===\s*".preg_quote($variable, '/').')$/';

        if (preg_match($pattern, $condition, $match) !== 1) {
            return null;
        }

        $matched = self::stringLiteral(($match[1] ?? '') !== '' ? $match[1] : ($match[2] ?? ''));

        return $matched === null ? null : ['match' => $matched, 'then' => trim($parts[1]), 'else' => trim($parts[2])];
    }

    /**
     * An expression split at its top-level `?` and matching `:`, or null when it is no ternary.
     *
     * Split on the tokens the lexer saw at depth zero, so a `?` or `:` inside a string, an array or
     * a nested call cannot be taken for the operator.
     *
     * @return array{0: string, 1: string, 2: string}|null
     */
    private static function topLevelTernary(string $expression): ?array
    {
        $tokens = token_get_all('<?php '.$expression);
        array_shift($tokens);

        $depth = 0;
        $parts = ['', '', ''];
        $part = 0;
        $pending = 0;

        foreach ($tokens as $token) {
            $text = is_array($token) ? $token[1] : $token;

            if ($text === '(' || $text === '[' || $text === '{') {
                $depth++;
            } elseif ($text === ')' || $text === ']' || $text === '}') {
                $depth--;
            } elseif ($depth === 0 && $text === '?' && $part === 0) {
                $part = 1;

                continue;
            } elseif ($depth === 0 && $text === '?') {
                $pending++;
            } elseif ($depth === 0 && $text === ':' && $part === 1 && $pending === 0) {
                $part = 2;

                continue;
            } elseif ($depth === 0 && $text === ':' && $pending > 0) {
                $pending--;
            }

            $parts[$part] .= $text;
        }

        return $part === 2 ? $parts : null;
    }

    /**
     * The text of a single-string-literal argument, or null for anything else.
     */
    private static function stringLiteral(string $argument): ?string
    {
        $tokens = array_values(array_filter(
            token_get_all('<?php '.$argument),
            fn ($token): bool => ! is_array($token) || ! in_array($token[0], [T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true),
        ));

        if (count($tokens) !== 1 || ! is_array($tokens[0]) || $tokens[0][0] !== T_CONSTANT_ENCAPSED_STRING) {
            return null;
        }

        return self::unquote($tokens[0][1]);
    }

    /**
     * The spellings a template maps onto an allowed value before the call validates it.
     *
     * The map is found through the VALUE the call checks: `$tone` is validated, so `$toneAliases`
     * is what feeds it. Never through a prop name. `feature` validates one value under `intent` and
     * under `tone`, and a read keyed by the map's own name gave its spellings to `tone` alone, so a
     * guard widened by it accused ten code blocks that pass `intent="primary"` or `intent="info"`
     * correctly.
     *
     * @return list<string>
     */
    private static function aliasesOf(string $valueArgument, string $source): array
    {
        $subject = trim($valueArgument);

        // A cast is not a variable a map can feed: `feature` and `empty-state` validate
        // `(string) $level`, and neither maps a level.
        if (preg_match('/^\$\w+$/', $subject) !== 1) {
            return [];
        }

        return self::mapKeys($source, $subject.'Aliases');
    }

    /**
     * The allow-list an argument stands for: its values, an empty list when the argument is a
     * MESSAGE rather than an enumeration, or null when the shape is one this class cannot prove.
     *
     * ⚠️ THE MESSAGE SHAPE IS NOT AN ALLOW-LIST, AND READING IT AS ONE PUBLISHES NONSENSE.
     * Four call sites hand `validateProp()` a single sentence — `['a CSS length such as 14rem,
     * 20ch, 280px']` — from inside an `if (! valid)` branch, so the array is the error text and
     * the rule itself is the pattern above it. A manifest that published that sentence as an
     * accepted value would offer it in a props playground's dropdown. A value token never
     * contains a space and a sentence always does, which is the whole test.
     *
     * @return list<string>|null
     */
    private static function resolve(string $expression, string $source): ?array
    {
        $expression = trim($expression);

        $values = match (true) {
            str_starts_with($expression, '[') => self::literalValues($expression),
            preg_match('/^array_keys\s*\(\s*(\$\w+)\s*\)$/', $expression, $match) === 1 => self::mapKeys($source, $match[1]),
            // A bare variable, when what it holds is a literal list. `reveal` builds its presets
            // by suffixing a base list at runtime instead, and that one stays unresolved on
            // purpose: interpreting an `array_map` would fit this class to a single call site.
            preg_match('/^\$\w+$/', $expression) === 1 => self::variableValues($source, $expression),
            preg_match('/^\\\\?[A-Za-z_][\\\\\w]*::[A-Z][A-Z0-9_]*$/', $expression) === 1 => self::constantValues($expression),
            default => [],
        };

        if ($values === []) {
            return null;
        }

        foreach ($values as $value) {
            if (str_contains($value, ' ')) {
                return [];
            }
        }

        return $values;
    }

    /**
     * The strings of a literal list. A `=>` makes it a map, and then the VALUES are the
     * allow-list — the keys are whatever the author indexed it by.
     *
     * @return list<string>
     */
    private static function literalValues(string $literal): array
    {
        $tokens = token_get_all('<?php '.$literal.';');
        $isMap = false;

        foreach ($tokens as $token) {
            if (is_array($token) && $token[0] === T_DOUBLE_ARROW) {
                $isMap = true;

                break;
            }
        }

        $values = [];
        $afterArrow = false;

        foreach ($tokens as $token) {
            if (is_array($token) && $token[0] === T_DOUBLE_ARROW) {
                $afterArrow = true;

                continue;
            }

            if (! is_array($token) || ! in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_LNUMBER], true)) {
                continue;
            }

            if ($isMap && ! $afterArrow) {
                continue;
            }

            $values[] = $token[0] === T_LNUMBER ? $token[1] : self::unquote($token[1]);
            $afterArrow = false;
        }

        return $values;
    }

    /**
     * The strings of a literal list assigned to `$variable` in the same template.
     *
     * @return list<string>
     */
    private static function variableValues(string $source, string $variable): array
    {
        if (preg_match('/'.preg_quote($variable, '/').'\s*=\s*\[/', $source, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return [];
        }

        $openBracket = $match[0][1] + strlen($match[0][0]) - 1;
        $literal = self::balancedBody($source, $openBracket);

        return $literal === null ? [] : self::literalValues($literal);
    }

    /**
     * The `[…]` starting at `$openBracket`, brackets included, or null when it never closes.
     *
     * Counted over the tokens the LEXER treated as brackets, so a `]` inside a string, a comment
     * or a heredoc body cannot move the depth.
     */
    private static function balancedBody(string $source, int $openBracket): ?string
    {
        $tail = substr($source, $openBracket);
        $tokens = token_get_all('<?php '.$tail);
        array_shift($tokens);

        $depth = 0;
        $cursor = 0;

        foreach ($tokens as $token) {
            $text = is_array($token) ? $token[1] : $token;

            if ($text === '[') {
                $depth++;
            } elseif ($text === ']') {
                $depth--;

                if ($depth === 0) {
                    return substr($tail, 0, $cursor + 1);
                }
            }

            $cursor += strlen($text);
        }

        return null;
    }

    /**
     * The keys of a literal map assigned to `$variable` in the same template.
     *
     * Every key, not the first one on each line: the maps are written several pairs per line, and
     * a line-anchored read of `grid`'s columns kept every third key — 12 of 36 — while staying
     * green, because an allow-list that comes back short only makes a check demand less.
     *
     * @return list<string>
     */
    private static function mapKeys(string $source, string $variable): array
    {
        if (preg_match('/'.preg_quote($variable, '/').'\s*=\s*\[/', $source, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return [];
        }

        $openBracket = $match[0][1] + strlen($match[0][0]) - 1;
        $tokens = token_get_all('<?php '.substr($source, $openBracket));
        array_shift($tokens);

        $depth = 0;
        $keys = [];
        $pending = null;

        foreach ($tokens as $token) {
            $text = is_array($token) ? $token[1] : $token;

            if ($text === '[' || $text === '(' || $text === '{') {
                $depth++;

                continue;
            }

            if ($text === ']' || $text === ')' || $text === '}') {
                $depth--;

                if ($depth === 0) {
                    return $keys;
                }

                continue;
            }

            if ($depth !== 1) {
                continue;
            }

            if (is_array($token) && in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_LNUMBER], true)) {
                $pending = $token[0] === T_LNUMBER ? $token[1] : self::unquote($token[1]);

                continue;
            }

            if (is_array($token) && $token[0] === T_DOUBLE_ARROW && $pending !== null) {
                $keys[] = $pending;
            }

            if (! is_array($token) || $token[0] !== T_WHITESPACE) {
                $pending = null;
            }
        }

        return [];
    }

    /**
     * An array constant's values, resolved through the constant itself.
     *
     * @return list<string>
     */
    private static function constantValues(string $name): array
    {
        $name = ltrim($name, '\\');

        if (! defined($name) && ! str_contains($name, '\\')) {
            $name = 'Pushery\\WireKit\\'.$name;
        }

        if (! defined($name)) {
            return [];
        }

        $resolved = constant($name);

        if (! is_array($resolved)) {
            return [];
        }

        return array_values(array_map('strval', $resolved));
    }

    private static function unquote(string $literal): string
    {
        $inner = substr($literal, 1, -1);

        return str_replace(["\\'", '\\"', '\\\\'], ["'", '"', '\\'], $inner);
    }
}
