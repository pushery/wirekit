<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * Token-stream-based parser for `@props([…])` blocks in Blade templates.
 *
 * Replaces two historical `preg_match`-based parsers that lived in
 * `ComponentRegistry::extractProps()` and `ExportJsonCommand::extractProps()`.
 * Both suffered from two well-documented bug classes:
 *
 *  1. **Look-ahead boundary break.** A prop default containing a comma
 *     inside a function-call argument list — `'variant' => config('x.y', null)`
 *     — got split at the inner comma. The prop became truncated AND a
 *     phantom "next prop" appeared.
 *  2. **Inline-comment leakage.** A trailing `// comment` block bled into
 *     the captured default value: `'name' => null, // doc` became
 *     `default = "null, // doc"`.
 *
 * Both classes vanish under PHP's own tokenizer. `token_get_all()`
 * understands string literals, function-call argument lists, nested
 * arrays / objects, heredoc / nowdoc, single-line and block comments —
 * every shape a `@props` block can legitimately contain. The narrow
 * extraction regex below ONLY captures the outer `@props(...)` wrapper;
 * everything inside the array body is handed off to the tokenizer.
 *
 * This class is THE source of truth for prop extraction. Every developer
 * (CLI commands, drift audits, future schema-export pipelines) routes
 * through here. A drift-audit test (PropsParserCallerDriftTest) blocks
 * new regex-based @props parsers from being added to `src/`.
 *
 * Return shape per entry:
 *   - `name` — prop name, string-key stripped of quotes.
 *   - `default` — raw default expression as it appears in source (e.g.
 *     `"config('wirekit.x.y', null)"`). Null when the prop has no
 *     `=> default` clause (positional-only @props).
 *   - `default_normalized` — same expression with whitespace collapsed
 *     and comments stripped (useful for stable string comparison).
 *   - `type_hint` — reserved for future @phpdoc-driven augmentation.
 *     Currently always null.
 *   - `comment` — the trailing same-line `// …` comment after the prop's
 *     comma, if present. The leading `//` is stripped and the value is
 *     trimmed. Null when no comment.
 *   - `examples` — `@example "value"` annotations extracted from the
 *     trailing comment. List of string examples; empty list when none.
 *     Each annotation must follow the shape `@example "..."` (double-
 *     quoted; backslash-escaped quotes supported). Multiple annotations
 *     in the same comment are all captured. Surfaces in the schema as
 *     `examples: ["1 md:2 lg:4"]` for props whose value-shape is
 *     non-obvious from the default alone (grid's `cols`, etc).
 */
final class PropsParser
{
    /**
     * Parse the `@props([…])` block from a Blade file on disk.
     *
     * @return list<array{name: string, default: ?string, default_normalized: ?string, type_hint: ?string, comment: ?string, examples: list<string>}>
     */
    public static function parseBlade(string $bladePath): array
    {
        if (! file_exists($bladePath)) {
            return [];
        }
        $contents = (string) file_get_contents($bladePath);
        if ($contents === '') {
            return [];
        }

        return self::parseSource($contents);
    }

    /**
     * Parse Blade-source text and extract its `@props([…])` block.
     *
     * Only the FIRST `@props(...)` directive is parsed. Blade itself
     * accepts a single `@props` per component; multiple directives are
     * a developer error rather than a supported pattern. If a future
     * use case demands multi-block parsing, extend the regex below to
     * `preg_match_all` and aggregate the results.
     *
     * @return list<array{name: string, default: ?string, default_normalized: ?string, type_hint: ?string, comment: ?string, examples: list<string>}>
     */
    public static function parseSource(string $source): array
    {
        // Step 1 — locate the @props block with a narrow regex that ONLY
        // captures the outer wrapper. Multi-line + nested-bracket-aware
        // matching is left to the tokenizer, where it works correctly.
        // The regex requires balanced brackets at the OUTER level only;
        // anything inside is opaque to the regex.
        if (! preg_match('/@props\s*\(\s*\[/s', $source, $startMatch, PREG_OFFSET_CAPTURE)) {
            return [];
        }
        $arrayBodyStart = $startMatch[0][1] + strlen($startMatch[0][0]) - 1;

        // Find the matching closing `]` by walking the source and
        // tracking bracket depth. This is more correct than a non-greedy
        // regex when the array contains nested arrays / function calls.
        $arrayBody = self::extractBalancedBracketBody($source, $arrayBodyStart);
        if ($arrayBody === null) {
            return [];
        }

        // Step 2 — wrap the captured array literal as valid PHP source
        // and tokenize.
        $phpSource = "<?php\n\$_props_parser_arr = {$arrayBody};\n";
        $tokens = token_get_all($phpSource);

        return self::walkTokens($tokens);
    }

    /**
     * Walk source from `$openBracketIndex` (must point at `[`) and return
     * the substring including the brackets `[…]` with matching depth.
     */
    private static function extractBalancedBracketBody(string $source, int $openBracketIndex): ?string
    {
        if (($source[$openBracketIndex] ?? null) !== '[') {
            return null;
        }
        $depth = 0;
        $i = $openBracketIndex;
        $len = strlen($source);
        $inString = null;  // null | "'" | '"'
        $escape = false;

        while ($i < $len) {
            $ch = $source[$i];

            // String-aware: brackets inside string literals do not affect depth.
            if ($inString !== null) {
                if ($escape) {
                    $escape = false;
                } elseif ($ch === '\\') {
                    $escape = true;
                } elseif ($ch === $inString) {
                    $inString = null;
                }
                $i++;

                continue;
            }

            // Comment-aware (only when NOT inside a string): a quote or bracket
            // inside a comment is NOT code. We skip the whole comment span so
            // its characters never toggle string state or bracket depth. Before
            // this, an ODD quote char inside a comment — e.g. the apostrophe in
            // `// Blade's compiler` or a trailing `// each item's group` — flipped
            // quote-parity for the real prop code below, swallowing the closing
            // `]` inside a phantom string. The walker then ran off the end and
            // returned null → parseSource returned [] → the component's props
            // were reported as (none) and its prop variables leaked into the
            // slot list downstream (the export-json props→slots misfire). The
            // skipped text still lands in the returned substring, where
            // token_get_all() tokenizes comments correctly.
            $next = $source[$i + 1] ?? '';
            if ($ch === '/' && $next === '/') {
                // `//` line comment → skip to the end of the line (or EOF).
                $nl = strpos($source, "\n", $i + 2);
                $i = $nl === false ? $len : $nl + 1;

                continue;
            }
            if ($ch === '#' && $next !== '[') {
                // `#` line comment. `#[` would begin a PHP attribute, which
                // cannot appear inside a @props array literal — excluded anyway
                // to stay faithful to PHP's lexer.
                $nl = strpos($source, "\n", $i + 1);
                $i = $nl === false ? $len : $nl + 1;

                continue;
            }
            if ($ch === '/' && $next === '*') {
                // `/* … */` block comment → skip to the closing `*/` (or EOF).
                $close = strpos($source, '*/', $i + 2);
                $i = $close === false ? $len : $close + 2;

                continue;
            }

            // Heredoc / nowdoc: the body is OPAQUE — `/*`, `//`, `#`, quotes, and
            // brackets inside it are literal text, not code. The walker is
            // otherwise only string-aware (it toggles on '/"), so a `<<<LABEL`
            // body could leak state: a `/*` would chase a phantom `*/` past the
            // array's `]`, a stray `]` would mis-count depth, an odd quote would
            // flip parity. Recognize the opener, capture the label, and skip the
            // whole body to its closing-label line — mirroring how token_get_all()
            // treats a heredoc as ONE opaque token. (The skipped text still lands
            // in the returned substring, where token_get_all() re-parses it.)
            if ($ch === '<' && substr($source, $i, 3) === '<<<'
                && preg_match('/<<<[ \t]*([\'"]?)([A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)\1\r?\n/A', $source, $hm, 0, $i)) {
                $label = $hm[2];
                // PHP 7.3+ allows the closing label to be indented; it sits at a
                // line start and is followed by a non-identifier boundary.
                if (preg_match('/^[ \t]*'.preg_quote($label, '/').'(?![A-Za-z0-9_\x80-\xff])/m', $source, $cm, PREG_OFFSET_CAPTURE, $i + strlen($hm[0]))) {
                    $i = $cm[0][1] + strlen($cm[0][0]);
                } else {
                    $i = $len; // malformed (no closing label) — skip to EOF
                }

                continue;
            }

            if ($ch === "'" || $ch === '"') {
                $inString = $ch;
                $i++;

                continue;
            }
            if ($ch === '[') {
                $depth++;
            } elseif ($ch === ']') {
                $depth--;
                if ($depth === 0) {
                    return substr($source, $openBracketIndex, $i - $openBracketIndex + 1);
                }
            }
            $i++;
        }

        return null;
    }

    /**
     * Walk the PHP token stream and extract array entries.
     *
     * @param  list<array{0: int, 1: string, 2: int}|string>  $tokens
     * @return list<array{name: string, default: ?string, default_normalized: ?string, type_hint: ?string, comment: ?string, examples: list<string>}>
     */
    private static function walkTokens(array $tokens): array
    {
        // Locate the array's opening `[`.
        $arrayStart = null;
        $len = count($tokens);
        foreach ($tokens as $i => $token) {
            if ($token === '[') {
                $arrayStart = $i;
                break;
            }
        }
        if ($arrayStart === null) {
            return [];
        }

        $entries = [];
        $current = self::freshEntry();
        $depth = 0;  // tracks nesting INSIDE the array body
        $i = $arrayStart + 1;

        while ($i < $len) {
            $token = $tokens[$i];

            // Closing `]` at outer depth = end of array.
            if ($token === ']' && $depth === 0) {
                if ($current['name'] !== null) {
                    $entries[] = self::finalizeEntry($current);
                }
                break;
            }

            // Nesting tracker. Note: T_CURLY_OPEN / T_DOLLAR_OPEN_CURLY_BRACES
            // are interpolation tokens inside double-quoted strings; the
            // tokenizer balances them with T_END_HEREDOC / `}` etc., but
            // they don't appear at array-top level for @props defaults.
            if (is_string($token)) {
                if ($token === '(' || $token === '[' || $token === '{') {
                    if ($current['state'] === 'expect-value') {
                        $current['default_tokens'][] = $token;
                    }
                    $depth++;
                    $i++;

                    continue;
                }
                if ($token === ')' || $token === '}') {
                    if ($current['state'] === 'expect-value') {
                        $current['default_tokens'][] = $token;
                    }
                    $depth--;
                    $i++;

                    continue;
                }
                if ($token === ']') {
                    // Inner closing bracket — captured into default value.
                    if ($current['state'] === 'expect-value') {
                        $current['default_tokens'][] = $token;
                    }
                    $depth--;
                    $i++;

                    continue;
                }
            }

            // Top-level comma = entry boundary.
            if ($token === ',' && $depth === 0) {
                if ($current['name'] !== null) {
                    // Capture any trailing inline comment on the same
                    // line (within the same source line as the comma).
                    $current['comment'] = self::peekTrailingComment($tokens, $i + 1, $newIndex);
                    if ($newIndex !== null) {
                        $i = $newIndex;
                    }
                    $entries[] = self::finalizeEntry($current);
                }
                $current = self::freshEntry();
                $i++;

                continue;
            }

            // Skip whitespace + standalone comments BETWEEN entries
            // (state = expect-key). Inside a value, whitespace is part
            // of the default expression and is preserved.
            if (is_array($token) && $token[0] === T_WHITESPACE) {
                if ($current['state'] === 'expect-value') {
                    $current['default_tokens'][] = $token;
                }
                $i++;

                continue;
            }
            if (is_array($token) && ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT)) {
                if ($current['state'] === 'expect-value') {
                    // Tokens inside the value's expression — preserve in
                    // the raw default; strip from normalized.
                    $current['default_tokens'][] = $token;
                }
                $i++;

                continue;
            }

            // Key — must be a string literal (single- or double-quoted).
            if ($current['state'] === 'expect-key' && is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
                $current['name'] = trim($token[1], "'\"");
                $current['state'] = 'expect-arrow';
                $i++;

                continue;
            }

            // `=>` between key and value.
            if ($current['state'] === 'expect-arrow' && is_array($token) && $token[0] === T_DOUBLE_ARROW) {
                $current['state'] = 'expect-value';
                $i++;

                continue;
            }

            // Value tokens.
            if ($current['state'] === 'expect-value') {
                $current['default_tokens'][] = $token;
            }
            $i++;
        }

        return $entries;
    }

    /**
     * Look ahead from `$startIndex` for a trailing same-line `//` or `/* … *\/`
     * comment. Sets `$endIndex` to the comment's token position if found,
     * else null.
     *
     * @param  list<array{0: int, 1: string, 2: int}|string>  $tokens
     */
    private static function peekTrailingComment(array $tokens, int $startIndex, ?int &$endIndex): ?string
    {
        $endIndex = null;
        $len = count($tokens);
        $j = $startIndex;
        while ($j < $len) {
            $token = $tokens[$j];
            if (is_array($token) && $token[0] === T_WHITESPACE) {
                // A newline ends the same-line lookahead.
                if (str_contains($token[1], "\n")) {
                    return null;
                }
                $j++;

                continue;
            }
            if (is_array($token) && ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT)) {
                $endIndex = $j;

                return self::cleanComment($token[1]);
            }

            // Anything else on the same line means no comment here.
            return null;
        }

        return null;
    }

    /**
     * @return array{name: ?string, default_tokens: list<mixed>, comment: ?string, state: string}
     */
    private static function freshEntry(): array
    {
        return [
            'name' => null,
            'default_tokens' => [],
            'comment' => null,
            'state' => 'expect-key',
        ];
    }

    /**
     * @param  array{name: ?string, default_tokens: list<mixed>, comment: ?string, state: string}  $current
     * @return array{name: string, default: ?string, default_normalized: ?string, type_hint: ?string, comment: ?string, examples: list<string>}
     */
    private static function finalizeEntry(array $current): array
    {
        $name = (string) $current['name'];
        $default = null;
        $defaultNormalized = null;

        if ($current['default_tokens'] !== []) {
            $raw = '';
            $normalized = '';
            foreach ($current['default_tokens'] as $token) {
                $text = is_array($token) ? $token[1] : $token;
                $raw .= $text;

                // Build the normalized form: strip comments, collapse
                // whitespace to single spaces.
                if (is_array($token) && ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT)) {
                    continue;
                }
                if (is_array($token) && $token[0] === T_WHITESPACE) {
                    if ($normalized !== '' && ! str_ends_with($normalized, ' ')) {
                        $normalized .= ' ';
                    }

                    continue;
                }
                $normalized .= $text;
            }
            $default = trim($raw);
            if ($default === '') {
                $default = null;
            }
            $normalized = trim(preg_replace('/\s+/', ' ', $normalized) ?? '');
            $defaultNormalized = $normalized === '' ? null : $normalized;
        }

        return [
            'name' => $name,
            'default' => $default,
            'default_normalized' => $defaultNormalized,
            // `type_hint` is reserved for future @phpdoc-driven augmentation
            // (e.g. parsing `@var bool $foo` from preceding docblocks). The
            // field exists today so the return shape is stable from v2.0.0.
            'type_hint' => null,
            'comment' => $current['comment'],
            'examples' => self::extractExamples($current['comment']),
        ];
    }

    /**
     * Extract every `@example "value"` annotation from a trailing comment.
     *
     * Pattern: `@example "..."` — double-quoted, backslash-escaped quotes
     * supported. Annotations are documented in the per-component
     * `@props([...])` blocks for props whose accepted value-shape is
     * non-obvious from the default alone (grid's `cols` accepts
     * `"1 md:2 lg:4"` Tailwind-style breakpoint-tokens, for example).
     *
     * Returns an empty list when no comment, or when the comment has no
     * `@example` annotations.
     *
     * @return list<string>
     */
    private static function extractExamples(?string $comment): array
    {
        if ($comment === null || $comment === '') {
            return [];
        }
        // Match every `@example "..."` with non-greedy capture + backslash-
        // escape support. The `(?:\\.|[^"\\])*` pattern accepts any
        // character except an unescaped quote OR any backslash-escaped
        // character (including escaped quotes).
        if (! preg_match_all('/@example\s+"((?:\\\\.|[^"\\\\])*)"/', $comment, $matches)) {
            return [];
        }

        // Un-escape the captured strings: backslash-escaped quotes become
        // literal quotes (`\"` → `"`).
        return array_map(
            fn (string $raw): string => str_replace(['\\"', '\\\\'], ['"', '\\'], $raw),
            $matches[1]
        );
    }

    /**
     * Strip leading `//` or `/* … *\/` markers and trim.
     */
    private static function cleanComment(string $raw): string
    {
        $raw = trim($raw);
        if (str_starts_with($raw, '//')) {
            return trim(substr($raw, 2));
        }
        if (str_starts_with($raw, '#')) {
            return trim(substr($raw, 1));
        }
        if (str_starts_with($raw, '/*')) {
            $inner = substr($raw, 2);
            if (str_ends_with($inner, '*/')) {
                $inner = substr($inner, 0, -2);
            }

            return trim($inner);
        }

        return $raw;
    }
}
