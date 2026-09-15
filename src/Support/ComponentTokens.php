<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Pushery\WireKit\ComponentRegistry;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionMethod;

/**
 * The design tokens a component reads, derived from the component on every call and never kept
 * by hand.
 *
 * A token is what the MCP catalog calls one: a custom property the shipped stylesheet declares on
 * a line of its own. `declaredIn()` is that definition, and the catalog lists through it. The
 * name's shape is deliberately not part of the question: `--radius-wk`, the rail insets and the
 * whole `--reading-*` family carry no `-wk-` segment, and a pattern that required one would drop
 * exactly the properties a developer overrides most.
 *
 * ⚠️ A COMPONENT READS A TOKEN THROUGH FOUR DOORS, AND A LIST BUILT FROM THE FIRST ONE ALONE
 * LOOKS COMPLETE. Measured over the catalog when this was written, the template door alone
 * carried about five in every six of the reads the four carry together:
 *
 *   1. Its own template, in every form that compiles to `var()`: `var(--x)`, the utility
 *      shorthand `text-(--x)`, an arbitrary value or property, a fallback inside a fallback.
 *   2. The partials that template includes, through `@include` or as a tag the manifest lists no
 *      component under (`<x-wirekit::partials.…>`). They render inside it and belong to nobody
 *      else.
 *   3. The package helpers it calls. `button` names none of its intent colors, because
 *      `VariantResolver` writes them, and `tabs` names almost none of its tokens, because
 *      `TablistStyles` does.
 *   4. The stylesheet rules keyed to a `wk-` class or a `data-wk-` attribute it renders. Some
 *      declared tokens are read nowhere else, and `reading-minimap` reads a handful in its
 *      template and several dozen in total.
 *
 * "Own" stops at a tag the manifest lists as a component. A template that renders
 * `<x-wirekit::card.body>` does not read what `card.body` reads; the part carries its own list,
 * so one relationship is never listed twice and a reader can tell which element a token belongs
 * to.
 *
 * Two things this cannot see, by construction: a value a script reads at runtime, and a
 * stylesheet rule keyed to anything other than a `wk-` class or a `data-wk-` attribute.
 */
final class ComponentTokens
{
    /**
     * Parsed stylesheets, keyed by a hash of their content, so the shipped one is parsed once per
     * process however many components ask.
     *
     * @var array<string, array{declared: array<string, string>, classes: list<string>, rules: list<array{alternatives: list<list<string>>, reads: list<string>}>}>
     */
    private static array $stylesheets = [];

    /**
     * File contents by path, each with the modification time it was read at. A long-running
     * process (the MCP server) sees an edited file on its next question, not a stale copy.
     *
     * @var array<string, array{0: int, 1: string}>
     */
    private static array $files = [];

    /**
     * Templates with their comments blanked, kept the same way. Blanking runs the PHP tokenizer
     * over every `@php` block and was most of what one component cost.
     *
     * @var array<string, array{0: int, 1: string}>
     */
    private static array $blanked = [];

    /**
     * The tokens one component or sub-component reads, sorted.
     *
     * @return list<string>
     */
    public static function of(string $name): array
    {
        $path = ComponentRegistry::existingBladeFilePath($name);
        $source = $path !== null ? self::blankedFile($path) : '';

        // A class-based component is its class as much as its template: what the class writes
        // into the markup is its own too.
        $class = ComponentRegistry::componentClass($name);

        if ($class !== null) {
            $file = (new ReflectionClass($class))->getFileName();

            if ($file !== false) {
                $source .= "\n".self::withoutPhpComments(self::read($file));
            }
        }

        return $source === ''
            ? []
            : self::derive($source, self::read(dirname(__DIR__, 2).'/dist/wirekit.css'));
    }

    /**
     * The tokens a template source reads against a given stylesheet: the whole derivation with
     * both inputs passed in, so each door can be proven on a source of a few lines.
     *
     * Includes resolve against this package's views and helper calls against its classes,
     * because those two are what make a file the component's own.
     *
     * @return list<string>
     */
    public static function fromSource(string $source, string $stylesheet): array
    {
        return self::derive(BladeParser::blankComments($source), $stylesheet);
    }

