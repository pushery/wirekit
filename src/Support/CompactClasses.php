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
 * A utility that keeps its name comes from the application's sheet, linked after the compact one.
 * On an element that carries one beside a utility Tailwind orders after it with a property of the
 * same family (`hidden` and `md:block`), the later utility keeps its name as well, so both come from
 * the application's sheet in Tailwind's order and the later one still wins where it does without
 * compact classes. The element's other classes reach each call as `$beside`: a view writes a class
 * attribute in parts, a literal beside an echo, and a script adds a class while the page runs.
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
     * Each utility's place in Tailwind's order, then the families of the properties it sets, by
     * number: utility => [place, family, ...]. Read once per process.
     *
     * @var array<string, list<int>>|null
     */
    private static ?array $cascade = null;

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
     *
     * @param  list<string>  $beside  The element's other classes that keep their names, from the
     *                                other parts of its class attribute and the classes a script
     *                                sets on it while the page runs.
     */
    public static function map(string $classes, bool $field = false, array $beside = []): string
    {
        if (! self::enabled() || trim($classes) === '') {
            return $classes;
        }

        $key = ($field ? "f\0" : "\0").$classes."\0".implode(' ', $beside);

        if (isset(self::$mapped[$key])) {
            return self::$mapped[$key];
        }

        $table = self::table();
        $named = self::namesKept(preg_split('/\s+/', trim($classes)) ?: [], $table, $field ? (self::$fields ?? []) : [], $beside);

        return self::$mapped[$key] = (string) preg_replace_callback(
            '/\S+/',
            static fn (array $class): string => isset($named[$class[0]]) ? $class[0] : ($table[$class[0]] ?? $class[0]),
            $classes,
        );
    }

    /**
     * The classes of one string written under their own names: each class the table does not
     * rename, each one holding a part the stylesheet asks of a field by name, and each utility the
     * table would rename that Tailwind orders after one of those with a property of the same family.
     * A utility kept for that reason can keep another, so the set grows until it stands.
     *
     * @param  list<string>  $classes
     * @param  array<string, string>  $table
     * @param  list<string>  $fields
     * @param  list<string>  $beside
     * @return array<string, true>
     */
    private static function namesKept(array $classes, array $table, array $fields, array $beside): array
    {
        $cascade = self::$cascade ?? [];
        $named = [];
        $renamed = [];

        foreach ($classes as $class) {
            $kept = ! isset($table[$class]);

            foreach ($fields as $part) {
                $kept = $kept || str_contains($class, $part);
            }

            if ($kept) {
                $named[$class] = true;
            } elseif (isset($cascade[$class])) {
                $renamed[$class] = true;
            }
        }

        foreach ($beside as $class) {
            $named[$class] = true;
        }

        do {
            $grew = false;

            foreach (array_keys($renamed) as $class) {
                foreach (array_keys($named) as $before) {
                    if (isset($cascade[$before])
                        && $cascade[$before][0] < $cascade[$class][0]
                        && array_intersect(array_slice($cascade[$before], 1), array_slice($cascade[$class], 1)) !== []) {
                        $named[$class] = true;
                        unset($renamed[$class]);
                        $grew = true;

                        break;
                    }
                }
            }
        } while ($grew);

        return $named;
    }

    /**
     * A class list as `ComponentAttributeBag::class()` and `@class` take it, mapped.
     *
     * A string is mapped as a whole. In an array, an entry under a numeric key is a class string
     * and an entry under a string key is a class string with its condition, so the value is mapped
     * in the first case and the key in the second; the order and the conditions stay.
     *
     * @param  string|array<array-key, string|bool|int|float|\Stringable|null>|null  $classList
     * @param  list<string>  $beside  As for map().
     * @return string|array<array-key, string|bool|int|float|\Stringable|null>|null
     */
    public static function list(string|array|null $classList, bool $field = false, array $beside = []): string|array|null
    {
        if (! self::enabled() || $classList === null) {
            return $classList;
        }

        if (is_string($classList)) {
            return self::map($classList, $field, $beside);
        }

        $mapped = [];

        foreach ($classList as $key => $value) {
            if (is_int($key)) {
                $mapped[] = is_string($value) ? self::map($value, $field, $beside) : $value;
            } else {
                $mapped[self::map($key, $field, $beside)] = $value;
            }
        }

        return $mapped;
    }

    /**
     * The value of an echo inside a `class` attribute: mapped when it is a string, returned as it
     * came otherwise, so `{{ }}` still treats an Htmlable, a number or `null` the way it always has.
     *
     * The parameter and the return are `mixed` because an echo accepts any value a template hands it.
     *
     * @param  list<string>  $beside  As for map().
     */
    public static function value(mixed $value, bool $field = false, array $beside = []): mixed
    {
        return is_string($value) ? self::map($value, $field, $beside) : $value;
    }

    /**
     * Whether a class is a kit utility written under its own name, on a form field (`$field`) a
     * class holding a part the stylesheet asks of fields by name included. Read while a view is
     * compiled, for the classes an element carries beside each class list it maps.
     */
    public static function keepsItsName(string $class, bool $field = false): bool
    {
        $table = self::table();

        if (! isset((self::$cascade ?? [])[$class])) {
            return false;
        }

        if (! isset($table[$class])) {
            return true;
        }

        foreach ($field ? (self::$fields ?? []) : [] as $part) {
            if (str_contains($class, $part)) {
                return true;
            }
        }

        return false;
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
            /** @var array{sources: string, fields: list<string>, classes: array<string, string>, cascade: array<string, list<int>>} $table */
            $table = require \dirname(__DIR__, 2).'/resources/catalog/compact-classes.php';
            self::$table = $table['classes'];
            self::$fields = $table['fields'];
            self::$cascade = $table['cascade'];
        }

        return self::$table;
    }
}
