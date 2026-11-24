<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * A value as a component's script turns it into text with `String()`, reading the JSON the value
 * travels to the page as.
 *
 * The server writes some of the text a script writes, for the moment before the script starts and
 * for a page that runs none, so the words do not change as the script takes over: the chips of a
 * filter builder (Support\FilterChipText) and the cells of a data table (Support\DataTableStart).
 * The cases of tests/fixtures/filter-chip-cases.json and tests/fixtures/data-table-start-cases.json
 * hold both halves to it.
 */
final class ScriptText
{
    /**
     * `true`, `false` and `null` as those words, a whole number as written, a fraction as JSON writes
     * it, a list joined by commas with nothing for an entry that is null, an enum by its value and
     * any other object as the script names one.
     */
    public static function of(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_string($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            // JSON writes the shortest form that reads back as the same number, as the script's
            // String() does, where PHP's own cast rounds to fourteen digits; a whole one loses
            // its `.0`, as it does in the script.
            return (string) preg_replace('/\.0$/', '', (string) json_encode($value));
        }

        if (is_array($value)) {
            if (! array_is_list($value)) {
                return '[object Object]';
            }

            $parts = [];
            foreach ($value as $entry) {
                $parts[] = $entry === null ? '' : self::of($entry);
            }

            return implode(',', $parts);
        }

        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        return '[object Object]';
    }
}
