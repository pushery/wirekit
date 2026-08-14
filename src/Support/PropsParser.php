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
        return self::parseDirectiveSource($source, 'props');
    }

    /**
     * Parse Blade-source text and extract its `@aware([…])` block.
     *
     * `@aware` is Laravel's parent-to-child prop bridge, and for the question
     * "is this attribute a name the component knows?" its keys count exactly as
     * much as `@props` does: Blade accepts either on the tag. A component that
     * reads `announceErrors` through `@aware` is handed it legitimately, and
     * treating only `@props` as declared reports that legitimate call as a typo.
     *
     * Same shape, same tokenizer, same guarantees — only the directive name
     * differs, which is why this delegates rather than growing a second parser.
     * The house rule against a regex `@props` reader applies to `@aware` for the
     * same reason it applies to `@props`: a nested `config(...)` default carries
     * commas, and an inline comment carries anything at all.
     *
     * @return list<array{name: string, default: ?string, default_normalized: ?string, type_hint: ?string, comment: ?string, examples: list<string>}>
     */
    public static function parseAwareSource(string $source): array
    {
        return self::parseDirectiveSource($source, 'aware');
    }

    /**
     * Parse the `@aware([…])` block of a Blade file.
     *
     * @return list<array{name: string, default: ?string, default_normalized: ?string, type_hint: ?string, comment: ?string, examples: list<string>}>
     */
    public static function parseAwareBlade(string $bladePath): array
    {
        if (! is_file($bladePath)) {
            return [];
        }

        $contents = file_get_contents($bladePath);

        return $contents === false ? [] : self::parseAwareSource($contents);
    }

    /**
     * The shared reader behind `@props` and `@aware`.
     *
     * @return list<array{name: string, default: ?string, default_normalized: ?string, type_hint: ?string, comment: ?string, examples: list<string>}>
     */
    private static function parseDirectiveSource(string $source, string $directive): array
    {
        // Step 1 — locate the directive block with a narrow regex that ONLY
        // captures the outer wrapper. Multi-line + nested-bracket-aware
        // matching is left to the tokenizer, where it works correctly.
        // The regex requires balanced brackets at the OUTER level only;
        // anything inside is opaque to the regex.
        if (! preg_match('/@'.preg_quote($directive, '/').'\s*\(\s*\[/s', $source, $startMatch, PREG_OFFSET_CAPTURE)) {
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
     *
     * PHP'S OWN LEXER DECIDES WHAT IS CODE, and that is the whole point of this
     * shape. The question "which `]` closes this `[`" is only hard because a
     * bracket can appear inside a string, a comment, or a heredoc body and mean
     * nothing — and each of those is a separate rule with its own edge cases.
     *
     * This walked the characters by hand for a long time, and the history is the
     * argument: it was hardened three times, each time after a real defect.
     *
     *   - Strings first, because a `]` inside `'a]b'` is not a bracket.
     *   - Then comments, after an ODD apostrophe in a trailing comment — the one
     *     in `// each item's group` — flipped quote parity for the real code
     *     below it. The closing `]` was swallowed by a string that did not
     *     exist, the walk ran to EOF, and the component reported NO props at
     *     all. Downstream, its prop variables surfaced as required slots.
     *   - Then heredocs, whose bodies could leak every one of those states at
     *     once: a `/*` chasing a phantom `*​/` past the array, a stray `]`
     *     mis-counting depth, an odd quote flipping parity.
     *
     * Three hardenings for one question is the signal. `token_get_all()` already
     * answers all of it — a comment is one token, a heredoc is one token, a
     * string is one token — so the bracket depth is counted over things that are
     * certainly brackets, and there is no fourth special case waiting.
     *
     * The offsets come from the token stream rather than from a second scan: a
     * `<?php ` prefix is prepended so PHP lexes the source as code at all, and
     * its width is subtracted back out.
     */
    private static function extractBalancedBracketBody(string $source, int $openBracketIndex): ?string
    {
        if (($source[$openBracketIndex] ?? null) !== '[') {
            return null;
        }

        // Lexed from the OPENING BRACKET, not from the top of the file, and
        // that is a correctness requirement rather than a saving.
        //
        // A Blade file is prose and markup before it is code. Handed the whole
        // thing, the lexer reads an apostrophe in a comment above the component
        // — `{{-- the trigger's label --}}` — as the start of a string literal,
        // and the `[` of `@props([` disappears inside it. Depth then never
        // opens, the first `]` drives it negative, and the component reports NO
        // props at all. Measured: `dropdown/trigger` and `faq-item` did exactly
        // that. Starting at the bracket means nothing before it can be
        // misjudged, which is the one property the hand-rolled walk had for
        // free.
        //
        // Lexed PERMISSIVELY, without `TOKEN_PARSE`: what follows the array is
        // still Blade and will never parse, so demanding that it does rejects
        // every real input — measured at 28 of 30 cases failing. The lexer
        // groups strings, comments and heredocs correctly regardless, and that
        // grouping is the only thing this needs.
        $prefix = '<?php ';
        $tail = substr($source, $openBracketIndex);
        $offsets = self::bracketOffsets(token_get_all($prefix.$tail), strlen($prefix));
        $depth = 0;

        foreach ($offsets as [$char, $offset]) {
            $depth += $char === '[' ? 1 : -1;

            if ($depth === 0) {
                return substr($tail, 0, $offset + 1);
            }
        }

        return null;
    }

    /**
     * Source offsets of every `[` and `]` the lexer treated as a bracket.
     *
     * `token_get_all()` reports a line for array tokens and nothing at all for
     * single-character ones, so the offset is accumulated by walking the stream
     * and adding each token's own width. That works because the token texts
     * concatenate back to the exact source — including whitespace, comments and
     * heredoc bodies, which are tokens of their own.
     *
     * @param  list<array{0: int, 1: string, 2: int}|string>  $tokens
     * @return list<array{0: string, 1: int}>
     */
    private static function bracketOffsets(array $tokens, int $prefixLength): array
    {
        $offsets = [];
        $cursor = 0;

        foreach ($tokens as $token) {
            $text = is_array($token) ? $token[1] : $token;

            if ($text === '[' || $text === ']') {
                $offsets[] = [$text, $cursor - $prefixLength];
            }

            $cursor += strlen($text);
        }

        return $offsets;
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
