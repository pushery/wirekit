<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * Reads an application stylesheet as configuration, for the commands that inspect or edit
 * `resources/css/app.css`: `wirekit:install` decides from it whether to add the WireKit
 * `@source`, and `wirekit:verify` reads its directives and its token declarations.
 */
final class AppCss
{
    /**
     * The stylesheet without its comments, leaving quoted strings alone.
     *
     * A comment that happens to contain a scanned phrase (a commented-out directive left behind
     * after a migration, an example `--font-wk-sans: …` pasted above the real declaration) is
     * text, not configuration, so every reader strips comments before it scans.
     *
     * A Tailwind `@source` argument is a quoted path, and a glob is made of the same two
     * characters a comment is: `views/**` followed by `/*.blade.php` reads as a comment to
     * anything that does not know where the strings are, and `views/*.blade.php` carries an
     * opener with no closer. A pattern strip over the whole file therefore swallows everything
     * between a glob and the next real comment, declarations included. Tracking the quote state
     * is what separates a configuration line from a commented-out one.
     */
    public static function withoutComments(string $css): string
    {
        $out = '';
        $quote = null;
        $length = strlen($css);

        for ($i = 0; $i < $length; $i++) {
            $char = $css[$i];

            if ($quote !== null) {
                // A backslash escape keeps the next character out of the quote decision, so
                // a path ending in one cannot close the string early.
                if ($char === '\\' && $i + 1 < $length) {
                    $out .= $char.$css[$i + 1];
                    $i++;

                    continue;
                }

                // A raw newline ends it. A CSS string cannot contain one — the spec calls it a
                // parse error — and without this rule a single stray apostrophe anywhere in the
                // file swallows every comment after it, which turns the strip back off exactly
                // where it matters: a directive commented out during a migration would read as
                // configuration again.
                if ($char === $quote || $char === "\n") {
                    $quote = null;
                }

                $out .= $char;

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $out .= $char;

                continue;
            }

            if ($char === '/' && ($css[$i + 1] ?? '') === '*') {
                $end = strpos($css, '*/', $i + 2);

                // An unterminated comment swallows the rest, which is what a CSS parser
                // does with it too — the file is broken either way, and reporting a missing
                // directive is the honest answer.
                if ($end === false) {
                    return $out;
                }

                $i = $end + 1;

                continue;
            }

            $out .= $char;
        }

        return $out;
    }

    /**
     * The argument of every `@source` directive that adds something for Tailwind to scan.
     *
     * Comments are left out, and so is every exclusion: `@source not '…';` is the opposite of
     * a source, and it names a path just the same.
     *
     * @return list<string>
     */
    public static function sourceArguments(string $css): array
    {
        preg_match_all('~@source\s+(?!not\b)([^;]*);~i', self::withoutComments($css), $directives);

        return $directives[1];
    }

    /**
     * Whether the stylesheet already points Tailwind at WireKit's templates.
     *
     * One directive has to name both: the package, and either its views (`…/wirekit/resources/
     * views/**`, or `../views/vendor/wirekit/**` once they are published) or one of its
     * per-component sources (`…/wirekit/resources/tailwind/button.txt`). The two words anywhere
     * in the file are not enough: a comment mentioning WireKit beside the pagination `@source`,
     * an exclusion of the package's tests, or an `@import` of its stylesheet each carry the name,
     * and none of them tells Tailwind to scan a template.
     */
    public static function includesWireKitTemplates(string $css): bool
    {
        foreach (self::sourceArguments($css) as $argument) {
            $lower = strtolower($argument);

            if (str_contains($lower, 'wirekit')
                && (str_contains($lower, 'views') || str_contains($lower, TailwindSources::DIRECTORY.'/'))) {
                return true;
            }
        }

        return false;
    }
}
