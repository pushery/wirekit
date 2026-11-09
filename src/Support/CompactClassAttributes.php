<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * The class lists of the kit's own views, routed through Support\CompactClasses at render time.
 *
 * Runs on the source of a view under `resources/views` before Blade compiles it, registered by the
 * service provider. On an HTML element it rewrites three things:
 *
 * - a `class` attribute: its literal text becomes a call over the same text, and an echo in it a
 *   call over the echoed value;
 * - `@class(...)`: the list it is given;
 * - an echo of an attribute bag (`{{ $attributes->class([...]) }}`): the defaults handed to
 *   `->class()`.
 *
 * Each call returns what it was given while compact classes are off, so the page is byte for byte
 * what it was. While they are on, the classes come out under their short names.
 *
 * Left as written:
 *
 * - a component tag (`<x-…>`): what a view hands a component is that component's caller class, and
 *   a component may read its caller's classes by name (`icon`, `button`, `label` and others do);
 * - an echo that reads a caller's attributes, since the class a developer writes on a component
 *   keeps its name;
 * - an Alpine or Livewire binding, a Blade comment, an HTML comment, a PHP block, the arguments of a
 *   directive such as `@props([...])` and the content of a `<script>` or `<style>` element;
 * - an attribute value holding a Blade directive.
 *
 * Anything the scan cannot read is copied unchanged, so a construct it does not know costs the
 * short names of one element and nothing else.
 *
 * @internal Registered by WireKitServiceProvider for the kit's views only.
 */
final class CompactClassAttributes
{
    private const CALL = '\\Pushery\\WireKit\\Support\\CompactClasses::';

    /**
     * The view-local switch every routed call reads. It is set once at the top of a rewritten view,
     * so a page with compact classes off asks the configuration once per view and writes its
     * classes without a call.
     */
    private const SWITCH = '$__wkCompact';

    /**
     * Where a routed call's trailing arguments go, filled in once the whole tag is read: the
     * element's field flag, and the classes it carries beside the list a call maps. The marker
     * holds the words of the call's own list when that list is a literal, up to `ARGS_END`: the
     * call reads those in its list already and is not handed them a second time.
     */
    private const ARGS = "\0wk-args:";

    private const ARGS_END = "\0";

