<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\View\ComponentAttributeBag;
use Stringable;
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
     * The families each root of the kit's utilities sets (`py` of `py-2`), and the family of the
     * display and of the position property, for a caller's class the cascade does not hold. Read
     * with the table.
     *
     * @var array<string, list<int>>|null
     */
    private static ?array $roots = null;

    /** @var array<string, int>|null */
    private static ?array $keywordFamilies = null;

    /**
     * Class strings already mapped, by the string they were given and whether they belong to a field.
     *
     * @var array<string, string>
     */
    private static array $mapped = [];

    /**
     * Each bundle's short name with the short names of the utilities it stands for, in the order
     * the table lists them; read with the table.
     *
     * @var array<string, list<string>>|null
     */
    private static ?array $bundles = null;

    /** @var array<string, string> */
    private static array $bundled = [];

    /**
     * The table turned round, short class => utility, built on the first question. Each short class
     * stands for one utility, so nothing is lost in the turn.
     *
     * @var array<string, string>|null
     */
    private static ?array $utilities = null;

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
     * @param  list<int>  $families  The property families a caller's class the cascade does not hold
     *                               may set: every class of the string in one of them keeps its name.
     */
    public static function map(string $classes, bool $field = false, array $beside = [], array $families = []): string
    {
        if (! self::enabled() || trim($classes) === '') {
            return $classes;
        }

        $key = ($field ? "f\0" : "\0").$classes."\0".implode(' ', $beside)."\0".implode(',', $families);

        if (isset(self::$mapped[$key])) {
            return self::$mapped[$key];
        }

        $table = self::table();
        $named = self::namesKept(preg_split('/\s+/', trim($classes)) ?: [], $table, $field ? (self::$fields ?? []) : [], $beside, $families);

        return self::$mapped[$key] = (string) preg_replace_callback(
            '/\S+/',
            static fn (array $class): string => isset($named[$class[0]]) ? $class[0] : ($table[$class[0]] ?? $class[0]),
            $classes,
        );
    }

    /**
     * A class string mapped as map() maps it, with each bundle whose utilities it holds under their
     * short names written as the bundle instead.
     *
     * A bundle stands for a fixed list of utilities, such as a button's base block or the classes of
     * an intent on a surface, and the compact sheet lists its short name in the rule of each of them,
     * so an element that carries the bundle looks as one that carries the list. A bundle is written
     * only where every utility of it came out under its short name: one that kept its name beside a
     * class of the caller's keeps the list as it is. The bundles are taken in the table's order, each
     * in the place of its first utility. A view asks for them on an element whose utilities no script
     * takes away by name, since the bundle would keep what the script took. While the switch is off,
     * the string comes back as it is.
     *
     * @param  list<string>  $beside  As for map().
     */
    public static function bundled(string $classes, array $beside = []): string
    {
        $mapped = self::map($classes, false, $beside);

        if (! self::enabled() || trim($mapped) === '') {
            return $mapped;
        }

        if (isset(self::$bundled[$mapped])) {
            return self::$bundled[$mapped];
        }

        self::table();
        $tokens = preg_split('/\s+/', trim($mapped), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $free = array_fill_keys($tokens, true);
        $taken = [];

        foreach (self::$bundles ?? [] as $bundle => $members) {
            foreach ($members as $member) {
                if (! isset($free[$member])) {
                    continue 2;
                }
            }

            foreach ($members as $index => $member) {
                unset($free[$member]);
                $taken[$member] = $index === 0 ? $bundle : '';
            }
        }

        if ($taken === []) {
            return self::$bundled[$mapped] = $mapped;
        }

        $out = [];

        foreach ($tokens as $token) {
            if (! isset($taken[$token])) {
                $out[] = $token;
            } elseif ($taken[$token] !== '') {
                $out[] = $taken[$token];
            }
        }

        return self::$bundled[$mapped] = implode(' ', $out);
    }

    /**
     * A class string with every short class of the table written as the utility it stands for: the
     * other direction of map().
     *
     * A class that is no short name stays as written, a utility map() left under its own name among
     * them, and so does the whitespace between the classes, so a string map() wrote comes back as it
     * was given to map(). A bundle comes back as the utilities it stands for, which bundled() wrote
     * it in place of. While the switch is off, the string comes back as it is.
     */
    public static function spelledOut(string $classes): string
    {
        if (! self::enabled() || trim($classes) === '') {
            return $classes;
        }

        $utilities = self::$utilities ??= array_flip(self::table());
        $bundles = self::$bundles ?? [];

        // A bundle is written out as the utilities it stands for, so a test that looks for one of
        // them finds it on an element that carries the bundle.
        return (string) preg_replace_callback(
            '/\S+/',
            static fn (array $class): string => isset($bundles[$class[0]])
                ? implode(' ', array_map(static fn (string $short): string => $utilities[$short] ?? $short, $bundles[$class[0]]))
                : ($utilities[$class[0]] ?? $class[0]),
            $classes,
        );
    }

    /**
     * The classes of one string written under their own names: each class the table does not
     * rename, each one holding a part the stylesheet asks of a field by name, and each utility the
     * table would rename that Tailwind orders after one of those with a property of the same family.
     * A utility kept for that reason can keep another, so the set grows until it stands.
     *
     * A class beside the string whose place the table does not hold arrives as the families it may
     * set, and every utility of the string in one of them keeps its name without regard to order.
     *
     * @param  list<string>  $classes
     * @param  array<string, string>  $table
     * @param  list<string>  $fields
     * @param  list<string>  $beside
     * @param  list<int>  $families
     * @return array<string, true>
     */
    private static function namesKept(array $classes, array $table, array $fields, array $beside, array $families = []): array
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

        if ($families !== []) {
            foreach (array_keys($renamed) as $class) {
                if (array_intersect(array_slice($cascade[$class], 1), $families) !== []) {
                    $named[$class] = true;
                    unset($renamed[$class]);
                }
            }
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
     * @param  string|array<array-key, string|bool|int|float|Stringable|null>|null  $classList
     * @param  list<string>  $beside  As for map().
     * @param  list<int>  $families  As for map().
     * @return string|array<array-key, string|bool|int|float|Stringable|null>|null
     */
    public static function list(string|array|null $classList, bool $field = false, array $beside = [], array $families = []): string|array|null
    {
        if (! self::enabled() || $classList === null) {
            return $classList;
        }

        if (is_string($classList)) {
            return self::map($classList, $field, $beside, $families);
        }

        $mapped = [];

        foreach ($classList as $key => $value) {
            if (is_int($key)) {
                $mapped[] = is_string($value) ? self::map($value, $field, $beside, $families) : $value;
            } else {
                $mapped[self::map($key, $field, $beside, $families)] = $value;
            }
        }

        return $mapped;
    }

    /**
     * What `$bag->class($classList)` does, as the callback a view hands the bag's `when()`: the list
     * is mapped as list() maps it, beside the classes the bag already holds.
     *
     * Those are the caller's. They keep their names and come from the application's sheet, after
     * the compact one, so a utility of the list that Tailwind orders after one of them with a
     * property of the same family keeps its name as well and still wins where it does without
     * compact classes: `@5xl/wk-table:hidden` beside a caller's `block`. The bag holds them only
     * once the call is made on it, which is why a view hands over a callback rather than the mapped
     * list. A class of the caller's that the kit never writes is read by what it may set
     * (callerClasses()). While the switch is off, the callback merges the list as it came.
     *
     * @param  string|array<array-key, string|bool|int|float|Stringable|null>|null  $classList
     * @param  list<string>  $beside  As for map().
     * @return Closure(ComponentAttributeBag): ComponentAttributeBag
     */
    public static function bagClass(string|array|null $classList, bool $field = false, array $beside = []): Closure
    {
        if (! self::enabled()) {
            return static fn (ComponentAttributeBag $bag): ComponentAttributeBag => $bag->class($classList);
        }

        return static function (ComponentAttributeBag $bag) use ($classList, $field, $beside): ComponentAttributeBag {
            $own = $bag->get('class');
            [$utilities, $families] = self::callerClasses(is_string($own) || $own instanceof Stringable ? (string) $own : '');

            return $bag->class(self::list($classList, $field, [...$beside, ...$utilities], $families));
        };
    }

    /**
     * What a caller's classes can keep beside them: the kit's utilities among them, whose place in
     * Tailwind's order the table holds, and the families of the properties the others may set.
     *
     * A class the kit never writes, `py-2` or `text-brand`, has no place in the table, so it is read
     * by its root, the run of its parts before a hyphen that the kit's own utilities share (`py`),
     * and a display or a position keyword by its property. Its place is not known, so every class
     * of the list in one of its families keeps its name. A class whose root no utility of the kit
     * has, `return-row`, sets nothing the kit's do. Only the kit's utilities and family numbers
     * come back, which keeps the caller's own names out of the mapping's keys.
     *
     * @return array{0: list<string>, 1: list<int>}
     */
    private static function callerClasses(string $classes): array
    {
        self::table();
        $cascade = self::$cascade ?? [];
        $utilities = [];
        $families = [];

        foreach (array_unique(preg_split('/\s+/', trim($classes), -1, PREG_SPLIT_NO_EMPTY) ?: []) as $class) {
            if (isset($cascade[$class])) {
                $utilities[] = $class;
            } else {
                array_push($families, ...self::familiesOf($class));
            }
        }

        $families = array_values(array_unique($families));
        sort($families);

        return [$utilities, $families];
    }

    /**
     * The families a class the kit never writes may set: those of its display or position keyword,
     * or those of the longest root the kit's utilities share with it.
     *
     * @return list<int>
     */
    private static function familiesOf(string $class): array
    {
        $parts = self::partsOf($class);

        if ($parts === []) {
            return [];
        }

        $utility = implode('-', $parts);
        $keyword = match (true) {
            preg_match(UtilityClasses::SETS_DISPLAY, $utility) === 1 => 'display',
            preg_match(UtilityClasses::POSITION, $utility) === 1 => 'position',
            default => null,
        };

        if ($keyword !== null) {
            return isset(self::$keywordFamilies[$keyword]) ? [self::$keywordFamilies[$keyword]] : [];
        }

        for ($end = count($parts) - 1; $end >= 1; $end--) {
            $root = implode('-', array_slice($parts, 0, $end));

            if (isset(self::$roots[$root])) {
                return self::$roots[$root];
            }
        }

        return [];
    }

    /**
     * The parts of a class between its hyphens, read as the build reads a utility's: without its
     * variants, its important marker and the minus of a negative value, a hyphen or a colon inside
     * brackets or parentheses splitting nothing. An arbitrary property gives none.
     *
     * @return list<string>
     */
    private static function partsOf(string $class): array
    {
        $segments = self::topLevelSplit($class, ':');
        $utility = (string) preg_replace('/^-/', '', trim((string) end($segments), '!'));

        return $utility === '' || str_starts_with($utility, '[') ? [] : self::topLevelSplit($utility, '-');
    }

    /**
     * A string split at each separator that stands outside brackets and parentheses.
     *
     * @return list<string>
     */
    private static function topLevelSplit(string $value, string $separator): array
    {
        $parts = [];
        $depth = 0;
        $start = 0;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];

            if ($char === '[' || $char === '(') {
                $depth++;
            } elseif ($char === ']' || $char === ')') {
                $depth = max(0, $depth - 1);
            } elseif ($char === $separator && $depth === 0) {
                $parts[] = substr($value, $start, $i - $start);
                $start = $i + 1;
            }
        }

        $parts[] = substr($value, $start);

        return $parts;
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

    /**
     * Whether a class is a kit utility the table writes under a short name. Read while a view is
     * compiled, for the classes an element's bindings set while the page runs, which keep their
     * names on that element.
     */
    public static function hasShortName(string $class): bool
    {
        return isset(self::table()[$class]);
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
            /** @var array{sources: string, fields: list<string>, classes: array<string, string>, cascade: array<string, list<int>>, roots?: array<string, list<int>>, keyword_families?: array<string, int>, bundles?: array<string, list<string>>} $table */
            $table = require \dirname(__DIR__, 2).'/resources/catalog/compact-classes.php';
            self::$table = $table['classes'];
            self::$fields = $table['fields'];
            self::$cascade = $table['cascade'];
            self::$roots = $table['roots'] ?? [];
            self::$keywordFamilies = $table['keyword_families'] ?? [];
            self::$bundles = $table['bundles'] ?? [];
        }

        return self::$table;
    }
}