    /**
     * The derivation over a source whose comments are already blank.
     *
     * @return list<string>
     */
    private static function derive(string $source, string $stylesheet): array
    {
        $sheet = self::stylesheet($stylesheet);
        $seen = [];
        $own = self::withIncludesAndHelpers($source, $seen);

        $reads = self::readsIn($own, $sheet['declared']);
        $hooks = self::hooksIn($own, $sheet['classes']);

        foreach ($sheet['rules'] as $rule) {
            if (self::rendersAny($rule['alternatives'], $hooks)) {
                array_push($reads, ...$rule['reads']);
            }
        }

        $reads = array_values(array_unique($reads));
        sort($reads);

        return $reads;
    }

    /**
     * Every custom property a stylesheet declares on a line of its own, as name => first value.
     *
     * The one definition of "a token" in this package. The MCP catalog lists what this returns,
     * and every component's list is drawn from it, so the two cannot disagree about which
     * properties exist.
     *
     * @return array<string, string>
     */
    public static function declaredIn(string $css): array
    {
        preg_match_all('/^\s*(--[a-z0-9-]+)\s*:\s*([^;]+);/m', $css, $matches, PREG_SET_ORDER);

        $declared = [];

        foreach ($matches as $match) {
            $declared[$match[1]] ??= trim($match[2]);
        }

        return $declared;
    }

    /**
     * The source with everything it renders as its own appended: the partials it includes and the
     * bodies of the package helpers it calls, each followed the same way.
     *
     * @param  array<string, true>  $seen
     */
    private static function withIncludesAndHelpers(string $source, array &$seen): string
    {
        $own = $source;

        // Every `@include` form names its view as a string literal. A tag names a view too, and when
        // the manifest lists no component under it (the `partials.*` views) it renders inside this
        // template exactly like an include, and nobody else will list what it reads. Only views of
        // this package are followed, and each one once, so two that include each other end.
        preg_match_all('/@include\w*\s*\([^\'"()]*[\'"]wirekit::([\w.-]+)[\'"]/', $source, $includes);
        preg_match_all('/<x-wirekit::([\w.-]+)/', $source, $tags);

        $views = $includes[1];

        foreach (array_unique($tags[1]) as $tag) {
            if (ComponentRegistry::resolve($tag) === null) {
                $views[] = 'components.'.$tag;
            }
        }

        foreach ($views as $view) {
            $path = dirname(__DIR__, 2).'/resources/views/'.str_replace('.', '/', $view).'.blade.php';

            if (isset($seen[$path]) || ! is_file($path)) {
                continue;
            }

            $seen[$path] = true;
            $own .= "\n".self::withIncludesAndHelpers(self::blankedFile($path), $seen);
        }

        return $own.self::helperSources($source, self::importsIn($source), $seen);
    }

    /**
     * The package classes a source imports, as short name => fully qualified name: `use` lines in
     * a `@php` block or a class file, and the `@use` directive.
     *
     * @return array<string, string>
     */
    private static function importsIn(string $source): array
    {
        $imports = [];

        preg_match_all('/^\s*use\s+(Pushery\\\\WireKit\\\\[\w\\\\]+?)(?:\s+as\s+(\w+))?\s*;/m', $source, $uses, PREG_SET_ORDER);
        preg_match_all('/@use\(\s*[\'"]\\\\?(Pushery\\\\WireKit\\\\[\w\\\\]+)[\'"](?:\s*,\s*[\'"](\w+)[\'"])?\s*\)/', $source, $directives, PREG_SET_ORDER);

        foreach ([...$uses, ...$directives] as $import) {
            $alias = ($import[2] ?? '') !== '' ? $import[2] : substr((string) strrchr('\\'.$import[1], '\\'), 1);
            $imports[$alias] = $import[1];
        }

        return $imports;
    }

    /**
     * The bodies of the package methods a source calls statically, by full name or through an
     * import, each followed into what it calls in turn.
     *
     * @param  array<string, string>  $imports
     * @param  array<string, true>  $seen
     */
    private static function helperSources(string $source, array $imports, array &$seen): string
    {
        preg_match_all('/(?<![\w\\\\$>:])(\\\\?Pushery\\\\WireKit\\\\[\w\\\\]+|[A-Z]\w*)::([a-zA-Z_]\w*)\s*\(/', $source, $calls, PREG_SET_ORDER);

        $bodies = '';

        foreach ($calls as $call) {
            $class = str_contains($call[1], '\\') ? ltrim($call[1], '\\') : ($imports[$call[1]] ?? null);

            if ($class !== null) {
                $bodies .= "\n".self::methodSource($class, $call[2], $seen);
            }
        }

        return $bodies;
    }

