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
 * through PropsParser. Direct regex scanning of Blade source for this data is a
 * build failure: a drift guard refuses a new regex against the props directive
 * anywhere in `src/`, and allowlists only PropsParser itself.
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
     * `trigger` slot, and the plain extraction alone would report them as
     * default-slot only, hiding the requirement.
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
     * @param  list<string>  $additionalExcludes  Extra names to drop from the
     *                                            detected slot set. Used by the
     *                                            JSON-manifest exporter to pass
     *                                            a class-based component's
     *                                            public-property names, which
     *                                            appear as bare `{{ $name }}`
     *                                            references in the template
     *                                            but are NOT real slots.
     * @return list<array{name: string, required: bool}>
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

        /*
         * Second optionality signal: `$name ??`.
         *
         * `{{ $bulkActions ?? '' }}` says "optional" as plainly as `@isset` does — it names a
         * default for the case where the slot is absent. Read as a bare reference alone, it
         * came out MANDATORY, and that is what shipped to components.json and the MCP
         * catalog: three slots a developer was told to supply, one of which (`swap`'s `off`)
         * falls back to the default slot by design.
         *
         * This signal only downgrades; it never introduces a name. Most names that occur only
         * inside a `??` are props and locals, not slots, and collecting them the way `isset`
         * names are collected would invent optional slots by the hundred. `isset($x)` on a
         * template is almost always a slot-presence check; `??` is ordinary expression
         * punctuation.
         */
        $coalescedNames = [];

        if (preg_match_all('/\$([a-zA-Z][a-zA-Z0-9]*)\s*\?\?/', $contents, $coalesced)) {
            foreach ($coalesced[1] as $name) {
                $coalescedNames[$name] = null;
            }
        }

        // A `{{ … }}` inside an `@php` block is not output — it is characters in a PHP
        // string, not an expression.
        //
        // `reading-bookmark` throws an exception whose MESSAGE shows the developer how to
        // call it: `key="article-{{ $post->slug }}"`. That documentation string made `post`
        // a required slot in every artifact derived from this parser — `components.json`,
        // the api-map, the MCP catalog an assistant reads.
        //
        // And only those sequences, not the whole block. `faq-item` reads its own content as
        // `$answerHtml = trim($slot->toHtml())` and nowhere else, so dropping the block would
        // stop the component's whole body being a slot. A `{{ }}` in PHP can only be a string;
        // a `$name->method()` there is ordinary code and may well be the only reference a slot
        // has.
        $output = (string) preg_replace_callback(
            '/@php\b.*?@endphp/s',
            static fn (array $m): string => (string) preg_replace('/\{\{.*?\}\}|\{!!.*?!!\}/s', '', $m[0]),
            $contents,
        );

        // Bare references: `{{ $name }}`, `{!! $name !!}`, `$name->method()`,
        // `$name->isEmpty()`. These signal a hard dependency — if the
        // developer doesn't supply the slot, the component errors.
        if (preg_match_all('/\{\{\s*\$([a-zA-Z][a-zA-Z0-9]*)\b/', $output, $bareMatches)) {
            foreach ($bareMatches[1] as $name) {
                $bareNames[$name] = null;
            }
        }
        if (preg_match_all('/\{!!\s*\$([a-zA-Z][a-zA-Z0-9]*)\b/', $output, $rawMatches)) {
            foreach ($rawMatches[1] as $name) {
                $bareNames[$name] = null;
            }
        }
        // Method calls on slot vars — e.g. `$slot->isEmpty()`,
        // `$trigger->toHtml()`.
        if (preg_match_all('/\$([a-zA-Z][a-zA-Z0-9]*)->[a-zA-Z]/', $output, $methodMatches)) {
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
        $loopLocals = self::extractLoopVariablesFromSource($contents);

        $exclude = array_unique(array_merge($propNames, $reserved, $phpLocals, $loopLocals, $additionalExcludes));

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
            // explicit guard wins. A `??` beside it is the same statement in
            // another spelling, so it wins too. Otherwise it's required.
            if (! isset($records[$name])) {
                $records[$name] = [
                    'name' => $name,
                    'required' => ! array_key_exists($name, $coalescedNames),
                ];
            }
        }

        return array_values($records);
    }

    /**
     * The parenthesized head of each named Blade directive, read with balanced parentheses.
     *
     * @param  list<string>  $directives
     * @return list<string>
     */
    private static function directiveHeads(string $contents, array $directives): array
    {
        $heads = [];
        $names = implode('|', array_map(fn (string $d): string => preg_quote($d, '/'), $directives));

        if (! preg_match_all('/@(?:'.$names.')\s*\(/', $contents, $starts, PREG_OFFSET_CAPTURE)) {
            return $heads;
        }

        foreach ($starts[0] as [$match, $offset]) {
            $i = $offset + strlen($match);   // first character inside the opening paren
            $depth = 1;
            $length = strlen($contents);

            while ($i < $length && $depth > 0) {
                $char = $contents[$i];

                if ($char === '(') {
                    $depth++;
                } elseif ($char === ')') {
                    $depth--;
                }

                $i++;
            }

            // An unbalanced head means the file is malformed; take nothing rather than
            // the rest of the file, which would bind every variable after it.
            if ($depth === 0) {
                $heads[] = substr($contents, $offset + strlen($match), $i - 1 - ($offset + strlen($match)));
            }
        }

        return $heads;
    }

    /**
     * Variables a Blade LOOP binds, which are not slots and never were.
     *
     * The exclusion list drops props, reserved names and `@php` locals, and loop bindings join
     * them here: every `@for($i = …)` and `@foreach($xs as $x)` would otherwise leave its
     * variable behind as a slot, and because a bare `{{ $i }}` is exactly the shape that marks a
     * slot required, as a hard dependency. That is not internal: `slotsOf()` feeds
     * `ExportJsonCommand` (the publicly served components.json and api-map.json) and
     * `McpCatalog` (what an AI assistant reads), so an assistant asking what `otp-input` accepts
     * would be told it takes a slot called `i`.
     *
     * Four directives bind, and all four are matched rather than only the common two: a
     * `@forelse` that went unhandled would reintroduce the bug for exactly the components
     * that render an empty state.
     *
     * @return list<string>
     */
    private static function extractLoopVariablesFromSource(string $contents): array
    {
        $names = [];

        /*
         * `@foreach($xs as $k => $v)` and `@foreach($xs as $v)`, plus @forelse which has
         * the same head. Both sides of a `=>` are bound.
         *
         * The head is read with balanced parentheses, not to the first `)`. A non-greedy
         * `\((.*?)\)` stops inside the collection expression the moment it contains one --
         * a cast or a method call is enough:
         *
         *     @foreach((array) $shortcut as $key)                  → head cut to `(array`
         *     @foreach($p->linkCollection()->slice(1, -1) as $link) → head cut to `$p->linkCollection`
         *
         * Neither cut half contains ` as `, so nothing would be bound and the loop variable
         * would survive into the slot list as a required slot, `pagination::link` among them.
         */
        foreach (self::directiveHeads($contents, ['foreach', 'forelse']) as $head) {
            if (preg_match_all('/\$([a-zA-Z][a-zA-Z0-9_]*)/', (string) strstr($head, ' as ') ?: '', $bound)) {
                foreach ($bound[1] as $name) {
                    $names[] = $name;
                }
            }
        }

        // `@for($i = 0; $i < $n; $i++)` — the initializer binds; the condition and the step
        // only read, so the first clause is the one to scan.
        if (preg_match_all('/@for\s*\((.*?);/s', $contents, $inits)) {
            foreach ($inits[1] as $init) {
                self::collectAssignmentsFrom($init, $names);
            }
        }

        // `@while($row = next($rows))` — an assignment in the condition binds too.
        if (preg_match_all('/@while\s*\((.*?)\)/s', $contents, $conds)) {
            foreach ($conds[1] as $cond) {
                self::collectAssignmentsFrom($cond, $names);
            }
        }

        return array_values(array_unique($names));
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
     * Scan a PHP-source string for assignments and append each captured name to the
     * `$locals` accumulator.
     *
     * Three shapes. `$foo = …` is the common one; `foreach (… as $item)` binds a name the same
     * way; and list destructuring (`[$a, $b, $c] = match (…)` or `list($a, $b) = …`) binds
     * several at once. A name bound by destructuring would otherwise be reported as a required
     * slot, and that answer ships: `stat` would advertise `trendColor`, `trendIcon` and
     * `trendLabel` as slots a developer must fill, in `components.json`, in the api-map and in
     * the MCP catalog a coding assistant reads.
     *
     * Nested destructuring (`[[$a, $b], $c] = …`) and keyed destructuring (`['x' => $a] = …`)
     * are not covered. Neither shape occurs in this catalog, and a pattern for them would be
     * guesswork against no case.
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

        // Closure and function parameters bind a name too, and this is the same class of
        // miss the docblock above records for destructuring — a name bound without an `=`.
        //
        // `fn (?FontPreset $preset) => …` inside an `@php` block made `fonts` advertise
        // `preset` as a slot a developer must fill. It is not a slot; it is the argument of
        // an arrow function two lines away, and that answer ships in `components.json`, the
        // api-map and the MCP catalog an assistant reads.
        //
        // The parameter LIST rather than the whole body, so a call like `foo($bar)` — which
        // uses a name rather than binding one — is untouched.
        if (preg_match_all('/\b(?:function|fn)\s*\(([^)]*)\)/', $body, $signatures)) {
            foreach ($signatures[1] as $params) {
                if (preg_match_all('/\$([a-zA-Z][a-zA-Z0-9]*)/', $params, $names)) {
                    foreach ($names[1] as $name) {
                        $locals[] = $name;
                    }
                }
            }
        }

        // And `use ($x)` on a closure, which binds the name into its scope the same way.
        if (preg_match_all('/\buse\s*\(([^)]*)\)/', $body, $uses)) {
            foreach ($uses[1] as $params) {
                if (preg_match_all('/\$([a-zA-Z][a-zA-Z0-9]*)/', $params, $names)) {
                    foreach ($names[1] as $name) {
                        $locals[] = $name;
                    }
                }
            }
        }
        // foreach (`@foreach($items as $item)`) declares $item locally;
        // same risk class. Catch the obvious shape.
        if (preg_match_all('/foreach\s*\(\s*[^\s]+\s+as\s+\$([a-zA-Z][a-zA-Z0-9]*)/', $body, $foreachMatches)) {
            foreach ($foreachMatches[1] as $name) {
                $locals[] = $name;
            }
        }

        // List destructuring binds several names in one statement: `[$a, $b] = …` and the
        // older `list($a, $b) = …`. The single-assignment pattern above cannot see them —
        // it anchors on a `$name` immediately followed by `=`, and here the `=` sits after
        // the closing bracket.
        if (preg_match_all('/(?:\blist\s*\(|\[)\s*((?:\$[a-zA-Z][a-zA-Z0-9]*\s*,\s*)*\$[a-zA-Z][a-zA-Z0-9]*)\s*(?:\)|\])\s*=(?!=)/', $body, $listMatches)) {
            foreach ($listMatches[1] as $group) {
                foreach (explode(',', $group) as $variable) {
                    $locals[] = ltrim(trim($variable), '$');
                }
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
     * Does this source set `$attribute` on a line that ALWAYS renders?
     *
     * The question is deliberately about the unconditional case, because the only caller
     * makes an absolute claim with the answer: `StrictnessGate` tells a developer their own
     * scope "never exists" because the component sets one. That sentence is true only when
     * the component sets it every time — and a plain regex over the source cannot tell the
     * difference, because it does not see conditions.
     *
     * A component that sets `x-data` only inside a condition would otherwise produce a warning
     * telling a developer to change working code: `card` is such a case, where the `x-data`
     * belongs to a debug warning that fires only when a card is composed wrongly. `x-init` has
     * the same shape.
     *
     * This under-reports on purpose, and that is the trade. A component that sets the
     * attribute only inside a condition that happens to be TRUE at runtime does collide, and
     * this answers "no". The alternative asserts a collision that mostly is not there. A hint
     * that is silent in a rare real case costs a developer nothing; one that tells them to
     * rewrite correct code costs them the trust they had in every other hint.
     *
     * The exact answer would need the component's own runtime state and would have to be
     * wired per component. That is the right shape for a component that genuinely wants it,
     * and the wrong price for every component that does not.
     */
    public static function setsAttributeUnconditionally(string $contents, string $attribute): bool
    {
        // Comments and `@php` blocks are stripped first: both can contain the very text this
        // scans for, and a `{{-- @if --}}` would otherwise open a block that never closes,
        // after which the whole rest of the file reads as conditional.
        $contents = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $contents);
        $contents = (string) preg_replace('/@php\b.*?@endphp\b/s', '', $contents);

        $needle = '/\s'.preg_quote($attribute, '/').'\s*=/';

        $depth = 0;

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            // The depth BEFORE this line is what governs the line itself — an `@if` and the
            // attribute on the same line means the attribute is inside it, and an `@endif`
            // on the same line does not retroactively free what came before it.
            if ($depth === 0 && preg_match($needle, $line) === 1) {
                return true;
            }

            $depth += self::conditionalDepthDelta($line);
        }

        return false;
    }

    /**
     * How much one line changes the conditional nesting depth.
     *
     * `@else`, `@elseif`, `@case`, `@default` and the bare `@empty` of a `@forelse` are
     * NOT openers — they continue a block someone else opened and have no `@end…` of their
     * own. Counting them opens a level that never closes, so after the first `@if … @else …
     *
     * @endif` the depth never returns to zero and every later attribute in the file reads as
     * conditional. That mistake is silent and it inflates: it reported 22 exclusively-
     * conditional components here where there are 18.
     *
     * `@empty` is the ambiguous one and is split by its parentheses: `@empty($x)` is a block
     * with an `@endempty`, the bare `@empty` inside a `@forelse` is not.
     */
    private static function conditionalDepthDelta(string $line): int
    {
        $withArgs = 'if|unless|isset|switch|forelse|foreach|for|while|auth|guest|env|production'
            .'|hasSection|sectionMissing|can|cannot|canany|error|once|empty';

        $opens = preg_match_all('/@('.$withArgs.')\s*\(/', $line);

        // These four are valid with no argument list at all.
        $opens += preg_match_all('/@(auth|guest|once|production)\b(?!\s*\()/', $line);

        $closes = preg_match_all(
            '/@(endif|endunless|endisset|endempty|endswitch|endforelse|endforeach|endfor'
            .'|endwhile|endauth|endguest|endenv|endproduction|endcan|endcannot|endcanany'
            .'|enderror|endonce)\b/',
            $line
        );

        return $opens - $closes;
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
     * Blank every comment in a Blade file while leaving every byte offset where it was.
     *
     * For a scanner that reads markup, a comment is the one place text appears that the page
     * never gets — and it is exactly where the markup a scanner looks for tends to be quoted,
     * because that is where somebody explained the rule. `qr-code` says in a `//` comment that
     * "the accessible name lives on the wrapper `<div role="img">` above it", and the a11y
     * linter read that sentence as an unnamed `role="img"` and reported an ERROR over two
     * wrappers that both carry `aria-label`.
     *
     * Blanked, not removed, and that is the whole design. Every caller reports a line
     * number, and most compute it from a byte offset with `substr_count(…, "\n")`. Deleting a
     * comment shifts everything after it, so a scanner fed a stripped file would report real
     * findings at the wrong lines — trading a false positive for a wrong address, which is
     * worse because it looks right. Each matched character becomes a space, each newline stays
     * a newline, and the string keeps its exact length.
     *
     * Three shapes, because all three hold prose: Blade comments, HTML comments, and the `//`,
     * `#` and block comments inside a `@php` block. The PHP half goes through `token_get_all()`
     * rather than a pattern, so a `//` inside a string literal is left alone.
     */
    public static function blankComments(string $contents): string
    {
        $blank = static fn (string $text): string => (string) preg_replace('/[^\n]/u', ' ', $text);

        $contents = (string) preg_replace_callback(
            '/\{\{--.*?--\}\}|<!--.*?-->/s',
            static fn (array $m): string => $blank($m[0]),
            $contents,
        );

        return (string) preg_replace_callback(
            '/@php\b.*?@endphp\b/s',
            static function (array $m) use ($blank): string {
                $body = $m[0];

                // `token_get_all` needs a real open tag to tokenize at all; the sentinel is the
                // same length as the directive it replaces, so every offset inside the body
                // survives the round trip.
                $php = '<?php     '.mb_substr($body, mb_strlen('@php'), mb_strlen($body) - mb_strlen('@php') - mb_strlen('@endphp'));

                foreach (array_reverse(token_get_all($php, TOKEN_PARSE)) as $token) {
                    if (! is_array($token) || ! in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                        continue;
                    }

                    $offset = mb_strpos($body, $token[1], 0);

                    if ($offset === false) {
                        continue;
                    }

                    $body = mb_substr($body, 0, $offset).$blank($token[1]).mb_substr($body, $offset + mb_strlen($token[1]));
                }

                return $body;
            },
            $contents,
        ) ?: $contents;
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
     * Every tag in a Blade source string, with the boundaries of its attribute region.
     *
     * This is the ONE tag walk. Everything in this package that needs to know where a tag
     * starts, where its attributes end, or what names it carries goes through here — the
     * usage extractor below, `wirekit:csp-audit`, `wirekit:show --validate-against`. The
     * reason is not tidiness: the same defect has now been found three times in three
     * hand-written scanners, because each one re-learned Blade from scratch and stopped at
     * a different depth. A walk that lives in one place gets hardened once.
     *
     * Two things make Blade markup harder to walk than it looks, and both were found by
     * pointing a scanner at real templates rather than at fixtures:
     *
     *   1. `>` is ordinary inside a value. `x-show="count > 3"` has one and a Blade array
     *      prop has several, so any rule that ends a tag at a `>` loses the beat and reads
     *      every later attribute as if it were on the next element. Quote state is tracked
     *      instead.
     *   2. **Blade's own constructs are not markup, and they carry apostrophes.** A comment
     *      between attributes, an `{{ $attributes->merge([…]) }}` in the same position, an
     *      `@php` block whose strings contain `<svg …>`, an `@if(str_contains($slot, '…'))`
     *      — all of them are PHP or prose, and a walk that reads them as markup opens a
     *      quoted value on an English genitive that closes at the next apostrophe ANYWHERE
     *      in the file, or off the end of it. So they are stepped over whole.
     *
     * Boundaries are returned, never values. The question a value answers is different for
     * every caller — an Alpine expression, a prop, a class list — and returning values here
     * would make this the second, weaker parser for all three of them.
     *
     * `terminator` says how the walk left the tag, because the three endings are not the
     * same fact. `>` closed it; `<` means another element started inside it, so the tag
     * never closed and whatever was collected is an artifact; `null` means the file ended
     * mid-tag. Callers decide what to do with the last two — discarding is usually right,
     * and it is always better than reporting plausible output nobody can tell apart.
     *
     * @return list<array{name: string, isComponent: bool, attributes: list<string>, start: int, attrStart: int, attrEnd: int, terminator: string|null}>
     */
    public static function tagsFromSource(string $contents): array
    {
        $tags = [];
        $length = strlen($contents);
        $cursor = 0;

        while (($start = self::nextTagOpener($contents, $cursor)) !== null) {
            $cursor = $start + 1;

            // A tag name, so `</div>`, `<!-- … -->` and a bare `<` in prose are all passed
            // over rather than walked as tags.
            if (preg_match('/\G([a-zA-Z][\w:.-]*)/', $contents, $nameMatch, 0, $cursor) !== 1) {
                continue;
            }

            $name = $nameMatch[1];
            $cursor += strlen($name);
            $attrStart = $cursor;

            $attributes = [];
            $token = '';
            $quote = null;
            $terminator = null;

            while ($cursor < $length) {
                $char = $contents[$cursor];

                // Deliberately NOT gated on being outside a quoted value. A Blade
                // construct inside an attribute value is still a Blade construct,
                // and its own quotes are its own: `title="{{ __('a.b') }}"` is one
                // value, not a value that ends at the apostrophe. Skipping the
                // whole construct is what makes that true — the walk never sees
                // the inner quotes, so they cannot close anything.
                //
                // Reading them was the reported defect: the PHP that followed got
                // read as attribute names, and `wirekit:doctor:props` inherited
                // each one as a prop the component never declared.
                $past = self::skipNonMarkup($contents, $cursor);

                if ($past !== null) {
                    $cursor = $past;

                    continue;
                }

                if ($quote !== null) {
                    // A backslash escapes the next character, so `\"` inside a
                    // double-quoted value does NOT close it. Without this, a real
                    // documented snippet — `:sort-action="\"sortBy('{$field}')\""` on
                    // `table.th` — ended its value at the first `\"`, and the walk then
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
                    $terminator = '>';

                    break;
                }

                // An unquoted `<` means this tag never closed: in well-formed Blade a `<`
                // inside a tag is always inside a quoted value, so an unquoted one starts a
                // NEW element. Without this bound the walk keeps consuming whatever follows
                // as attribute names, and the failure is the expensive kind — it does not
                // throw, it returns plausible-looking output.
                if ($char === '<') {
                    $terminator = '<';

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

            $tags[] = [
                'name' => $name,
                // `<x-…>` and `<livewire:…>` are both component tags. The distinction is
                // what tells a bare `:` apart from an Alpine shorthand, so it belongs to
                // the tag, not the caller.
                //
                // The `livewire:` half was missing, and the omission was invisible because
                // the rule's own test used an `<x-…>` fixture — the rule looked covered
                // while its sibling spelling went unexamined. What that cost is measurable:
                // in a real application, 12 of 13 false findings were a bare `:` on a
                // `<livewire:…>` tag, typically a key assembled in PHP.
                'isComponent' => preg_match('/^(?:x[-:]|livewire:)/i', $name) === 1,
                'attributes' => $attributes,
                'start' => $start,
                'attrStart' => $attrStart,
                'attrEnd' => $cursor,
                'terminator' => $terminator,
            ];

            // Never past the end. A cursor running to `strlen + 1` on a malformed file would
            // make the audit command die with a raw `ValueError` out of its extraction
            // phase — before it could report anything at all.
            $cursor = $terminator === '>' ? $cursor + 1 : $cursor;
        }

        return $tags;
    }

    /**
     * The offset of the next `<` that is genuinely markup, or null when there is none.
     *
     * The search has to step over Blade's constructs for the same reason the tag walk does:
     * a documented example inside `{{-- … --}}`, or an `<svg …>` built as a PHP string in an
     * `@php` block, is not an element. Read as one, a comment becomes a live finding against
     * a line the browser never sees, and a PHP string becomes a tag that never closes.
     */
    private static function nextTagOpener(string $contents, int $cursor): ?int
    {
        $length = strlen($contents);

        while ($cursor < $length) {
            // Jump to the next character that could begin markup or a Blade construct;
            // everything between is prose and costs nothing to skip in one step.
            $cursor += strcspn($contents, '<{@', $cursor);

            if ($cursor >= $length) {
                return null;
            }

            if ($contents[$cursor] === '<') {
                return $cursor;
            }

            $past = self::skipNonMarkup($contents, $cursor);
            $cursor = $past ?? $cursor + 1;
        }

        return null;
    }

    /**
     * If a Blade construct starts at `$cursor`, the offset just past it — otherwise null.
     *
     * Everything handled here is PHP or prose that happens to live in a Blade file, and the
     * walk has to cross it in one step rather than character by character. Crossing it
     * character by character is what let an apostrophe in `don't` open a quoted value.
     *
     * An unterminated construct runs to the end of the file deliberately: resuming after the
     * opener would put the walk back inside prose and read it as markup, which is the louder
     * half of the same bug.
     */
    private static function skipNonMarkup(string $contents, int $cursor): ?int
    {
        $length = strlen($contents);

        // Order matters: `{{--` is also a `{{`, so reading it as an echo would end the
        // comment at the first `}}` inside it and hand the rest of the body to the walk.
        foreach ([['{{--', '--}}'], ['{!!', '!!}'], ['{{', '}}']] as [$open, $close]) {
            if (substr($contents, $cursor, strlen($open)) !== $open) {
                continue;
            }

            $end = strpos($contents, $close, $cursor + strlen($open));

            return $end === false ? $length : $end + strlen($close);
        }

        if ($contents[$cursor] !== '@') {
            return null;
        }

        // `@php … @endphp` is a block of PHP, where `'<svg '.$attrs.'>'` is a string and not
        // an element. Three of this package's own views build icon markup exactly that way,
        // and each one started a tag that could never close.
        if (preg_match('/\G@php\b(?!\s*\()/i', $contents, $blockMatch, 0, $cursor) === 1) {
            $end = stripos($contents, '@endphp', $cursor + strlen($blockMatch[0]));

            return $end === false ? $length : $end + strlen('@endphp');
        }

        // A directive's argument is PHP too — `@if(str_contains($slot, '<x-wirekit'))` puts
        // both an apostrophe and a `<` in front of the walk, and `@if($active) … @endif`
        // between attributes puts one inside a tag. The parentheses are matched rather than
        // searched for, because arguments nest and carry strings of their own. An `@` NOT
        // followed by `(` is left alone: that is how `@click="…"` stays an Alpine shorthand
        // and `@example.com` stays prose.
        if (preg_match('/\G@[a-zA-Z][a-zA-Z0-9_]*\s*(?=\()/', $contents, $directiveMatch, 0, $cursor) === 1) {
            // Not `?? $length`. When the argument cannot be matched the honest move is to
            // skip nothing and let the walk read on as it always did — swallowing the rest
            // of the file on a construct we failed to understand is the failure this whole
            // change exists to remove, and it would be invisible.
            return self::pastMatchingParen($contents, $cursor + strlen($directiveMatch[0]));
        }

        return null;
    }

    /**
     * The offset just past the `)` that closes the `(` at `$open`, or null when it never
     * closes.
     *
     * Both things that can hide a `)` in PHP are handled, and the second one is not
     * optional: `@props([… // the item's name …])` is ordinary in this package, and a
     * matcher that saw only strings read that apostrophe as an opening quote and ran to the
     * next one somewhere else in the file, losing the tags in between.
     */
    private static function pastMatchingParen(string $contents, int $open): ?int
    {
        $length = strlen($contents);
        $depth = 0;

        for ($i = $open; $i < $length; $i++) {
            $char = $contents[$i];

            if ($char === '"' || $char === "'") {
                $i = self::pastPhpString($contents, $i);

                continue;
            }

            if ($char === '/' && ($contents[$i + 1] ?? '') === '*') {
                $end = strpos($contents, '*/', $i + 2);

                if ($end === false) {
                    return null;
                }

                $i = $end + 1;

                continue;
            }

            if (($char === '#') || ($char === '/' && ($contents[$i + 1] ?? '') === '/')) {
                $end = strpos($contents, "\n", $i);

                if ($end === false) {
                    return null;
                }

                $i = $end;

                continue;
            }

            if ($char === '(') {
                $depth++;

                continue;
            }

            if ($char === ')' && --$depth === 0) {
                return $i + 1;
            }
        }

        return null;
    }

    /**
     * The offset of the closing quote of the PHP string opened at `$open`, or the last
     * offset in the source when it never closes. A backslash escapes the next character in
     * both quote styles, which is the only escape that can hide a terminator.
     */
    private static function pastPhpString(string $contents, int $open): int
    {
        $length = strlen($contents);
        $quote = $contents[$open];

        for ($i = $open + 1; $i < $length; $i++) {
            if ($contents[$i] === '\\') {
                $i++;

                continue;
            }

            if ($contents[$i] === $quote) {
                return $i;
            }
        }

        return $length - 1;
    }

    /**
     * An attribute value with Blade's server-side constructs replaced by what the browser is
     * left with — a placeholder for each hole, and nothing at all for a comment.
     *
     * The caller is a scan that hands attribute values to a client-side grammar, and Blade is
     * not client-side: `@js(…)` dies on its `@` before the expression is reached, and a raw
     * `{!! … !!}` echo is not a property key. Reported as violations, they would be artifacts of
     * Blade rather than properties of what the browser sees, and `@js()` is the documented way to
     * hand server data to a client-side attribute, so a template using it could never read clean,
     * however good the expression was.
     *
     * The order is not cosmetic. `{{--` is also a `{{`, so substituting the echoes first turns
     * a whole comment into one placeholder and yields an expression that is not in the file —
     * a violation reported against a line that does not say what the developer was told it says.
     *
     * `@js(…)` is closed by MATCHING its parens rather than by searching for the next one,
     * because its argument is PHP: `@js($a ? 'x)' : 'y')` is valid, and stopping at the first
     * `)` leaves `: 'y')` behind, which reads no better than the `@` did. That matcher already
     * exists here for directive arguments, and reusing it is the reason this lives in this
     * class rather than in the command — a fourth hand-written scanner would have to learn PHP
     * strings and comments all over again, which is how each of the first three went wrong.
     *
     * A construct that cannot be substituted is LEFT IN PLACE rather than guessed at, so
     * `hasServerSideConstruct()` below can still see it. A directive that opens a block is the
     * ordinary case: `x-data="{ a: 1, @if($x) b: 2, @endif }"` is a fragment rather than an
     * expression, and no placeholder turns it into one.
     *
     * The placeholder is the CALLER's, because only the caller knows the grammar it is
     * standing in for. In a JavaScript grammar an identifier is the permissive choice and a
     * numeric literal is not — a hole sits at an assignment target in
     * `@click="{{ $model }} = true"` and at a key in an object literal, and a literal is
     * rejected at both.
     */
    public static function substituteServerSideConstructs(string $value, string $placeholder): string
    {
        // Comments first, and removed OUTRIGHT rather than replaced: a comment leaves nothing
        // behind at all, and standing a placeholder in for one puts a token where the browser
        // sees whitespace.
        $value = (string) preg_replace('/\{\{--.*?--\}\}/su', '', $value);

        // Raw before escaped, for the same reason comments come before both: these are checked
        // in order of specificity, and the shorter opener would otherwise claim the longer one.
        $value = (string) preg_replace('/\{!!.*?!!\}/su', $placeholder, $value);
        $value = (string) preg_replace('/\{\{.*?\}\}/su', $placeholder, $value);

        return self::substituteJsDirective($value, $placeholder);
    }

    /**
     * Whether a value still carries a Blade construct after substitution.
     *
     * This answers a counting question rather than a parsing one. A caller that hands these
     * values to a foreign grammar has to tell "the grammar rejects this" apart from "I could
     * not show the grammar what the browser sees", because only the first is a statement about
     * the developer's code. Reported as one number, a developer is sent to fix a template that
     * is fine — and after being sent there once, they stop reading the report.
     *
     * Deliberately narrow: the residual echo openers, and the parenthesized directive form that
     * substitution leaves behind when it cannot close it. A bare `@` is NOT matched, because it
     * is ordinary inside a string, and over-matching here quietly downgrades a real violation to
     * "could not check" — the one direction in which being wrong leaves no trace.
     */
    public static function hasServerSideConstruct(string $value): bool
    {
        return preg_match('/\{\{|\{!!|@[a-zA-Z][a-zA-Z0-9_]*\s*\(/u', $value) === 1;
    }

    /**
     * Every rendering of an attribute value that its conditional blocks allow: each `@if`,
     * `@unless` and `@isset` block replaced by one of its branches, the directives removed.
     *
     * A value like `{ a: 1@if($x), b: 2@endif }` is a fragment rather than an expression, and
     * what a browser receives is one of its renderings. Handing each of them to a client-side
     * grammar measures what the value can be, where a single substitution can only report
     * that it did not know.
     *
     * A block without `@else` renders empty as well, because its condition can be false.
     * Echoes and comments are copied rather than searched, so an `@if(` inside a PHP string
     * in `{{ … }}` is text. `@@` is Blade's escape for a literal `@`, and an `@` right after a
     * word character is not a directive either, which is how Blade itself reads them.
     *
     * Null when a block does not close, when a directive closes a block that is not open, or
     * when the renderings would exceed `$limit`: the caller then treats the value as one it
     * could not read, rather than as a clean one.
     *
     * @return list<string>|null
     */
    public static function conditionalBranches(string $value, int $limit = 16): ?array
    {
        $position = 0;
        $renderings = self::renderSequence($value, $position, [], $limit);

        return $renderings === null ? null : array_values(array_unique($renderings));
    }

    /**
     * The renderings of `$value` from `$position` up to the first of `$stops` at this level,
     * with `$position` left on it; with no stops, up to the end.
     *
     * @param  list<string>  $stops
     * @return list<string>|null
     */
    private static function renderSequence(string $value, int &$position, array $stops, int $limit): ?array
    {
        $renderings = [''];
        $length = strlen($value);
        $textStart = $position;

        for ($i = $position; $i < $length; $i++) {
            $past = self::pastEchoOrComment($value, $i);

            if ($past === null) {
                return null;
            }

            if ($past !== $i) {
                $i = $past - 1;

                continue;
            }

            if ($value[$i] !== '@') {
                continue;
            }

            if (($value[$i + 1] ?? '') === '@') {
                $i++;

                continue;
            }

            if ($i > 0 && preg_match('/\w/', $value[$i - 1]) === 1) {
                continue;
            }

            if (preg_match('/\G@(elseif|else|endif|endunless|endisset|if|unless|isset)\b/', $value, $match, 0, $i) !== 1) {
                continue;
            }

            $directive = $match[1];
            $text = substr($value, $textStart, $i - $textStart);

            if (in_array($directive, $stops, true)) {
                $position = $i;

                return array_map(static fn (string $rendering): string => $rendering.$text, $renderings);
            }

            if (! in_array($directive, ['if', 'unless', 'isset'], true)) {
                return null;
            }

            $position = $i;
            $block = self::renderBlock($value, $position, $directive, $limit);

            if ($block === null || count($renderings) * count($block) > $limit) {
                return null;
            }

            $combined = [];

            foreach ($renderings as $rendering) {
                foreach ($block as $branch) {
                    $combined[] = $rendering.$text.$branch;
                }
            }

            $renderings = $combined;
            $textStart = $position;
            $i = $position - 1;
        }

        if ($stops !== []) {
            return null;
        }

        $position = $length;
        $text = substr($value, $textStart);

        return array_map(static fn (string $rendering): string => $rendering.$text, $renderings);
    }

    /**
     * The renderings of the block that opens at `$position`, one per branch, with `$position`
     * left just past its closing directive.
     *
     * @return list<string>|null
     */
    private static function renderBlock(string $value, int &$position, string $opener, int $limit): ?array
    {
        $closer = 'end'.$opener;
        $alternatives = match ($opener) {
            'if' => ['elseif', 'else'],
            'unless' => ['else'],
            default => [],
        };

        $cursor = self::pastDirectiveArgument($value, $position + 1 + strlen($opener));
        $branches = [];
        $hasElse = false;

        while ($cursor !== null) {
            $body = self::renderSequence($value, $cursor, $hasElse ? [$closer] : [...$alternatives, $closer], $limit);

            if ($body === null) {
                return null;
            }

            array_push($branches, ...$body);

            if (count($branches) > $limit || preg_match('/\G@(\w+)/', $value, $match, 0, $cursor) !== 1) {
                return null;
            }

            if ($match[1] === $closer) {
                $position = $cursor + 1 + strlen($closer);

                return $hasElse ? $branches : [...$branches, ''];
            }

            if ($match[1] === 'else') {
                $hasElse = true;
                $cursor += strlen('@else');

                continue;
            }

            // `@elseif`, which carries a condition of its own.
            $cursor = self::pastDirectiveArgument($value, $cursor + strlen('@elseif'));
        }

        return null;
    }

    /**
     * The offset just past an echo or a comment that opens at `$offset`, `$offset` itself when
     * none does, or null when one opens and never closes. The longer openers are tried first:
     * `{{--` is also a `{{`.
     */
    private static function pastEchoOrComment(string $value, int $offset): ?int
    {
        foreach (['{{--' => '--}}', '{!!' => '!!}', '{{' => '}}'] as $open => $close) {
            if (substr($value, $offset, strlen($open)) === $open) {
                $end = strpos($value, $close, $offset + strlen($open));

                return $end === false ? null : $end + strlen($close);
            }
        }

        return $offset;
    }

    /**
     * The offset just past the parenthesized argument that follows a directive name ending at
     * `$offset`, or null when there is none or it never closes.
     */
    private static function pastDirectiveArgument(string $value, int $offset): ?int
    {
        if ($offset >= strlen($value)) {
            return null;
        }

        $open = $offset + strspn($value, " \t\r\n", $offset);

        return ($value[$open] ?? '') === '(' ? self::pastMatchingParen($value, $open) : null;
    }

    /**
     * Replace every `@js(…)` whose parens close with `$placeholder`, leaving the rest alone.
     *
     * Scanned rather than pattern-replaced because the end of the directive is a matching
     * problem: the argument is PHP, so a `)` inside a string or a nested call is not the end.
     * A `@js(` that never closes is left in place — skipping to the end of the value on a
     * construct we failed to understand is the same swallow this class removed from the tag
     * walk, and it would be invisible here too.
     */
    private static function substituteJsDirective(string $value, string $placeholder): string
    {
        $offset = 0;

        while (($start = strpos($value, '@js', $offset)) !== false) {
            // `\b` keeps `@jsonPayload(` out: it shares the first three characters and is not
            // this directive.
            if (preg_match('/\G@js\b\s*(?=\()/', $value, $match, 0, $start) !== 1) {
                $offset = $start + 3;

                continue;
            }

            $end = self::pastMatchingParen($value, $start + strlen($match[0]));

            if ($end === null) {
                $offset = $start + 3;

                continue;
            }

            $value = substr_replace($value, $placeholder, $start, $end - $start);
            $offset = $start + strlen($placeholder);
        }

        return $value;
    }

    /**
     * Every `<x-wirekit::…>` usage in a source string, with the attribute names it carries.
     *
     * A filter over `tagsFromSource()`, which owns the walk: one walk for every scanner
     * means every scanner reads the same Blade, where separate scanners would each learn
     * a different subset of it. The reasons a regex cannot do this job are recorded on
     * that method — a `>` is ordinary inside a value, and Blade's own constructs are not
     * markup — and they are the same reasons wherever the job comes up.
     *
     * What stays here is the part that is about WireKit rather than about Blade: only
     * `<x-wirekit::…>` counts, the name has to be a real component name, a tag another
     * element interrupted is discarded rather than reported with what it managed to
     * collect, and Blade's prop-binding colon is not part of an attribute name.
     *
     * Values are not returned: the question this serves is whether a name is a declared
     * prop, and carrying values would invite a second, weaker parser for them.
     *
     * @return list<array{name: string, attributes: list<string>}>
     */
    public static function extractWireKitComponentUsagesFromSource(string $contents): array
    {
        $usages = [];

        foreach (self::tagsFromSource($contents) as $tag) {
            if (! str_starts_with($tag['name'], 'x-wirekit::')) {
                continue;
            }

            // A tag another element interrupted is DISCARDED rather than reported with
            // whatever it managed to collect. Pointed at PHP fixtures, where a component tag
            // is split across string concatenation, a partial read yields entries like
            // `<button $html>` and `<ticker \;>`: artifacts, on a guard whose whole purpose
            // is reporting real findings. Silent plausible output is worse than a throw,
            // because everything built on top of it inherits the confidence without the
            // correctness.
            if ($tag['terminator'] === '<') {
                continue;
            }

            $name = substr($tag['name'], strlen('x-wirekit::'));

            // A component name is lowercase segments with at most one dotted
            // sub-component. Anything else is a documentation placeholder — `<x-wirekit::*>`
            // and `<x-wirekit::{name}>` both appear in the docs — and reporting one as a
            // usage would put a component that does not exist into a prop check.
            if (preg_match('/^[a-z0-9\-.]+$/', $name) !== 1) {
                continue;
            }

            $usages[] = [
                'name' => $name,
                // Blade's own prop-binding colon is not part of the name: `:value="$x"`
                // binds the `value` prop. Left in, every bound prop would read as unknown.
                'attributes' => array_values(array_unique(array_map(
                    static fn (string $a): string => ltrim($a, ':'),
                    $tag['attributes'],
                ))),
            ];
        }

        return $usages;
    }

    /**
     * Remove every span of a Blade file a browser would never execute.
     *
     * A question about what a template DOES — does it use a directive, render a component,
     * close `<head>` — is answered just as well by text that only MENTIONS the thing. A note
     * explaining a rule satisfies a search for the rule, and a check built on raw text then
     * reports a working setup over a broken one, which is precisely the case such a check
     * exists for.
     *
     * The Blade half was stripped first, after a `{{-- … @livewireScripts … --}}` note mis-cued
     * the doctor's ORDER check. Three syntaxes were left:
     *
     *   - an HTML comment — `<!-- @wirekitStyles goes here -->`
     *   - a `//` or `#` line comment inside `@php … @endphp`
     *   - the same inside a raw `<?php … ?>` island
     *
     * A comment naming `@wirekitStyles` would otherwise count as the directive, and on an install
     * with neither the directive nor the `@import` path the doctor would report a green setup
     * over a broken one.
     *
     * The PHP half is tokenized rather than matched, and that is not fastidiousness: `//`
     * also occurs inside `'https://…'` and `#` inside `'#fff'`. A pattern that cuts at either
     * would truncate a live line — and truncating a line is how a strip meant to remove false
     * positives starts producing false negatives instead. `token_get_all()` is the reader PHP
     * itself uses, so a string keeps its contents.
     */
    public static function liveText(string $blade): string
    {
        // Neither comment form nests, so one non-greedy pass over each is exact.
        $live = preg_replace('/\{\{--.*?--\}\}/s', '', $blade) ?? $blade;
        $live = preg_replace('/<!--.*?-->/s', '', $live) ?? $live;

        // A PHP comment can only exist inside a PHP island, so the islands are located first
        // and only their bodies are handed to the lexer. The delimiters are kept: removing them
        // would join the text on either side into one line and could fabricate a match.
        return preg_replace_callback(
            '/(@php\b)(.*?)(@endphp)|(<\?php)(.*?)(\?>)/s',
            static function (array $m): string {
                $isBladeIsland = $m[1] !== '';

                return $isBladeIsland
                    ? $m[1].self::stripPhpComments($m[2]).$m[3]
                    : $m[4].self::stripPhpComments($m[5]).$m[6];
            },
            $live,
        ) ?? $live;
    }

    /**
     * Drop comment tokens from a fragment of PHP, leaving every other byte untouched.
     *
     * The fragment arrives without an opening tag, so one is prepended for the lexer and then
     * skipped in the output — `token_get_all()` reports it as the first token and nothing else
     * inside a `@php` body can produce a second one.
     */
    private static function stripPhpComments(string $php): string
    {
        $live = '';

        foreach (token_get_all('<?php '.$php) as $index => $token) {
            if ($index === 0 && is_array($token) && $token[0] === T_OPEN_TAG) {
                continue;
            }

            if (is_array($token)) {
                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    continue;
                }

                $live .= $token[1];

                continue;
            }

            $live .= $token;
        }

        return $live;
    }
}
