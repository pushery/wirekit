<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * The text on the chip of an active filter in a filter builder: the field's label, the operator's
 * and, unless the operator compares against nothing, the value as it reads.
 *
 * The script draws the chips and writes this text as their accessible name, `chipText()` in
 * resources/js/components/filter-builder.js. The server writes the same text on the chips a page
 * loads with, for the moment before the script starts and for a page that runs none, so a chip
 * does not change its words as the script takes over. Both halves are held to the cases of
 * tests/fixtures/filter-chip-cases.json.
 */
final class FilterChipText
{
    /**
     * The operators a field offers when it names none of its own, by the field's type, each with
     * the key of the word that labels it. A relation symbol labels itself: `≥` reads the same in
     * every language. A type this list does not know offers the operators of `text`.
     *
     * @var array<string, list<array{op: string, word?: string, label?: string}>>
     */
    public const OPERATORS = [
        'text' => [
            ['op' => 'contains', 'word' => 'contains'],
            ['op' => 'equals', 'word' => 'is'],
            ['op' => 'starts', 'word' => 'startsWith'],
            ['op' => 'ends', 'word' => 'endsWith'],
        ],
        'number' => [
            ['op' => 'eq', 'label' => '='],
            ['op' => 'gt', 'label' => '>'],
            ['op' => 'lt', 'label' => '<'],
            ['op' => 'gte', 'label' => '≥'],
            ['op' => 'lte', 'label' => '≤'],
        ],
        'select' => [
            ['op' => 'is', 'word' => 'is'],
            ['op' => 'isnot', 'word' => 'isNot'],
        ],
        'date' => [
            ['op' => 'on', 'word' => 'on'],
            ['op' => 'before', 'word' => 'before'],
            ['op' => 'after', 'word' => 'after'],
        ],
        'bool' => [
            ['op' => 'is', 'word' => 'is'],
        ],
    ];

    /**
     * The words the script falls back to where a view hands it none.
     *
     * @var array<string, string>
     */
    private const WORDS = [
        'contains' => 'contains',
        'is' => 'is',
        'isNot' => 'is not',
        'startsWith' => 'starts with',
        'endsWith' => 'ends with',
        'on' => 'on',
        'before' => 'before',
        'after' => 'after',
        'yes' => 'Yes',
        'no' => 'No',
    ];

    /**
     * The text of one chip.
     *
     * @param  array<array-key, mixed>  $rule  an active filter: `field`, `op` and `value`
     * @param  array<array-key, mixed>  $fields  the fields of the builder, as the script is handed them
     * @param  array<string, string>  $words  the translated words the script is handed
     */
    public static function of(array $rule, array $fields, array $words = []): string
    {
        $words += self::WORDS;
        $key = $rule['field'] ?? null;
        $op = $rule['op'] ?? null;

        $field = null;
        foreach ($fields as $candidate) {
            if (is_array($candidate) && ($candidate['key'] ?? null) === $key) {
                $field = $candidate;
                break;
            }
        }

        $operator = null;
        foreach (self::operatorsOf($field, $words) as $candidate) {
            if (is_array($candidate) && ($candidate['op'] ?? null) === $op) {
                $operator = $candidate;
                break;
            }
        }

        $said = ScriptText::of($field === null ? $key : ($field['label'] ?? null))
            .' '.ScriptText::of($operator === null ? $op : ($operator['label'] ?? null));

        // An operator of the field's own that compares against nothing, such as "has a value".
        if ($operator !== null && ($operator['valueless'] ?? null) === true) {
            return $said;
        }

        $value = $rule['value'] ?? null;

        if ($field !== null && ($field['type'] ?? null) === 'bool') {
            return $said.' '.(BooleanProp::orNull($value) === true ? $words['yes'] : $words['no']);
        }

        // A list joins its entries with a comma and a space, and an empty entry stays empty.
        if (is_array($value) && array_is_list($value)) {
            $parts = [];
            foreach ($value as $entry) {
                $parts[] = $entry === null ? '' : ScriptText::of($entry);
            }

            return $said.' '.implode(', ', $parts);
        }

        // An option names its value by its label; only a list of options does, as only a list
        // reaches the script as one.
        $options = $field['options'] ?? null;
        if (is_array($options) && array_is_list($options)) {
            foreach ($options as $option) {
                if (is_array($option) && ScriptText::of($option['value'] ?? null) === ScriptText::of($value)) {
                    return $said.' '.ScriptText::of($option['label'] ?? null);
                }
            }
        }

        return $said.' '.($value === null ? '' : ScriptText::of($value));
    }

    /**
     * The operators of a field: its own list where it names one, else the ones of its type.
     *
     * @param  array<array-key, mixed>|null  $field
     * @param  array<string, string>  $words
     * @return array<array-key, mixed>
     */
    private static function operatorsOf(?array $field, array $words): array
    {
        if ($field === null) {
            return [];
        }

        $own = $field['operators'] ?? null;
        if (is_array($own) && $own !== [] && array_is_list($own)) {
            return $own;
        }

        $type = $field['type'] ?? null;
        $defaults = is_string($type) && isset(self::OPERATORS[$type]) ? self::OPERATORS[$type] : self::OPERATORS['text'];

        $operators = [];
        foreach ($defaults as $operator) {
            // A relation symbol carries its label, every other operator the key of its word.
            $word = $operator['word'] ?? null;
            $operators[] = [
                'op' => $operator['op'],
                'label' => $operator['label'] ?? (is_string($word) ? ($words[$word] ?? '') : ''),
            ];
        }

        return $operators;
    }
}