    /**
     * One method's body without its comments, followed into the methods and constants of its own
     * class, and no further.
     *
     * Method by method rather than class by class, and that is not a refinement: every template
     * calls `WireKit::warnUnknownProps()`, and the same class holds a padding helper no template
     * calls. Read whole, that class would hand its padding tokens to every component there is.
     *
     * And not into other classes, for the same reason one level up. A helper's own machinery is
     * the registry and the parsers, whose strings look like hooks and reads and are neither;
     * followed, they would attach to every template that asks a helper anything.
     *
     * @param  array<string, true>  $seen
     */
    private static function methodSource(string $class, string $method, array &$seen): string
    {
        $key = $class.'::'.$method.'()';

        if (isset($seen[$key]) || ! str_starts_with($class, 'Pushery\\WireKit\\') || ! method_exists($class, $method)) {
            return '';
        }

        $seen[$key] = true;

        $reflection = new ReflectionMethod($class, $method);
        $file = $reflection->getFileName();
        $start = $reflection->getStartLine();
        $end = $reflection->getEndLine();

        if ($file === false || $start === false || $end === false) {
            return '';
        }

        $lines = explode("\n", self::read($file));
        $body = self::withoutPhpComments(implode("\n", array_slice($lines, $start - 1, $end - $start + 1)));
        $owner = $reflection->getDeclaringClass();

        preg_match_all('/(?:(?:self|static)::|\$this->)([a-zA-Z_]\w*)\s*\(/', $body, $calls);

        foreach (array_unique($calls[1]) as $call) {
            $body .= "\n".self::methodSource($owner->getName(), $call, $seen);
        }

        // A constant is read through its VALUE, so an array of class strings counts however it is
        // laid out across lines.
        preg_match_all('/(?:self|static)::([A-Z][A-Z0-9_]*)\b(?!\s*\()/', $body, $constants);

        foreach (array_unique($constants[1]) as $constant) {
            if (! isset($seen[$owner->getName().'::'.$constant]) && $owner->hasConstant($constant)) {
                $seen[$owner->getName().'::'.$constant] = true;
                $body .= "\n".var_export((new ReflectionClassConstant($owner->getName(), $constant))->getValue(), true);
            }
        }

        return $body;
    }

    /**
     * Every declared token a source names right after an opening parenthesis, which is where each
     * form that compiles to `var()` puts it.
     *
     * A name built at render time, like `var(--motion-wk-delay-{$delay})`, reads every declared
     * token that fits around the interpolation, the way a prop reads every value it accepts.
     *
     * @param  array<string, string>  $declared
     * @return list<string>
     */
    private static function readsIn(string $source, array $declared): array
    {
        $reads = [];

        preg_match_all('/\(\s*(--[a-z0-9]+-[a-z0-9-]*?)(?:\{\{.*?\}\}|\{\$[^}]*\}|\$[a-zA-Z_]\w*)([a-z0-9-]*)/s', $source, $built, PREG_SET_ORDER);

        foreach ($built as $name) {
            foreach (array_keys($declared) as $token) {
                if (strlen($token) > strlen($name[1]) + strlen($name[2])
                    && str_starts_with($token, $name[1])
                    && str_ends_with($token, $name[2])) {
                    $reads[] = $token;
                }
            }
        }

        preg_match_all('/\(\s*(--[a-z0-9-]+)(?![\w{$-])/', $source, $plain);

        foreach ($plain[1] as $token) {
            if (isset($declared[$token])) {
                $reads[] = $token;
            }
        }

        return $reads;
    }

    /**
     * Every `wk-` class and `data-wk-` attribute a source renders, as a set. A class built at
     * render time, like `wk-tool-call-{{ $state }}`, renders every stylesheet class that begins
     * with its literal part.
     *
     * @param  list<string>  $stylesheetClasses
     * @return array<string, true>
     */
    private static function hooksIn(string $source, array $stylesheetClasses): array
    {
        $hooks = [];

        preg_match_all('/(?<![\w-])(?:data-wk-[a-z0-9-]+|wk-[a-z0-9-]+)(?![\w-])/', $source, $rendered);

        foreach ($rendered[0] as $hook) {
            $hooks[$hook] = true;
        }

        preg_match_all('/(?<![\w-])(wk-[a-z0-9-]*)(?:\{\{|\{\$)/', $source, $built);

        foreach (array_unique($built[1]) as $prefix) {
            foreach ($stylesheetClasses as $class) {
                if (str_starts_with($class, $prefix)) {
                    $hooks[$class] = true;
                }
            }
        }

        return $hooks;
    }

