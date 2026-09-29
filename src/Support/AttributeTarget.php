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
 * The answer is deliberately narrow. The target is known only when every place the template
 * echoes the bag opens the same literal HTML tag, and the bag is never handed on to another
 * component. A dynamic tag (`<{{ $as }}`), a bag split across two elements or one passed to a
 * child all yield no target, and no finding: a false finding ends a developer's lint run, a
 * missed one does not. For the same reason only elements whose attributes are few and settled
 * are judged; any other tag counts as accepting every element-specific attribute.
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

    /** @var array<string, string|null> */
    private static array $targets = [];

    /**
     * The literal HTML tag every bag echo of the component's template opens, or null when there
     * is none, it is dynamic, it differs between echoes, or the bag is handed to a child.
     */
    public static function elementFor(string $component): ?string
    {
        if (array_key_exists($component, self::$targets)) {
            return self::$targets[$component];
        }

        $path = ComponentRegistry::existingBladeFilePath($component);

        return self::$targets[$component] = $path === null ? null : self::elementIn((string) file_get_contents($path));
    }

    /**
     * The same answer for a template source, so it can be asked of a template that is not a
     * registered component.
     */
    public static function elementIn(string $source): ?string
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

            if ($tag === null || preg_match('/^[a-z][a-z0-9]*$/', $tag) !== 1) {
                return null;
            }

            $tags[$tag] = true;
        }

        return count($tags) === 1 ? (string) array_key_first($tags) : null;
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
        $element = self::elementFor($component);

        if ($element === null || ! array_key_exists($element, self::ACCEPTED)) {
            return [];
        }

        $path = ComponentRegistry::existingBladeFilePath($component);
        $source = $path === null ? '' : (string) file_get_contents($path);
        $meaningless = [];

        foreach ($attributes as $attribute) {
            $name = ltrim($attribute, ':');

            if (! in_array($name, StrictnessGate::HTML_ELEMENT_ATTRIBUTES, true)
                || in_array($name, $declared, true)
                || in_array($name, self::ACCEPTED[$element], true)
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

            $i--;
        }

        return null;
    }

    /**
     * Forget the templates read so far. For tests only.
     */
    public static function flush(): void
    {
        self::$targets = [];
    }
}
