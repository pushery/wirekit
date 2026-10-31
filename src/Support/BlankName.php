<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\View\ComponentAttributeBag;
use Pushery\WireKit\ComponentRegistry;
use Stringable;
use Throwable;

/**
 * A blank name given to a component that names itself.
 *
 * A blank name is no name: an empty or whitespace-only `aria-label` leaves an element without one,
 * and on an icon button or a navigation landmark that is a control or a landmark nobody can tell
 * apart. Such a value arrives easily, as `:label="$section->title"` over a record without a title.
 * A component that carries a name of its own, a `label`, an `ariaLabel` or another `…Label` whose
 * default is a text such as "Main", "Dark mode" or "Add filter", keeps that name when it is handed a
 * blank one. A component whose default is `null` or empty is left alone: there a blank value can
 * mean that no name is wanted.
 *
 * @internal Called from the component views and the view composer the service provider registers;
 *           not a developer-facing API.
 */
final class BlankName
{
    /** @var array<string, list<string>> The name props with a text default, by component. */
    private static array $named = [];

    /**
     * The first candidate with text, read as a name is read, or null when none has any.
     */
    public static function first(mixed ...$candidates): ?string
    {
        foreach ($candidates as $candidate) {
            // A slot or an HtmlString names by its text, never by its markup.
            if ($candidate instanceof Htmlable) {
                $candidate = SlotContent::textOf($candidate);
            }

            if ((is_string($candidate) || $candidate instanceof Stringable || is_int($candidate) || is_float($candidate))
                && trim((string) $candidate) !== '') {
                return (string) $candidate;
            }
        }

        return null;
    }

    /**
     * Before `@props` reads a component's view: a blank `label` or `ariaLabel` of a component that
     * names itself becomes `null`, which `@props` answers with the default. The view's data and the
     * attribute bag both carry the value, so both lose it.
     */
    public static function normalize(View $view): void
    {
        $component = UnboundModel::componentOf($view->name());

        if ($component === null || str_starts_with($component, 'partials.')) {
            return;
        }

        $attributes = $view->getData()['attributes'] ?? null;

        if (! $attributes instanceof ComponentAttributeBag) {
            return;
        }

        foreach (self::namedBy($component) as $prop) {
            $spellings = array_values(array_unique([$prop, Str::kebab($prop)]));
            $given = null;

            foreach ($spellings as $spelling) {
                if ($attributes->has($spelling)) {
                    $given = $attributes->get($spelling);
                }
            }

            if (! is_string($given) || trim($given) !== '') {
                continue;
            }

            $attributes->setAttributes(array_diff_key($attributes->getAttributes(), array_flip($spellings)));
            $view->with($prop, null);
        }
    }

    /**
     * The name props of a component whose default is a text, read once from its `@props`.
     *
     * @return list<string>
     */
    private static function namedBy(string $component): array
    {
        if (array_key_exists($component, self::$named)) {
            return self::$named[$component];
        }

        try {
            $props = ComponentRegistry::extractProps($component);
        } catch (Throwable) {
            $props = [];
        }

        $named = [];

        foreach ($props as $prop) {
            $name = (string) $prop['name'];
            $default = trim((string) ($prop['default'] ?? 'null'));
            // A text, read through the catalog or written as a literal. `false` on a `hideLabel` is a
            // switch, not a name.
            $text = preg_match('/__\(|^([\'"]).+\1$/s', $default) === 1;

            if (($name === 'label' || str_ends_with($name, 'Label')) && $text) {
                $named[] = $name;
            }
        }

        return self::$named[$component] = $named;
    }
}
