<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Stringable;

use function Illuminate\Support\enum_value;

/**
 * The sources an answer cites, normalized for the chip row of `<x-wirekit::assistant-message>`.
 *
 * A retrieval layer hands back whatever it hands back: a list of titles, a list of arrays with a
 * link and a snippet, a list of models. This turns all three into one shape so the template has no
 * branching in it, and numbers them in the order they were given — the number IS the marker the
 * reader sees next to the answer, so it follows the list rather than anything inside an entry.
 *
 * An entry without a label is dropped rather than rendered. A chip whose accessible name is empty
 * is a button a screen reader announces as nothing; a bare number tells its user even less than
 * the silence would.
 */
final class CitationList
{
    /**
     * `mixed` because the prop is whatever the application passed: a list, a Collection, a
     * paginator, a single string, null. Narrowing it to `iterable|null` would make the
     * component throw on the shapes it is documented to survive, and the first line below is
     * the survival — anything that cannot be walked cites nothing.
     *
     * @return list<array{number: int, label: string, href: ?string, snippet: ?string}>
     */
    public static function from(mixed $citations): array
    {
        if (! is_iterable($citations)) {
            return [];
        }

        $normalized = [];

        foreach ($citations as $citation) {
            $label = self::text(self::field($citation, 'label'));

            // A plain string is the shortest spelling of a citation: a title and nothing else.
            if ($label === null && (is_string($citation) || $citation instanceof Stringable)) {
                $label = self::text($citation);
            }

            if ($label === null) {
                continue;
            }

            $normalized[] = [
                'number' => count($normalized) + 1,
                'label' => $label,
                'href' => self::text(self::field($citation, 'href')),
                'snippet' => self::text(self::field($citation, 'snippet')),
            ];
        }

        return $normalized;
    }

    /**
     * One field of an entry, whichever shape the caller used.
     *
     * `data_get` reads an array key and an object property with the same call, which is what lets
     * an application pass its own model straight through instead of mapping it first.
     *
     * Both `mixed` types come from that call: the entry is one element of an unconstrained
     * iterable, and `data_get` is declared to return `mixed` upstream. Either one narrowed here
     * would be a claim about a value this class never sees the origin of.
     */
    private static function field(mixed $citation, string $key): mixed
    {
        if (is_string($citation) || $citation instanceof Stringable) {
            return null;
        }

        return data_get($citation, $key);
    }

    /**
     * A trimmed string, or null for anything that is not usable text — including the empty string,
     * so `['label' => '']` is treated as the absent label it is rather than as a nameless chip.
     *
     * `mixed` is the input side of exactly that sentence: the method's purpose is to decide
     * whether an unknown value is usable text, so a parameter type that already excluded the
     * unusable ones would leave it with nothing to decide.
     */
    private static function text(mixed $value): ?string
    {
        if ($value instanceof Stringable) {
            $value = (string) $value;
        }

        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }

        if (! is_string($value)) {
            $value = enum_value($value);
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
