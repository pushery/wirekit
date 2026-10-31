<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * The ids of a list a component hands its script, as the script can hand them back.
 *
 * A script reads a number in JSON as a double, which holds every integer up to 2^53 - 1 exactly
 * and only some beyond it. An id of 64 bits, as a snowflake id is, would come back from a click
 * or a selection as another number, and the application would act on another record or none.
 * Written as its digits it comes back as it went.
 *
 * Only such an id changes. Every smaller integer stays a number, so a selection or an event keeps
 * the type it had for any id a database counts up to, and every other field keeps its type: a
 * quantity above 2^53 loses its last digits as a number and still draws, where as a text it
 * would not.
 *
 * @internal Called from the component views; not a developer-facing API.
 */
final class ExactIds
{
    /** The largest integer a script's number holds exactly, 2^53 - 1. */
    private const LARGEST_EXACT = 9007199254740991;

    /**
     * `$records` with the `$field` of each record written as its digits where it is an integer a
     * script cannot hold exactly. A record that is no array, and one without the field, stays.
     *
     * @param  array<array-key, mixed>  $records
     * @return array<array-key, mixed>
     */
    public static function in(array $records, string $field): array
    {
        foreach ($records as $index => $record) {
            if (! is_array($record)) {
                continue;
            }

            $id = $record[$field] ?? null;

            if (is_int($id) && ($id > self::LARGEST_EXACT || $id < -self::LARGEST_EXACT)) {
                $record[$field] = (string) $id;
                $records[$index] = $record;
            }
        }

        return $records;
    }
}
