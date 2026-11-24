<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use BackedEnum;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Stringable;
use Illuminate\View\Factory;
use ReflectionProperty;
use Throwable;
use UnitEnum;

/**
 * A value a child component takes from its own family through `@aware`, and from no other.
 *
 * Laravel's `@aware` reads a key from the nearest ancestor component whose tag holds it, whatever
 * that component is. A child whose parent was written without the key would take it from an
 * ancestor of another family: the items of a FAQ inside a card written with `:level="4"` would be
 * `h4` headings, and a table inside a page header written with `stack-below` would stack. Read
 * here, a child takes the key from its own tag, else from the nearest component of its family
 * that holds it, and passes over every other ancestor. The search ends at the first of its owners,
 * the component the family belongs to, so a table inside another table's cell keeps its own
 * value. With no member of the family above it, the child was rendered from inside a view that
 * hands the key over as the view's data, and that data is read.
 *
 * A component whose content can hold the child in one shape only answers for the key when it is
 * met first. The rows a cart summary takes in its slot are rendered before the summary's own
 * list, so that list is not above them, and they can only be rows of it.
 *
 * The component stack lives in Laravel's view factory, which keeps it to itself. Where it cannot
 * be read, the value is read as `@aware` reads it.
 *
 * @internal Called from the component views; not a developer-facing API.
 */
final class AwareProp
{
    /**
     * The factory's component stack, the tag data of each component on it, and the data of the
     * view that is rendering, read through reflection.
     *
     * @var list<ReflectionProperty>|null
     */
    private static ?array $properties = null;

    /**
     * The key from the child's own tag, else from the nearest member of its family that holds it.
     *
     * The value is whatever a tag carried, a text, a number, a switch or a list, so the default
     * and the return are `mixed`. An enum or a fluent string is read as its text, and a text
     * written into a tag with Blade's escape undone (asText()).
     *
     * @param  list<string>  $owners  the components the family belongs to, by name; the search ends at the first of them
     * @param  list<string>  $members  the components between the child and its owner that may hold the key as well
     * @param  array<string, string>  $implied  the components whose content holds the child in one shape only, by name, with the value of that shape
     * @param  bool  $ownTag  whether the child rendered through the view factory, so the tag data the factory keeps at its depth is its own; a view livewire/blaze compiled into a function did not, and the data there belongs to a component rendered before it
     */
    public static function read(string $key, array $owners, mixed $default = null, array $members = [], array $implied = [], bool $ownTag = true): mixed
    {
        $state = self::state();

        if ($state === null) {
            return self::asText(app('view')->getConsumableComponentData($key, $default));
        }

        [$stack, $data, $current] = $state;
        $depth = count($stack);
        $own = $ownTag ? ($data[$depth] ?? null) : null;

        if (is_array($own) && array_key_exists($key, $own)) {
            return self::asText($own[$key]);
        }

        $family = false;

        for ($i = $depth - 1; $i >= 0; $i--) {
            $entry = $stack[$i] ?? null;
            // An anonymous component stands on the stack as its view's name, a class component as
            // the view its `render()` returned, which carries the same name.
            $name = match (true) {
                is_string($entry) => UnboundModel::componentOf($entry),
                $entry instanceof ViewContract => UnboundModel::componentOf($entry->name()),
                default => null,
            };

            if ($name !== null && array_key_exists($name, $implied)) {
                return $implied[$name];
            }

            $owner = $name !== null && in_array($name, $owners, true);

            if (! $owner && ($name === null || ! in_array($name, $members, true))) {
                continue;
            }

            $family = true;
            $tag = $data[$i] ?? null;

            if (is_array($tag) && array_key_exists($key, $tag)) {
                return self::asText($tag[$key]);
            }

            if ($owner) {
                return $default;
            }
        }

        return ! $family && array_key_exists($key, $current) ? self::asText($current[$key], escaped: false) : $default;
    }

    /**
     * A value from tag data, read as the view composer reads a prop (Support\PropText): an enum
     * as the text it stands for, a backed one as its value and a pure one as its name, and a
     * fluent string as its string. The composer reads the data of the view that renders, so a
     * parent bound `:variant="$style"` reads the text, while the tag data its children read
     * here holds the value as it was bound, and a child comparing it with `===` or handing it
     * to a parameter typed for text would take the enum for no value or end the page. `mixed`
     * for the reason read() gives.
     *
     * A text from a tag is read with Blade's escape undone, as the composer reads one written on
     * a tag (PropText::text()): a value echoed into the tag, `selected="{{ $key }}"`, is held
     * there escaped, so a key with an ampersand never matched the child's own value, which the
     * composer had read. The data of the view that renders is read without that step: a
     * component's has passed the composer already, and a page's never passed Blade's escape.
     */
    private static function asText(mixed $value, bool $escaped = true): mixed
    {
        if ($value instanceof UnitEnum) {
            return $value instanceof BackedEnum ? $value->value : $value->name;
        }

        if ($value instanceof Stringable) {
            return $value->toString();
        }

        return $escaped ? PropText::text($value) : $value;
    }

    /**
     * Whether one of the owners is above the child on the component stack.
     *
     * A child can only take its family's values while the family renders around it. A component
     * Livewire renders on its own, on an update, renders its children with no owner above them,
     * and every read answers with its default. A child that leaves out what its owner can never
     * need asks this first, and writes everything where the answer is not true: false where no
     * owner is on the stack, null where the stack cannot be read.
     *
     * @param  list<string>  $owners  the components the family belongs to, by name
     */
    public static function within(array $owners): ?bool
    {
        $state = self::state();

        if ($state === null) {
            return null;
        }

        foreach ($state[0] as $entry) {
            $name = match (true) {
                is_string($entry) => UnboundModel::componentOf($entry),
                $entry instanceof ViewContract => UnboundModel::componentOf($entry->name()),
                default => null,
            };

            if ($name !== null && in_array($name, $owners, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The stack, the tag data by depth and the rendering view's data, or null where the factory
     * does not keep them as this reads them.
     *
     * @return array{0: array<int, mixed>, 1: array<int, mixed>, 2: array<array-key, mixed>}|null
     */
    private static function state(): ?array
    {
        try {
            $factory = app('view');
            self::$properties ??= array_map(
                static fn (string $name): ReflectionProperty => new ReflectionProperty(Factory::class, $name),
                ['componentStack', 'componentData', 'currentComponentData'],
            );
            [$stack, $data, $current] = array_map(static fn (ReflectionProperty $property): mixed => $property->getValue($factory), self::$properties);
        } catch (Throwable) {
            return null;
        }

        return is_array($stack) && is_array($data) && is_array($current) ? [$stack, $data, $current] : null;
    }
}
