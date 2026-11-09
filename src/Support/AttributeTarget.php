<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Pushery\WireKit\ComponentRegistry;

/**
 * The element a component's attribute bag lands on, and the element-specific attributes that
 * mean nothing there.
 *
 * `StrictnessGate::HTML_ELEMENT_ATTRIBUTES` lets `value`, `name`, `placeholder`, `href` and the
 * rest through on every component, because each is valid HTML somewhere. On a component that
 * renders the bag onto a `<span>`, `value="…"` is still an attribute nothing reads: the value
 * never appears on the page, and a test asserting the text finds it inside the attribute.
 *
 * The answer is deliberately narrow. The template has to name every element the bag can land
 * on: each place it echoes the bag opens a literal HTML tag, or a tag from a variable that one
 * assignment sets to one of two literal tags (`$tag = $href !== null ? 'a' : 'span'`), and the
 * bag is never handed on to another component. A tag the template computes or takes from a prop
 * (`<{{ $as }}`) and a bag passed to a child yield no target, and no finding: a false finding
 * ends a developer's lint run, a missed one does not. Where the bag can land on more than one
 * element, an attribute is reported only when none of them takes it. For the same reason only
 * elements whose attributes are few and settled are judged; any other tag counts as accepting
 * every element-specific attribute.
 */
final class AttributeTarget
{
    /**
     * The element-specific attributes of `StrictnessGate::HTML_ELEMENT_ATTRIBUTES` each judged
     * element accepts, from the WHATWG index of attributes. A tag missing here is not judged.
     *
     * @var array<string, list<string>>
     */
    private const ACCEPTED = [
        'a' => ['href', 'target', 'download', 'ping', 'rel', 'hreflang', 'type', 'referrerpolicy'],
        'div' => [], 'span' => [], 'p' => [],
        'article' => [], 'section' => [], 'nav' => [], 'aside' => [],
        'header' => [], 'footer' => [], 'main' => [],
        'figure' => [], 'figcaption' => [],
        'ul' => [], 'ol' => ['type'], 'li' => ['value'],
        'dl' => [], 'dt' => [], 'dd' => [],
        'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
        'blockquote' => ['cite'],
        'code' => [], 'kbd' => [], 'mark' => [], 'small' => [], 'strong' => [], 'em' => [],
        'table' => [],
    ];

    /** @var array<string, list<string>|null> */
    private static array $targets = [];

    /**
     * The literal HTML tag every bag echo of the component's template opens, or null when there
     * is none, it is dynamic, it differs between echoes, or the bag is handed to a child.
     */
    public static function elementFor(string $component): ?string
    {
        return self::single(self::elementsFor($component));
    }

    /**
     * Every element the bag of the component's template can land on, in alphabetical order, or
     * null when the template does not name each of them or hands the bag to a child.
     *
     * @return list<string>|null
     */
    public static function elementsFor(string $component): ?array
    {
        if (array_key_exists($component, self::$targets)) {
            return self::$targets[$component];
        }

        $path = ComponentRegistry::existingBladeFilePath($component);

        return self::$targets[$component] = $path === null ? null : self::elementsIn((string) file_get_contents($path));
    }

    /**
     * The same answer for a template source, so it can be asked of a template that is not a
     * registered component.
     */
    public static function elementIn(string $source): ?string
    {
        return self::single(self::elementsIn($source));
    }

    /**
     * Every element a template source can render its bag onto, in alphabetical order, or null.
     *
     * @return list<string>|null
     */
    public static function elementsIn(string $source): ?array
    {
        $count = preg_match_all('/\{\{\s*\$attributes\b|\{!!\s*\$attributes\b|:attributes\s*=\s*["\']\s*\$attributes\b/', $source, $sinks, PREG_OFFSET_CAPTURE);

        if ($count === false || $count === 0) {
            return null;
        }

        $tags = [];

        foreach ($sinks[0] as [$text, $offset]) {
            // Handed to a child component: the child decides where it lands.
            if (! str_starts_with($text, '{')) {
                return null;
            }

            $tag = self::openingTagBefore($source, (int) $offset);
            $named = $tag !== null && str_starts_with($tag, '$') ? self::tagsAssigned($source, substr($tag, 1)) : ($tag === null ? null : [$tag]);

            if ($named === null) {
                return null;
            }

            foreach ($named as $name) {
                if (preg_match('/^[a-z][a-z0-9]*$/', $name) !== 1) {
                    return null;
                }

                $tags[$name] = true;
            }
        }

        $elements = array_keys($tags);
        sort($elements);

        return $elements;
    }

