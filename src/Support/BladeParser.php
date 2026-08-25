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
     * `trigger` slot — schema previously reported them as default-slot
     * only, hiding the requirement.
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
     * @return list<array{name: string, required: bool}>
     */
    /**
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

        // Bare references: `{{ $name }}`, `{!! $name !!}`, `$name->method()`,
        // `$name->isEmpty()`. These signal a hard dependency — if the
        // developer doesn't supply the slot, the component errors.
        if (preg_match_all('/\{\{\s*\$([a-zA-Z][a-zA-Z0-9]*)\b/', $contents, $bareMatches)) {
            foreach ($bareMatches[1] as $name) {
                $bareNames[$name] = null;
            }
        }
        if (preg_match_all('/\{!!\s*\$([a-zA-Z][a-zA-Z0-9]*)\b/', $contents, $rawMatches)) {
            foreach ($rawMatches[1] as $name) {
                $bareNames[$name] = null;
            }
        }
        // Method calls on slot vars — e.g. `$slot->isEmpty()`,
        // `$trigger->toHtml()`.
        if (preg_match_all('/\$([a-zA-Z][a-zA-Z0-9]*)->[a-zA-Z]/', $contents, $methodMatches)) {
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

        $exclude = array_unique(array_merge($propNames, $reserved, $phpLocals, $additionalExcludes));

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
            // explicit guard wins. Otherwise it's required.
            if (! isset($records[$name])) {
                $records[$name] = ['name' => $name, 'required' => true];
            }
        }

        return array_values($records);
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
     * Scan a PHP-source string for `$name = ...` assignments and
     * append each captured name to the `$locals` accumulator. Catches
     * the common `$foo = ...;` shape; nested-array / destructuring
     * shapes fall through silently.
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
        // foreach (`@foreach($items as $item)`) declares $item locally;
        // same risk class. Catch the obvious shape.
        if (preg_match_all('/foreach\s*\(\s*[^\s]+\s+as\s+\$([a-zA-Z][a-zA-Z0-9]*)/', $body, $foreachMatches)) {
            foreach ($foreachMatches[1] as $name) {
                $locals[] = $name;
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

            // Never past the end. The cursor running to `strlen + 1` on a malformed file is
            // how the audit command used to die with a raw `ValueError` out of its
            // extraction phase — before it could report anything at all.
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
     * next one somewhere else in the file. That is the same defect this class keeps
     * producing, one level down — it was measured here before it shipped, on a walk that had
     * otherwise stopped losing tags.
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
     * `{!! … !!}` echo is not a property key. Measured over a real application, ELEVEN of
     * twenty-five reported violations were exactly this — artifacts of Blade rather than
     * properties of what the browser sees. Worse, `@js()` is the documented way to hand server
     * data to a client-side attribute, so a clean report was unreachable for a template that
     * used it, no matter how good the expression was.
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
     * A filter over `tagsFromSource()`, which owns every hardening this scan used to carry
     * on its own. It is worth naming why the walk lives there rather than here: the same
     * defect was found three times in three separate scanners, and each one had learned a
     * different subset of Blade. The reasons a regex cannot do this job are recorded on
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
            // whatever it managed to collect. Reported from a downstream tree that pointed
            // this at PHP fixtures, where a component tag split across string concatenation
            // yielded entries like `<button $html>` and `<ticker \;>` — eight of nine
            // findings were artifacts, on a guard whose whole purpose was reporting real
            // ones. Silent plausible output is worse than a throw, because everything built
            // on top of it inherits the confidence without the correctness.
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
}
