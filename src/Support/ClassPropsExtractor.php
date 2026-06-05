<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionUnionType;

/**
 * Constructor-reflection prop extractor for class-based Blade components.
 *
 * The companion class to `PropsParser` (which reads `@props([…])` blocks
 * from anonymous Blade files). For class-based components — those
 * registered via Laravel's `loadViewComponentsAs(...)` — `@props` is not
 * applicable; the prop surface IS the constructor signature. This class
 * walks that signature via Reflection and produces the same shape as
 * `PropsParser` returns for anonymous components, so downstream
 * callers (ExportJsonCommand, InstallCommand schema writer,
 * ShowComponentCommand) can route both flavors through a single code
 * path.
 *
 * Return shape per entry — mirrors `PropsParser::parseBlade()`:
 *   - `name` — constructor-param name (kebab-cased for Blade-attribute
 *     compatibility — `wireStream` → `wire-stream`).
 *   - `default` — string representation of the parameter's default value
 *     if one is declared; null when the param is required.
 *   - `default_normalized` — same as `default` for now (no whitespace
 *     collapse needed on a Reflection-derived value).
 *   - `type_hint` — the parameter's PHP type as a string (`string`,
 *     `?string`, `int`, `bool`, `array`, `string|null`). Null when no
 *     type hint is declared.
 *   - `comment` — null (Reflection doesn't surface parameter-level
 *     same-line comments; the equivalent docs are in the @phpdoc block
 *     above the constructor, which is read separately if needed).
 *   - `examples` — always empty list. Class-based props don't carry
 *     `@example` annotations the same way; future work can extract
 *     them from the constructor's @phpdoc.
 */
final class ClassPropsExtractor
{
    /**
     * Return every public property name (including constructor-promoted ones)
     * of the class. Used by the JSON-manifest exporter to extend BladeParser's
     * slot-detection exclude set so class-side variables like
     * `$alpineComponent` / `$chartConfig` on Chart.php don't false-positive
     * as required `<x-slot:...>` references in the emitted manifest.
     *
     * @param  class-string  $className
     * @return list<string>
     */
    public static function publicPropertyNames(string $className): array
    {
        if (! class_exists($className)) {
            return [];
        }

        $reflection = new ReflectionClass($className);
        $names = [];
        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $names[] = $property->getName();
        }

        return array_values(array_unique($names));
    }

    /**
     * Extract every constructor parameter from a class as a prop entry.
     *
     * Returns an empty array when the class doesn't exist OR has no
     * public constructor (e.g. an interface, an abstract class, a
     * class with a private constructor).
     *
     * @param  class-string  $className
     * @return list<array{name: string, default: ?string, default_normalized: ?string, type_hint: ?string, comment: ?string, examples: list<string>}>
     */
    public static function extract(string $className): array
    {
        if (! class_exists($className)) {
            return [];
        }

        $reflection = new ReflectionClass($className);
        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return [];
        }

        $entries = [];
        foreach ($constructor->getParameters() as $param) {
            $name = self::camelToKebab($param->getName());

            $default = null;
            $defaultNormalized = null;
            if ($param->isDefaultValueAvailable()) {
                $defaultValue = $param->getDefaultValue();
                $default = self::stringifyDefault($defaultValue);
                $defaultNormalized = $default;
            }

            $typeHint = self::stringifyType($param);

            $entries[] = [
                'name' => $name,
                'default' => $default,
                'default_normalized' => $defaultNormalized,
                'type_hint' => $typeHint,
                'comment' => null,
                'examples' => [],
            ];
        }

        return $entries;
    }

    /**
     * Convert a camelCase parameter name to kebab-case for Blade
     * attribute compatibility — Laravel's class-component bridge does
     * this transform automatically (`wireStream` constructor param ↔
     * `wire-stream` HTML attribute). Surfacing the kebab form in the
     * schema keeps AI / IDE tooling aligned with what developers
     * actually type in their Blade templates.
     */
    private static function camelToKebab(string $name): string
    {
        // Insert a hyphen before every uppercase letter (except at the
        // start), then lowercase the whole string. `wireStream` →
        // `wire-stream`, `wireStreamCap` → `wire-stream-cap`.
        $kebab = preg_replace('/(?<!^)([A-Z])/', '-$1', $name);

        return strtolower($kebab ?? $name);
    }

    /**
     * Convert a Reflection default value to a string representation
     * that matches what `PropsParser` emits for anonymous components.
     * Scalar values render as their PHP literal form; arrays render as
     * `[...]`; null is the string `'null'`; objects fall back to their
     * class name (rare in constructor defaults).
     */
    private static function stringifyDefault(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_string($value)) {
            // Single-quote with quote-escaping. Matches PropsParser's
            // raw-default capture for string literals.
            return "'".addslashes($value)."'";
        }
        if (is_array($value)) {
            // Reproduce a PHP array literal. For empty arrays, emit `[]`.
            if ($value === []) {
                return '[]';
            }
            $parts = [];
            foreach ($value as $k => $v) {
                $parts[] = is_string($k)
                    ? "'".addslashes($k)."' => ".self::stringifyDefault($v)
                    : self::stringifyDefault($v);
            }

            return '['.implode(', ', $parts).']';
        }
        if (is_object($value)) {
            return $value::class;
        }

        return (string) var_export($value, true);
    }

    /**
     * Render a ReflectionParameter's type as a string — `string`,
     * `?string`, `int`, `bool`, `array`, `string|null` (union types
     * supported). Returns null when the parameter has no type hint.
     */
    private static function stringifyType(\ReflectionParameter $param): ?string
    {
        $type = $param->getType();
        if ($type === null) {
            return null;
        }
        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();

            // PHP's reflection emits `?T` only when the parameter was
            // declared with the leading-`?` shorthand; `T|null` reads
            // back as a union. Both shapes are semantically identical;
            // we normalize to the `?T` form for prefix-nullable types.
            return ($type->allowsNull() && $name !== 'null' && $name !== 'mixed')
                ? '?'.$name
                : $name;
        }
        if ($type instanceof ReflectionUnionType) {
            $names = array_map(
                fn ($t) => $t instanceof ReflectionNamedType ? $t->getName() : (string) $t,
                $type->getTypes()
            );

            return implode('|', $names);
        }

        // ReflectionIntersectionType (PHP 8.1+) and any future
        // reflection-type subclass — fall back to cast.
        return (string) $type;
    }
}
