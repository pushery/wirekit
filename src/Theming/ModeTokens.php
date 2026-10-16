<?php

declare(strict_types=1);

namespace Pushery\WireKit\Theming;

/**
 * The custom properties a stylesheet declares for each mode: the `:root` blocks of the screen as
 * light, the `.dark` blocks and a `prefers-color-scheme: dark` query as dark, each `var(--other)`
 * alias resolved through the same table.
 *
 * `wirekit:doctor:a11y --theme-contrast` measures with this, and `WireKit::tokens()` hands the
 * package's own values to an application through it, so the two never read a stylesheet
 * differently.
 */
final class ModeTokens
{
    /**
     * The values each mode shows: light as its blocks declare it, and dark as the light table with
     * the dark blocks on top, which is what a page computes with `dark` on its root element. The
     * declarations are merged before any `var()` is substituted, so a light alias of a token the
     * dark blocks change takes the dark value, and every `var()` is substituted, a nested one
     * included, so a value reads as a color {@see WcagContrast} can measure. One that cannot be
     * resolved keeps its `var()`.
     *
     * @return array{light: array<string, string>, dark: array<string, string>}
     */
    public static function effective(string $css): array
    {
        $tables = self::declarations($css);

        return [
            'light' => self::substituted($tables['light']),
            'dark' => self::substituted(array_merge($tables['light'], $tables['dark'])),
        ];
    }

    /**
     * @param  array<string, string>  $tokens
     * @return array<string, string>
     */
    private static function substituted(array $tokens): array
    {
        return array_map(static fn (string $value): string => WcagContrast::resolveCssVars($value, $tokens), $tokens);
    }

    /**
     * Parse `--color-wk-*` declarations under `:root` and `.dark`
     * blocks (including the `:where(...)` wrapped forms). Resolves
     * simple `var(--other)` aliases by chaining through the same
     * token table.
     *
     * @return array{light: array<string, string>, dark: array<string, string>}
     */
    public static function parse(string $css): array
    {
        $tables = self::declarations($css);

        return [
            'light' => self::aliasesResolved($tables['light']),
            'dark' => self::aliasesResolved($tables['dark']),
        ];
    }

    /**
     * The custom properties each mode's blocks declare, as written: no `var()` is resolved yet,
     * so a caller can lay the dark blocks over the light ones first, as a page does.
     *
     * @return array{light: array<string, string>, dark: array<string, string>}
     */
    public static function declarations(string $css): array
    {
        if ($css === '') {
            return ['light' => [], 'dark' => []];
        }
        $css = (string) preg_replace('!/\*.*?\*/!s', '', $css);

        // Which presentation each block actually describes, before any of it is read as a
        // token table. See splitByPresentation() for the at-rule case this handles.
        $byPresentation = self::splitByPresentation($css);

        $extract = function (string $selector, string $source): array {
            $escaped = preg_quote($selector, '/');

            // The selector may sit in a list. A theme writes `:root, .light` for a token that
            // changes with the mode, as the theming guide asks. A rule read as absent is
            // indistinguishable from an empty one here, so every pairing would come back
            // "token unresolved" rather than wrong.
            //
            // `[^{};]*` and not `[^{}]*`: a selector list cannot contain a semicolon, and
            // without that exclusion a `.dark` inside a DECLARATION (`:where(.dark, .dark *)`)
            // runs across the gap to the next rule and captures a block it does not belong to.
            $pattern = '/(?<![\w-])(?::where\(\s*)?'.$escaped.'(?:\s*\))?(?:\s*,[^{};]*)?\s*\{([^}]*)\}/u';
            preg_match_all($pattern, $source, $matches);
            $tokens = [];
            foreach ($matches[1] ?? [] as $body) {
                // Capture EVERY custom property (not just --color-wk-*) so that
                // a hue-driven token like `oklch(L C var(--theme-hue))` can have
                // its `--theme-hue` reference substituted before parsing.
                // The pairings still only evaluate --color-wk-* pairs; the extra
                // entries are var()-resolution sources.
                if (preg_match_all('/(--[\w-]+)\s*:\s*([^;]+);/', $body, $declMatches, PREG_SET_ORDER)) {
                    foreach ($declMatches as $entry) {
                        $tokens[$entry[1]] = trim($entry[2]);
                    }
                }
            }

            return $tokens;
        };

        $light = $extract(':root', $byPresentation['screen']);

        // A theme can express dark two ways, and both are legitimate: the `.dark` class the
        // kit's own toggler writes, and `@media (prefers-color-scheme: dark)` for a theme
        // that follows the system with no toggle of its own. A `:root` block is a `:root`
        // block wherever it sits, so without this split a theme written the second way would
        // have its light mode audited against its dark colors, the worse direction of the
        // two: light is the mode people spot-check.
        //
        // The class wins where both declare the same token. It is the documented switch,
        // and it is what is actually on the element when the toggler has run.
        $dark = array_merge(
            $extract(':root', $byPresentation['dark']),
            $extract('.dark', $byPresentation['dark']),
            $extract('.dark', $byPresentation['screen']),
        );

        return ['light' => $light, 'dark' => $dark];
    }