    /**
     * Element-specific attributes in `$attributes` that the component's target element does not
     * accept. A declared prop is never one, and neither is a name the template reads itself,
     * because a component may take an attribute from the bag rather than through `@props`.
     *
     * @param  list<string>  $attributes  raw attribute names as written on the tag
     * @param  list<string>  $declared  the component's accepted prop names
     * @return list<string>
     */
    public static function meaninglessOn(string $component, array $attributes, array $declared): array
    {
        $elements = self::elementsFor($component);

        if ($elements === null || array_diff($elements, array_keys(self::ACCEPTED)) !== []) {
            return [];
        }

        // An attribute one of the elements takes means something wherever the bag lands on it.
        $accepted = array_merge(...array_map(static fn (string $element): array => self::ACCEPTED[$element] ?? [], $elements));
        $path = ComponentRegistry::existingBladeFilePath($component);
        $source = $path === null ? '' : (string) file_get_contents($path);
        $meaningless = [];

        foreach ($attributes as $attribute) {
            $name = ltrim($attribute, ':');

            if (! in_array($name, StrictnessGate::HTML_ELEMENT_ATTRIBUTES, true)
                || in_array($name, $declared, true)
                || in_array($name, $accepted, true)
                || str_contains($source, "'{$name}'")
                || str_contains($source, "\"{$name}\"")) {
                continue;
            }

            $meaningless[] = $name;
        }

        return array_values(array_unique($meaningless));
    }

    /**
     * The name of the tag whose opening the character at `$offset` sits in, or null. Blade echoes
     * are skipped whole, so a `>` inside one does not end the search.
     */
    private static function openingTagBefore(string $source, int $offset): ?string
    {
        $i = $offset - 1;

        while ($i >= 0) {
            if ($source[$i] === '}' && $i > 0 && $source[$i - 1] === '}') {
                $open = strrpos(substr($source, 0, $i - 1), '{{');

                if ($open === false) {
                    return null;
                }

                $i = $open - 1;

                continue;
            }

            if ($source[$i] === '>') {
                return null;
            }

            if ($source[$i] === '<' && preg_match('/\G<([a-zA-Z][a-zA-Z0-9:.-]*)/', $source, $match, 0, $i) === 1) {
                return $match[1];
            }

            // A tag the template takes from a variable, `<{{ $tag }}`, comes back as the variable's
            // name with its `$`, for the caller to resolve.
            if ($source[$i] === '<' && preg_match('/\G<\{\{\s*\$([a-zA-Z_][a-zA-Z0-9_]*)\s*\}\}/', $source, $match, 0, $i) === 1) {
                return '$'.$match[1];
            }

            $i--;
        }

        return null;
    }

    /**
     * The two literal tags the template's one assignment to a variable chooses between, as
     * `$tag = $href !== null ? 'a' : 'span';` does, or null for any other shape: a second
     * assignment, a branch that is not a quoted tag, a nested choice, a tag computed or taken
     * from a prop.
     *
     * @return list<string>|null
     */
    private static function tagsAssigned(string $source, string $variable): ?array
    {
        $name = preg_quote($variable, '/');

        // Every write counts, a compound one and a loop variable included, so a second one declines.
        if (preg_match_all('/\$'.$name.'\s*(?:\?\?|[.+\-*\/%])?=(?!=)|\bas\s+(?:\$\w+\s*=>\s*)?\$'.$name.'\b/', $source) !== 1
            || preg_match('/\$'.$name.'\s*=(?!=)[^;?]*\?\s*([\'"])([a-z][a-z0-9]*)\1\s*:\s*([\'"])([a-z][a-z0-9]*)\3\s*;/', $source, $match) !== 1) {
            return null;
        }

        return [$match[2], $match[4]];
    }

    /**
     * The one element of a list, or null for none or several.
     *
     * @param  list<string>|null  $elements
     */
    private static function single(?array $elements): ?string
    {
        return $elements !== null && count($elements) === 1 ? $elements[0] : null;
    }

    /**
     * Forget the templates read so far. For tests only.
     */
    public static function flush(): void
    {
        self::$targets = [];
    }
}