    /**
     * Whether a source renders every hook of at least one of a rule's alternatives.
     *
     * @param  list<list<string>>  $alternatives
     * @param  array<string, true>  $hooks
     */
    private static function rendersAny(array $alternatives, array $hooks): bool
    {
        foreach ($alternatives as $required) {
            foreach ($required as $hook) {
                if (! isset($hooks[$hook])) {
                    continue 2;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * A stylesheet's declared tokens, its `wk-` classes, and every style rule that reads a token,
     * each with the hooks an element must carry for the rule to be that element's.
     *
     * @return array{declared: array<string, string>, classes: list<string>, rules: list<array{alternatives: list<list<string>>, reads: list<string>}>}
     */
    private static function stylesheet(string $css): array
    {
        $key = hash('xxh3', $css);

        if (isset(self::$stylesheets[$key])) {
            return self::$stylesheets[$key];
        }

        $declared = self::declaredIn($css);
        $text = (string) preg_replace('~/\*.*?\*/~s', '', $css);

        // The innermost blocks hold the declarations; a block that holds blocks is only context.
        $blocks = [];
        $stack = [];
        $last = 0;

        preg_match_all('/[{};]/', $text, $marks, PREG_OFFSET_CAPTURE);

        foreach ($marks[0] as [$mark, $offset]) {
            if ($mark === '{') {
                $stack[] = ['prelude' => trim(substr($text, $last, $offset - $last)), 'start' => $offset + 1];
            } elseif ($mark === '}' && $stack !== []) {
                $open = array_pop($stack);
                $body = substr($text, $open['start'], $offset - $open['start']);

                if (! str_contains($body, '{')) {
                    $blocks[] = ['selector' => $open['prelude'], 'context' => array_column($stack, 'prelude'), 'body' => $body];
                }
            }

            $last = $offset + 1;
        }

        $keyframes = [];

        foreach ($blocks as $block) {
            foreach ($block['context'] as $context) {
                if (preg_match('/^@(?:-webkit-)?keyframes\s+([\w-]+)/', $context, $name) === 1) {
                    $keyframes[$name[1]] = [...$keyframes[$name[1]] ?? [], ...self::varReads($block['body'], $declared)];
                }
            }
        }

        $rules = [];

        foreach ($blocks as $block) {
            if (str_starts_with($block['selector'], '@') || preg_grep('/^@(?:-webkit-)?keyframes\b/', $block['context']) !== []) {
                continue;
            }

            $reads = self::varReads($block['body'], $declared);

            // A rule that runs an animation reads what the animation's steps read.
            preg_match_all('/animation(?:-name)?\s*:\s*([^;]+)/', $block['body'], $animations);

            foreach ($animations[1] as $value) {
                foreach (preg_split('/[\s,]+/', $value) ?: [] as $word) {
                    array_push($reads, ...$keyframes[$word] ?? []);
                }
            }

            $alternatives = $reads === [] ? [] : self::ownersOf($block['selector']);

            if ($alternatives !== []) {
                $rules[] = ['alternatives' => $alternatives, 'reads' => array_values(array_unique($reads))];
            }
        }

        preg_match_all('/\.(wk-[a-z0-9-]+)/', $text, $classes);

        return self::$stylesheets[$key] = [
            'declared' => $declared,
            'classes' => array_values(array_unique($classes[1])),
            'rules' => $rules,
        ];
    }

    /**
     * @param  array<string, string>  $declared
     * @return list<string>
     */
    private static function varReads(string $body, array $declared): array
    {
        preg_match_all('/var\(\s*(--[a-z0-9-]+)/', $body, $matches);

        return array_values(array_filter($matches[1], static fn (string $token): bool => isset($declared[$token])));
    }

    /**
     * For each selector in a list, the hooks its subject must carry: the `wk-` classes and
     * `data-wk-` attributes of the rightmost compound that has any.
     *
     * A rule on an item inside a rail is the item's, not the rail's. A rule whose subject carries
     * no hook, like the children of a prose block, belongs to the nearest ancestor that does.
     * `:is()` and `:where()` are alternatives, each judged on its own; `:not()` and `:has()` are
     * conditions that name some other element, so their hooks confer nothing.
     *
     * @return list<list<string>>
     */
    private static function ownersOf(string $selectorList): array
    {
        $alternatives = [];

        foreach (self::splitOutside($selectorList, [',']) as $selector) {
            foreach (self::withEachMatchesArgument($selector) as $variant) {
                $conditionsRemoved = (string) preg_replace('/:(?:not|has)\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\)/', '', $variant);

                foreach (array_reverse(self::splitOutside($conditionsRemoved, [' ', "\t", "\n", '>', '+', '~'])) as $compound) {
                    preg_match_all('/\.(wk-[a-z0-9-]+)|\[\s*(data-wk-[a-z0-9-]+)/', $compound, $found);

                    $hooks = array_values(array_unique(array_filter([...$found[1], ...$found[2]])));

                    if ($hooks !== []) {
                        sort($hooks);
                        $alternatives[] = $hooks;

                        break;
                    }
                }
            }
        }

        return $alternatives;
    }

    /**
     * A selector with its first `:is()` or `:where()` replaced by each argument in turn,
     * recursively, so every alternative is judged on its own.
     *
     * @return list<string>
     */
    private static function withEachMatchesArgument(string $selector, int $depth = 0): array
    {
        if ($depth > 4 || preg_match('/:(?:is|where)\(/', $selector, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return [$selector];
        }

        $open = $match[0][1] + strlen($match[0][0]);
        $close = $open;

        for ($level = 1, $i = $open, $length = strlen($selector); $i < $length && $level > 0; $i++) {
            $level += match ($selector[$i]) {
                '(' => 1,
                ')' => -1,
                default => 0,
            };
            $close = $i;
        }

        $before = substr($selector, 0, $match[0][1]);
        $after = substr($selector, $close + 1);
        $variants = [];

        foreach (self::splitOutside(substr($selector, $open, $close - $open), [',']) as $argument) {
            array_push($variants, ...self::withEachMatchesArgument($before.$argument.$after, $depth + 1));
        }

        return $variants === [] ? [$before.$after] : $variants;
    }

    /**
     * Split at any of the separator characters where they stand outside brackets, parentheses
     * and quotes. Empty parts are dropped.
     *
     * @param  list<string>  $separators
     * @return list<string>
     */
    private static function splitOutside(string $text, array $separators): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $quote = null;

        foreach (str_split($text) as $char) {
            if ($quote !== null) {
                $current .= $char;
                $quote = $char === $quote ? null : $quote;

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '(' || $char === '[') {
                $depth++;
            } elseif ($char === ')' || $char === ']') {
                $depth--;
            } elseif ($depth === 0 && in_array($char, $separators, true)) {
                $parts[] = $current;
                $current = '';

                continue;
            }

            $current .= $char;
        }

        $parts[] = $current;

        return array_values(array_filter(array_map(trim(...), $parts), static fn (string $part): bool => $part !== ''));
    }

    /**
     * PHP source without its comments. Newlines survive, so nothing after a comment moves to
     * another line. A method's own docblock is never inside its line range, but a `//` note in its
     * body can name a class it does not use.
     */
    private static function withoutPhpComments(string $php): string
    {
        $opened = str_starts_with(ltrim($php), '<?php');
        $stripped = '';

        foreach (token_get_all($opened ? $php : '<?php '.$php) as $token) {
            if (is_array($token) && ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT)) {
                $stripped .= str_repeat("\n", substr_count($token[1], "\n"));

                continue;
            }

            $stripped .= is_array($token) ? $token[1] : $token;
        }

        return $opened ? $stripped : substr($stripped, strlen('<?php '));
    }

    private static function blankedFile(string $path): string
    {
        $contents = self::read($path);
        $modified = self::$files[$path][0] ?? 0;
        $cached = self::$blanked[$path] ?? null;

        if ($cached !== null && $cached[0] === $modified) {
            return $cached[1];
        }

        $blanked = BladeParser::blankComments($contents);
        self::$blanked[$path] = [$modified, $blanked];

        return $blanked;
    }

    private static function read(string $path): string
    {
        if (! is_file($path)) {
            return '';
        }

        $modified = (int) filemtime($path);
        $cached = self::$files[$path] ?? null;

        if ($cached !== null && $cached[0] === $modified) {
            return $cached[1];
        }

        $contents = (string) file_get_contents($path);
        self::$files[$path] = [$modified, $contents];

        return $contents;
    }
}