    /** The template with every class list of its HTML elements routed through CompactClasses. */
    public static function rewrite(string $template): string
    {
        $out = '';
        $offset = 0;
        $length = strlen($template);

        while ($offset < $length && preg_match('/\{\{--|<!--|@verbatim\b|@php\b|@[a-zA-Z_]\w*[ \t]*\(|<\?php|<(?:script|style)\b|\{\{|\{!!|<(?=[a-zA-Z]|\{\{)/i', $template, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            [$token, $at] = $match[0];
            $out .= substr($template, $offset, $at - $offset);

            $end = self::passedOverEnd($template, $token, $at);

            if ($end !== null) {
                $out .= substr($template, $at, $end - $at);
                $offset = $end;

                continue;
            }

            $tag = self::tag($template, $at);

            if ($tag === null) {
                $out .= '<';
                $offset = $at + 1;

                continue;
            }

            [$tagEnd, $rewritten] = $tag;
            $out .= $rewritten;
            $offset = $tagEnd;
        }

        $out .= substr($template, $offset);

        // A PHP tag rather than an inline `@php(...)`: Blade takes an inline `@php(` ahead of a later
        // `@php ... @endphp` block for the opening of that block.
        return $out === $template
            ? $template
            : '<?php '.self::SWITCH.' = '.self::CALL.'enabled(); ?>'.$out;
    }

    /**
     * Where the text the rewrite passes over ends, for the token that opens it: a Blade or an HTML
     * comment, a verbatim block, PHP, the arguments of a directive, a script, a style or an echo.
     * Null for the `<` of a tag, which is read.
     *
     * A directive's arguments are PHP. Read as markup, a comment in `@props([...])` that names a
     * `<style>` element would open one, and everything up to the first real `</style>` would go
     * unread.
     */
    private static function passedOverEnd(string $template, string $token, int $at): ?int
    {
        $lower = strtolower($token);

        return match (true) {
            $token === '{{--' => self::after($template, '--}}', $at + 4),
            $token === '<!--' => self::after($template, '-->', $at + 4),
            $lower === '@verbatim' => self::after($template, '@endverbatim', $at),
            $lower === '@php' => self::phpDirectiveEnd($template, $at),
            // Unbalanced, only the directive's name and parenthesis are passed over, and the rest is read.
            str_starts_with($token, '@') => self::balanced($template, $at + strlen($token) - 1) ?? $at + strlen($token),
            $lower === '<?php' => self::after($template, '?>', $at + 5),
            str_starts_with($lower, '<script') => self::afterInsensitive($template, '</script>', $at),
            str_starts_with($lower, '<style') => self::afterInsensitive($template, '</style>', $at),
            $token === '{{' => self::after($template, '}}', $at + 2),
            $token === '{!!' => self::after($template, '!!}', $at + 3),
            default => null,
        };
    }

    /**
     * One opening tag from its `<`: where it ends and how it reads rewritten, or null when the scan
     * cannot read it.
     *
     * @return array{int, string}|null
     */
    private static function tag(string $template, int $at): ?array
    {
        $length = strlen($template);
        $i = $at + 1;

        if (preg_match('/\G(?:[a-zA-Z][\w:.-]*|\{\{\s*\$\w+\s*\}\})/', $template, $name, 0, $i) !== 1) {
            return null;
        }

        $i += strlen($name[0]);
        // `<x-button>`, `<x-slot:footer>` and `<livewire:…>` are components; `<{{ $tag }}>` is an
        // element whose name a view chose.
        $component = str_starts_with(strtolower($name[0]), 'x-') || (! str_starts_with($name[0], '{{') && str_contains($name[0], ':'));
        // A form field keeps the classes the stylesheet asks of fields by name. An element whose name
        // a view chose is asked at render time, by the name it gets.
        $field = str_starts_with($name[0], '{{')
            ? '\\Pushery\\WireKit\\Support\\CompactClasses::isField((string) '.trim(substr($name[0], 2, -2)).')'
            : in_array(strtolower($name[0]), ['input', 'select', 'textarea'], true);
        $out = '<'.$name[0];
        // The classes the element carries under their own names, from every part that writes or
        // sets one, which each call over a part of its classes reads beside that part.
        $context = [];

        while (true) {
            if (preg_match('/\G\s+/', $template, $space, 0, $i) === 1) {
                $out .= $space[0];
                $i += strlen($space[0]);
            }

            if ($i >= $length) {
                return null;
            }

            if ($template[$i] === '>') {
                return [$i + 1, self::finish($out, $field, $context).'>'];
            }

            if (substr($template, $i, 2) === '/>') {
                return [$i + 2, self::finish($out, $field, $context).'/>'];
            }

            if (substr($template, $i, 4) === '{{--') {
                $end = self::after($template, '--}}', $i + 4);
                $out .= substr($template, $i, $end - $i);
                $i = $end;

                continue;
            }

            if (substr($template, $i, 3) === '{!!' || substr($template, $i, 2) === '{{') {
                $raw = substr($template, $i, 3) === '{!!';
                $end = $raw ? self::after($template, '!!}', $i + 3) : self::after($template, '}}', $i + 2);
                $echo = substr($template, $i, $end - $i);

                if (! $component) {
                    $context = [...$context, ...self::literalWords($echo)];
                }

                $out .= $component ? $echo : self::bagEcho($echo);
                $i = $end;

                continue;
            }

            // A Blade directive on the tag, `@class([...])` or `@if($open) open @endif`; an Alpine
            // shorthand such as `@click="…"` is an attribute and is read below.
            if ($template[$i] === '@' && preg_match('/\G@[\w:.-]+\s*=/', $template, $unused, 0, $i) !== 1) {
                if (preg_match('/\G@(\w+)/', $template, $directive, 0, $i) !== 1) {
                    return null;
                }

                $j = $i + strlen($directive[0]);

                if (preg_match('/\G[ \t]*\(/', $template, $paren, 0, $j) === 1) {
                    $open = $j + strlen($paren[0]) - 1;
                    $close = self::balanced($template, $open);

                    if ($close === null) {
                        return null;
                    }

                    if (! $component && strtolower($directive[1]) === 'class') {
                        $context = [...$context, ...self::literalWords(substr($template, $open + 1, $close - $open - 2))];
                    }

                    $out .= ! $component && strtolower($directive[1]) === 'class'
                        ? '@class('.self::routed('list', substr($template, $open + 1, $close - $open - 2)).')'
                        : substr($template, $i, $close - $i);
                    $i = $close;

                    continue;
                }

                $out .= $directive[0];
                $i = $j;

                continue;
            }

            if (preg_match('/\G[^\s"\'>\/=]+/', $template, $attribute, 0, $i) !== 1) {
                return null;
            }

            $i += strlen($attribute[0]);

            if (preg_match('/\G\s*=\s*/', $template, $equals, 0, $i) !== 1) {
                $out .= $attribute[0];

                continue;
            }

            $j = $i + strlen($equals[0]);
            $quote = $template[$j] ?? '';

            if ($quote === '"' || $quote === "'") {
                $close = self::quoted($template, $j);

                if ($close === null) {
                    return null;
                }

                $value = substr($template, $j + 1, $close - $j - 2);

                if (! $component) {
                    $context = [...$context, ...self::classesWritten($attribute[0], $value)];
                }

                if (! $component && $quote === '"' && strtolower($attribute[0]) === 'class') {
                    $value = self::classValue($value);
                }

                $out .= $attribute[0].$equals[0].$quote.$value.$quote;
                $i = $close;

                continue;
            }

            if (preg_match('/\G[^\s>]+/', $template, $bare, 0, $j) !== 1) {
                return null;
            }

            $out .= $attribute[0].$equals[0].$bare[0];
            $i = $j + strlen($bare[0]);
        }
    }

    /** A `class` attribute's value with its text and its echoes routed through CompactClasses. */
    private static function classValue(string $value): string
    {
        // A directive inside the value is compiled into PHP around the text, which a call over the
        // text would cut apart.
        if (preg_match('/@\w|<\?|\{\{--/', $value) === 1) {
            return $value;
        }

        $parts = preg_split('/(\{\{.*?\}\}|\{!!.*?!!\})/s', $value, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        if ($parts === false) {
            return $value;
        }

        $out = '';

        foreach ($parts as $part) {
            if (str_starts_with($part, '{{') || str_starts_with($part, '{!!')) {
                $out .= self::echoValue($part);
            } elseif (trim($part) === '') {
                $out .= $part;
            } else {
                $out .= '{!! '.self::routed('map', '\''.str_replace(['\\', "'"], ['\\\\', "\\'"], $part).'\'').' !!}';
            }
        }

        return $out;
    }

    /** An echo inside a `class` attribute, its value routed through CompactClasses. */
    private static function echoValue(string $echo): string
    {
        $raw = str_starts_with($echo, '{!!');
        $code = trim($raw ? substr($echo, 3, -3) : substr($echo, 2, -2));

        // A caller's own class keeps its name.
        if ($code === '' || stripos($code, 'attributes') !== false) {
            return $echo;
        }

        return $raw
            ? '{!! '.self::routed('value', $code).' !!}'
            : '{{ '.self::routed('value', $code).' }}';
    }

    /** An echo in an element's attribute list, with the defaults of each `->class()` call routed. */
    private static function bagEcho(string $echo): string
    {
        $out = '';
        $length = strlen($echo);
        $i = 0;

        while ($i < $length) {
            $char = $echo[$i];

            if ($char === '\'' || $char === '"') {
                $end = self::stringEnd($echo, $i);
                $out .= substr($echo, $i, $end - $i);
                $i = $end;

                continue;
            }

            if (($end = self::commentEnd($echo, $i)) !== null) {
                $out .= substr($echo, $i, $end - $i);
                $i = $end;

                continue;
            }

            if (substr($echo, $i, 8) === '->class(') {
                $close = self::balanced($echo, $i + 7);

                if ($close === null) {
                    return $echo;
                }

                $out .= '->class('.self::routed('list', substr($echo, $i + 8, $close - $i - 9)).')';
                $i = $close;

                continue;
            }

            $out .= $char;
            $i++;
        }

        return $out;
    }

    /**
     * A routed call over an expression, taken only while the view's switch is on: with it off the
     * expression stands as written, evaluated once either way.
     */
    private static function routed(string $method, string $expression): string
    {
        $marker = self::ARGS.implode(' ', self::literalListWords($expression)).self::ARGS_END;

        return '('.self::SWITCH.' ? '.self::CALL.$method.'('.$expression.$marker.') : ('.$expression.'))';
    }

    /**
     * The words of a call's list when the list is one single-quoted literal, the form the rewrite
     * gives the text of a class attribute. An expression has none here: its words are known only
     * once it runs, and the call reads them there.
     *
     * @return list<string>
     */
    private static function literalListWords(string $expression): array
    {
        if (preg_match('/\A\'((?:[^\'\\\\]|\\\\.)*)\'\z/s', $expression, $match) !== 1) {
            return [];
        }

        $text = strtr($match[1], ['\\\\' => '\\', "\\'" => "'"]);

        return preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * The tag's calls with their trailing arguments: the field flag alone while the element carries
     * no kit utility under its own name beside a call's own list, and the flag and those classes
     * when it does.
     *
     * @param  list<string>  $context
     */
    private static function finish(string $out, bool|string $field, array $context): string
    {
        $kept = array_values(array_unique(array_filter(
            $context,
            static fn (string $class): bool => CompactClasses::keepsItsName($class, $field !== false),
        )));

        $pattern = '/'.preg_quote(self::ARGS, '/').'([^\x00]*)'.preg_quote(self::ARGS_END, '/').'/';

        return (string) preg_replace_callback($pattern, static function (array $marker) use ($kept, $field): string {
            $beside = array_values(array_diff($kept, preg_split('/\s+/', $marker[1], -1, PREG_SPLIT_NO_EMPTY) ?: []));

            if ($beside === []) {
                return self::fieldArgument($field);
            }

            $flag = match (true) {
                $field === true => 'true',
                is_string($field) => $field,
                default => 'false',
            };

            return ', '.$flag.', ['.implode(', ', array_map(static fn (string $class): string => '\''.str_replace(['\\', "'"], ['\\\\', "\\'"], $class).'\'', $beside)).']';
        }, $out);
    }

    /**
     * The classes an attribute writes or a script sets on the element: the words of a class
     * attribute's text and the string literals of its echoes, the string literals of an Alpine
     * class binding, and the words of an `x-transition` or a `wire:*.class` value.
     *
     * @return list<string>
     */
    private static function classesWritten(string $attribute, string $value): array
    {
        $name = strtolower($attribute);

        if ($name === 'class') {
            $text = (string) preg_replace('/\{\{.*?\}\}|\{!!.*?!!\}/s', ' ', $value);

            return [...(preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []), ...self::literalWords($value)];
        }

        if ($name === ':class' || $name === 'x-bind:class') {
            return self::literalWords($value);
        }

        if (preg_match('/^(?:x-transition[\w:.-]*|wire:[\w.-]*class[\w.-]*)$/', $name) === 1) {
            return preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        return [];
    }

    /**
     * The words of every string literal in some code, single-quoted, double-quoted or a template
     * string.
     *
     * @return list<string>
     */
    private static function literalWords(string $code): array
    {
        preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'|\"((?:[^\"\\\\]|\\\\.)*)\"|`([^`]*)`/", $code, $literals, PREG_SET_ORDER);
        $words = [];

        foreach ($literals as $literal) {
            // One of the three groups holds the literal; the others are empty or absent.
            $text = implode('', array_slice($literal, 1));
            array_push($words, ...(preg_split('/\s+/', stripslashes($text), -1, PREG_SPLIT_NO_EMPTY) ?: []));
        }

        return $words;
    }

    /**
     * The second argument a routed call takes: `true` on a form field, the render-time question for
     * an element whose name a view chose, and nothing otherwise.
     */
    private static function fieldArgument(bool|string $field): string
    {
        return match (true) {
            $field === true => ', true',
            is_string($field) => ', '.$field,
            default => '',
        };
    }

    /**
     * The offset just after the parenthesis that closes the one at `$open`, or null.
     *
     * Strings and comments are read whole: a comment in a class array is prose, and an apostrophe
     * or a bracket in it opens nothing.
     */
    private static function balanced(string $code, int $open): ?int
    {
        $depth = 0;
        $length = strlen($code);

        for ($i = $open; $i < $length; $i++) {
            $char = $code[$i];

            if ($char === '\'' || $char === '"') {
                $i = self::stringEnd($code, $i) - 1;
            } elseif (($end = self::commentEnd($code, $i)) !== null) {
                $i = $end - 1;
            } elseif ($char === '(' || $char === '[') {
                $depth++;
            } elseif ($char === ')' || $char === ']') {
                $depth--;

                if ($depth === 0) {
                    return $char === ')' ? $i + 1 : null;
                }
            }
        }

        return null;
    }

    /**
     * The offset just after the PHP comment that opens at `$start`, or null when none opens there.
     * A line comment ends before its newline, which the code after it still needs.
     */
    private static function commentEnd(string $code, int $start): ?int
    {
        if ($code[$start] !== '/') {
            return null;
        }

        $next = $code[$start + 1] ?? '';

        if ($next === '/') {
            $end = strpos($code, "\n", $start);

            return $end === false ? strlen($code) : $end;
        }

        if ($next === '*') {
            $end = strpos($code, '*/', $start + 2);

            return $end === false ? strlen($code) : $end + 2;
        }

        return null;
    }

    /** The offset just after the PHP string literal that opens at `$start`. */
    private static function stringEnd(string $code, int $start): int
    {
        $quote = $code[$start];
        $length = strlen($code);

        for ($i = $start + 1; $i < $length; $i++) {
            if ($code[$i] === '\\') {
                $i++;
            } elseif ($code[$i] === $quote) {
                return $i + 1;
            }
        }

        return $length;
    }

    /** The offset just after an attribute value's closing quote, reading echoes whole, or null. */
    private static function quoted(string $template, int $open): ?int
    {
        $quote = $template[$open];
        $length = strlen($template);

        for ($i = $open + 1; $i < $length; $i++) {
            if (substr($template, $i, 4) === '{{--') {
                $i = self::after($template, '--}}', $i + 4) - 1;
            } elseif (substr($template, $i, 3) === '{!!') {
                $i = self::after($template, '!!}', $i + 3) - 1;
            } elseif (substr($template, $i, 2) === '{{') {
                $i = self::after($template, '}}', $i + 2) - 1;
            } elseif ($template[$i] === $quote) {
                return $i + 1;
            }
        }

        return null;
    }

    /** Where a `@php` directive ends: its parenthesis for the inline form, `@endphp` otherwise. */
    private static function phpDirectiveEnd(string $template, int $at): int
    {
        if (preg_match('/\G@php[ \t]*\(/i', $template, $inline, 0, $at) === 1) {
            return self::balanced($template, $at + strlen($inline[0]) - 1) ?? strlen($template);
        }

        return self::after($template, '@endphp', $at + 4);
    }

    /** The offset just after the next `$needle` from `$from`, or the end of the template. */
    private static function after(string $template, string $needle, int $from): int
    {
        $found = strpos($template, $needle, $from);

        return $found === false ? strlen($template) : $found + strlen($needle);
    }

    private static function afterInsensitive(string $template, string $needle, int $from): int
    {
        $found = stripos($template, $needle, $from);

        return $found === false ? strlen($template) : $found + strlen($needle);
    }
}
