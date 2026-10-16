<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\Contracts\Container\Container;
use WeakReference;

/**
 * Compact classes: the kit's Tailwind utilities under short names, while `wirekit.styles.compact`
 * is on.
 *
 * The table is built with the package from the kit's Tailwind sources: every utility the kit's
 * views write is compiled, and the class of each rule renamed to a short one in
 * `dist/wirekit-compact.css`. A short class carries exactly the rules of its utility, in the
 * utilities' order, so writing one for the other changes nothing on the page but its weight.
 *
 * The names change only where a class is written into the page: a `class` attribute of the kit's
 * views, the defaults a view hands its attribute bag, and the lists `x-wk-current` moves. A class
 * string stays a list of utilities everywhere before that, so a view or a personalization closure
 * can still read, filter or extend it. While the switch is off, every method returns what it was
 * given.
 *
 * @internal Called from the kit's compiled views (Support\CompactClassAttributes) and from the
 * classes that write class lists into the page.
 */
final class CompactClasses
{
    /**
     * The application the switch was read for, and what it said. Every rewritten view asks once per
     * render, many times on a page, so the answer is kept until the application changes (a test, an
     * Octane request) or the per-request reset clears it (forget()).
     *
     * @var WeakReference<Container>|null
     */
    private static ?WeakReference $readFor = null;

    private static bool $on = false;

    /**
     * The table, utility => short class, read once per process.
     *
     * @var array<string, string>|null
     */
    private static ?array $table = null;

    /**
     * The parts of a class the kit's stylesheet asks of a form field by name, read once per process.
     *
     * @var list<string>|null
     */
    private static ?array $fields = null;

    /**
     * Class strings already mapped, by the string they were given and whether they belong to a field.
     *
     * @var array<string, string>
     */
    private static array $mapped = [];

    /**
     * Whether the switch is on; `off` and `no` from the environment read as off. Read once per
     * request and per queued job: a value set later in the same request applies from the next.
     */
    public static function enabled(): bool
    {
        $container = app();

        if (self::$readFor?->get() !== $container) {
            self::$readFor = WeakReference::create($container);
            self::$on = BooleanProp::from(config('wirekit.styles.compact', false), false);
        }

        return self::$on;
    }

    /** Read the switch again on the next question: after each request and before each queued job. */
    public static function forget(): void
    {
        self::$readFor = null;
    }

    /**
     * A class string with every utility the table knows under its short name.
     *
     * Only the classes change: the whitespace between them stays as it was, so a part of a longer
     * attribute value keeps its place next to the parts around it. Anything the table does not know
     * stays as written, a marker such as `group`, a class of the kit's own stylesheet, a utility of
     * the application. On a form field (`$field`, an input, a select or a textarea) a class holding
     * a part the stylesheet asks of fields by name keeps that name as well.
     */
    public static function map(string $classes, bool $field = false): string
    {
        if (! self::enabled() || trim($classes) === '') {
            return $classes;
        }

        $key = ($field ? "f\0" : "\0").$classes;

        if (isset(self::$mapped[$key])) {
            return self::$mapped[$key];
        }

        $table = self::table();
        $fields = $field ? (self::$fields ?? []) : [];

        return self::$mapped[$key] = (string) preg_replace_callback(
            '/\S+/',
            static function (array $class) use ($table, $fields): string {
                foreach ($fields as $part) {
                    if (str_contains($class[0], $part)) {
                        return $class[0];
                    }
                }

                return $table[$class[0]] ?? $class[0];
            },
            $classes,
        );
    }

    /**
     * A class list as `ComponentAttributeBag::class()` and `@class` take it, mapped.
     *
     * A string is mapped as a whole. In an array, an entry under a numeric key is a class string
     * and an entry under a string key is a class string with its condition, so the value is mapped
     * in the first case and the key in the second; the order and the conditions stay.
     *
     * @param  string|array<array-key, string|bool|int|float|\Stringable|null>|null  $classList
     * @return string|array<array-key, string|bool|int|float|\Stringable|null>|null
     */
    public static function list(string|array|null $classList, bool $field = false): string|array|null
    {
        if (! self::enabled() || $classList === null) {
            return $classList;
        }

        if (is_string($classList)) {
            return self::map($classList, $field);
        }

        $mapped = [];

        foreach ($classList as $key => $value) {
            if (is_int($key)) {
                $mapped[] = is_string($value) ? self::map($value, $field) : $value;
            } else {
                $mapped[self::map($key, $field)] = $value;
            }
        }

        return $mapped;
    }

    /**
     * The value of an echo inside a `class` attribute: mapped when it is a string, returned as it
     * came otherwise, so `{{ }}` still treats an Htmlable, a number or `null` the way it always has.
     *
     * The parameter and the return are `mixed` because an echo accepts any value a template hands it.
     */
    public static function value(mixed $value, bool $field = false): mixed
    {
        return is_string($value) ? self::map($value, $field) : $value;
    }

    /** Whether an element of this name is a form field, whose field-only classes keep their names. */
    public static function isField(string $tag): bool
    {
        return in_array(strtolower($tag), ['input', 'select', 'textarea'], true);
    }

    /** @return array<string, string> */
    private static function table(): array
    {
        if (self::$table === null) {
            /** @var array{sources: string, fields: list<string>, classes: array<string, string>} $table */
            $table = require \dirname(__DIR__, 2).'/resources/catalog/compact-classes.php';
            self::$table = $table['classes'];
            self::$fields = $table['fields'];
        }

        return self::$table;
    }
}