    /**
     * Resolve var(--other-token) aliases within the same block.
     * Bounded recursion: max 5 hops per token to avoid cycles.
     *
     * @param  array<string, string>  $tokens
     * @return array<string, string>
     */
    private static function aliasesResolved(array $tokens): array
    {
        foreach ($tokens as $name => $value) {
            $hops = 0;
            while (preg_match('/^\s*var\(\s*(--[\w-]+)\s*(?:,[^)]*)?\)\s*$/', $value, $m) === 1 && $hops < 5) {
                $alias = $m[1];
                if (! isset($tokens[$alias])) {
                    break;
                }
                $value = $tokens[$alias];
                $hops++;
            }
            $tokens[$name] = $value;
        }

        return $tokens;
    }

    /**
     * The stylesheet split by the presentation each block actually applies to.
     *
     * An at-rule is invisible to a selector pattern. The extractor above finds `:root` and
     * `.dark` wherever they stand; an `@media` wrapper is just text in between. So a print
     * sheet written exactly as it should be --
     *
     *     @media print {
     *         :root,
     *         .dark { --color-wk-bg: #fff; --color-wk-text: #000; }
     *     }
     *
     * -- would read as a description of the screen, and as the last `.dark` match in the file
     * it would replace the entire dark table: text on bg would come back at 21.00:1, pure black
     * on pure white, whatever the real dark surface measures.
     *
     * An unrecognized prelude stays where it is. Dropping a block this classifier does
     * not understand would narrow the audit silently, and a narrower audit reports fewer
     * findings -- which reads exactly like a cleaner theme.
     *
     * @return array{screen: string, dark: string}
     */
    private static function splitByPresentation(string $css): array
    {
        $screen = '';
        $dark = '';
        $offset = 0;

        while (($at = strpos($css, '@media', $offset)) !== false) {
            $braceAt = strpos($css, '{', $at);

            if ($braceAt === false) {
                break;
            }

            $end = self::matchingBrace($css, $braceAt);

            if ($end === null) {
                break;
            }

            $screen .= substr($css, $offset, $at - $offset);

            $prelude = substr($css, $at + 6, $braceAt - $at - 6);
            $body = substr($css, $braceAt + 1, $end - $braceAt - 1);

            match (self::presentationOf($prelude)) {
                // Flattened rather than kept wrapped, which is equivalent here: the
                // extractor reads declaration blocks and never the at-rule around them.
                'screen' => $screen .= $body,
                'dark' => $dark .= $body,
                // Paper, speech and the alternative-preference themes describe something
                // this audit does not claim to measure.
                default => null,
            };

            $offset = $end + 1;
        }

        $screen .= substr($css, $offset);

        return ['screen' => $screen, 'dark' => $dark];
    }

    /**
     * Which of the audited presentations an `@media` prelude describes.
     *
     * Deliberately a short list of things that are definitely NOT the default screen,
     * rather than an attempt at a media-query parser: the cost of the two directions is not
     * symmetric. Misreading a block as screen puts a wrong number in a report somebody can
     * check against their own page; dropping one they meant makes a finding disappear.
     */
    private static function presentationOf(string $prelude): string
    {
        $prelude = strtolower(trim($prelude));

        // `not print` is every medium EXCEPT paper, so the word alone decides nothing. A
        // negated query is left where it is rather than guessed at.
        if (preg_match('/(?<![\w-])not(?![\w-])/', $prelude) === 1) {
            return 'screen';
        }

        if (preg_match('/prefers-color-scheme\s*:\s*dark/', $prelude) === 1) {
            return 'dark';
        }

        // The media TYPE, which is a bare word rather than a feature in parentheses.
        if (preg_match('/(^|[\s,(])(print|speech)(?![\w-])/', $prelude) === 1) {
            return 'drop';
        }

        // Alternative themes a user preference switches on. Each is a real surface and each
        // deserves its own audit; what none of them is, is the default one.
        if (preg_match('/prefers-contrast|forced-colors|inverted-colors/', $prelude) === 1) {
            return 'drop';
        }

        return 'screen';
    }

    /**
     * The index of the `}` closing the `{` at $open, or null when the file is unbalanced.
     *
     * An unbalanced stylesheet ends the walk rather than guessing, so a truncated file is
     * read as far as it is trustworthy and no further.
     */
    private static function matchingBrace(string $css, int $open): ?int
    {
        $depth = 0;
        $length = strlen($css);

        for ($i = $open; $i < $length; $i++) {
            if ($css[$i] === '{') {
                $depth++;
            } elseif ($css[$i] === '}') {
                $depth--;

                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }
}
