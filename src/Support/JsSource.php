<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * A JavaScript source read as text by the console checks.
 *
 * @internal
 */
final class JsSource
{
    /**
     * JavaScript source with every comment blanked out, line for line.
     *
     * The checks that read an application's scripts match plain text, so prose that names a
     * pattern would count as the thing itself, in both directions: a comment naming
     * `MutationObserver.disconnect()` would raise a finding, and a comment that happens to contain
     * `destroy() {` or an `import` line would make the file look like it has what it lacks.
     *
     * Contents are replaced with spaces rather than removed, and newlines are kept, so a reader
     * that looks at the lines around a match still finds each call next to its own context.
     *
     * Quote state is tracked so a `//` inside a string literal stays put: `'https://…'` would
     * otherwise swallow the rest of its line, and with it any call that shares it. A regex literal
     * carrying a quote (`/['"]/`) is the shape this does not model; it would flip the state and
     * blank too much or too little from there to the end of the file. Modeling it takes a real
     * tokenizer, and the cost of the gap is one file read wrongly by a heuristic check.
     */
    public static function withoutComments(string $source): string
    {
        $out = '';
        $length = strlen($source);
        $quote = null;
        $i = 0;

        while ($i < $length) {
            $char = $source[$i];
            $next = $i + 1 < $length ? $source[$i + 1] : '';

            if ($quote !== null) {
                $out .= $char;

                // A backslash escapes whatever follows, the closing quote included.
                if ($char === '\\' && $next !== '') {
                    $out .= $next;
                    $i += 2;

                    continue;
                }

                if ($char === $quote) {
                    $quote = null;
                }

                $i++;

                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $out .= $char;
                $i++;

                continue;
            }

            if ($char === '/' && $next === '/') {
                while ($i < $length && $source[$i] !== "\n") {
                    $out .= ' ';
                    $i++;
                }

                continue;
            }

            if ($char === '/' && $next === '*') {
                $out .= '  ';
                $i += 2;

                while ($i < $length && ! ($source[$i] === '*' && ($i + 1 < $length) && $source[$i + 1] === '/')) {
                    $out .= $source[$i] === "\n" ? "\n" : ' ';
                    $i++;
                }

                if ($i < $length) {
                    $out .= '  ';
                    $i += 2;
                }

                continue;
            }

            $out .= $char;
            $i++;
        }

        return $out;
    }
}
