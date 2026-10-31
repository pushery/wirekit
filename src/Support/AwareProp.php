<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\View\Factory;
use ReflectionProperty;
use Throwable;

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
     * and the return are `mixed`.
     *
     * @param  list<string>  $owners  the components the family belongs to, by name; the search ends at the first of them
     * @param  list<string>  $members  the components between the child and its owner that may hold the key as well
     * @param  array<string, string>  $implied  the components whose content holds the child in one shape only, by name, with the value of that shape
     */
    public static function read(string $key, array $owners, mixed $default = null, array $members = [], array $implied = []): mixed
    {
        $state = self::state();

        if ($state === null) {
            return app('view')->getConsumableComponentData($key, $default);
        }

        [$stack, $data, $current] = $state;
        $depth = count($stack);
        $own = $data[$depth] ?? null;

        if (is_array($own) && array_key_exists($key, $own)) {
            return $own[$key];
        }

        $family = false;

        for ($i = $depth - 1; $i >= 0; $i--) {
            $entry = $stack[$i] ?? null;
            $name = is_string($entry) ? UnboundModel::componentOf($entry) : null;

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
                return $tag[$key];
            }

            if ($owner) {
                return $default;
            }
        }

        return ! $family && array_key_exists($key, $current) ? $current[$key] : $default;
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
