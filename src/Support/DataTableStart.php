<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * The head and the rows a data table's script starts with, for the copy the view writes before the
 * script runs and for a page that runs none.
 *
 * The script draws both from templates over `visibleColumns` and `displayRows` in
 * resources/js/components/data-table.js, and each cell through the branch its template picks with
 * `cellText()`, `cellHref()`, `badgeIntent()`, `badgeItems()`, `rowIntent()`, `avatarText()` and
 * `subText()`. This class answers each of them as the script does, so a cell does not change its
 * words or its pill as the script takes over. Both halves are held to the cases of
 * tests/fixtures/data-table-start-cases.json.
 */
final class DataTableStart
{
    /** Every intent a cell may take, the script's `KNOWN_INTENTS`. */
    public const INTENTS = ['primary', 'accent', 'info', 'success', 'warning', 'danger', 'neutral'];

    /** The words a badge takes an intent from when its column maps none, by intent. */
    private const WORDS = [
        'success' => ['met', 'pass', 'paid', 'active', 'done', 'success', 'completed', 'approved'],
        'warning' => ['pending', 'at-risk', 'warning', 'review', 'processing'],
        'danger' => ['failed', 'error', 'inactive', 'overdue', 'rejected', 'canceled'],
    ];

    /**
     * The columns the head shows: every column whose key the hidden list does not name.
     *
     * @param  list<array<array-key, mixed>>  $columns
     * @param  list<mixed>  $hidden
     * @return list<array<array-key, mixed>>
     */
    public static function visibleColumns(array $columns, array $hidden): array
    {
        return array_values(array_filter(
            $columns,
            static fn (array $column): bool => ! in_array($column['key'] ?? null, $hidden, true),
        ));
    }

    /**
     * The rows the script starts with, in the order it shows them.
     *
     * In server mode they are the rows as the server sent them, which it has already ordered and
     * filtered. In client mode the script keeps the rows a field of which holds the search, both
     * folded as SearchFold folds them, over every column, hidden ones too, and sorts them by the
     * start column: two numbers as numbers, anything else as text in the application's locale,
     * with numbers inside text in natural order and no difference of case or accent at the first
     * level, as `Intl.Collator` orders it for the script. Null where the server cannot give that
     * order: without the intl extension, or where the column holds numbers beside text, whose
     * order the script's comparison leaves to the sort it runs in.
     *
     * @param  list<array<array-key, mixed>>  $rows
     * @param  list<array<array-key, mixed>>  $columns  every column, hidden ones too
     * @return list<array<array-key, mixed>>|null
     */
    public static function rows(array $rows, array $columns, bool $server, ?string $sortKey, string $sortDir, ?string $search, string $locale): ?array
    {
        if ($server) {
            return $rows;
        }

        $query = SearchFold::text(BrowserTrim::trim($search ?? '') ?? '');

        if ($query !== '') {
            $rows = array_values(array_filter($rows, static function (array $row) use ($columns, $query): bool {
                foreach ($columns as $column) {
                    $value = self::valueOf($row, $column['key'] ?? null);

                    if (str_contains(SearchFold::text($value === null ? '' : ScriptText::of($value)), $query)) {
                        return true;
                    }
                }

                return false;
            }));
        }

        if ($sortKey === null || $sortKey === '') {
            return $rows;
        }

        return self::sorted($rows, $sortKey, $sortDir === 'asc' ? 1 : -1, $locale);
    }

    /**
     * The rows sorted by one field as the script sorts them, or null where the server cannot.
     *
     * @param  list<array<array-key, mixed>>  $rows
     * @return list<array<array-key, mixed>>|null
     */
    private static function sorted(array $rows, string $key, int $direction, string $locale): ?array
    {
        if (! extension_loaded('intl')) {
            return null;
        }

        $numbers = 0;
        $others = 0;

        foreach ($rows as $row) {
            $value = self::valueOf($row, $key);

            if (is_int($value) || is_float($value)) {
                $numbers++;
            } elseif ($value !== null) {
                $others++;
            }
        }

        if ($numbers > 0 && $others > 0) {
            return null;
        }

        $collator = \Collator::create($locale) ?? \Collator::create('');

        if ($collator === null) {
            return null;
        }

        $collator->setAttribute(\Collator::NUMERIC_COLLATION, \Collator::ON);
        $collator->setStrength(\Collator::PRIMARY);

        usort($rows, static function (array $a, array $b) use ($key, $direction, $collator): int {
            $first = self::valueOf($a, $key);
            $second = self::valueOf($b, $key);

            if ((is_int($first) || is_float($first)) && (is_int($second) || is_float($second))) {
                return ($first <=> $second) * $direction;
            }

            $order = $collator->compare($first === null ? '' : ScriptText::of($first), $second === null ? '' : ScriptText::of($second));

            return (is_int($order) ? $order : 0) * $direction;
        });

        return $rows;
    }

    /**
     * One cell as the template draws it: the branch it takes, its text and what that branch reads.
     *
     * The branches are the template's, in its order: a badge, a list of badges, a pill the row
     * names, a number, a code, a link with a URL that cannot run script, and plain text for every
     * other cell, an unknown type and a link without a URL among them.
     *
     * @param  array<array-key, mixed>  $row
     * @param  array<array-key, mixed>  $column
     * @return array{kind: string, text: string, intent: string, items: list<array{text: string, intent: string}>, href: string, avatar: string, sub: string}
     */
    public static function cell(array $row, array $column): array
    {
        $type = $column['cellType'] ?? null;
        $text = self::textOf($row, $column['key'] ?? null);
        $rowIntent = self::rowIntent($row, $column);
        $href = $type === 'link' && self::truthy($column['hrefKey'] ?? null)
            ? self::href(self::valueOf($row, $column['hrefKey']))
            : '';

        $kind = match (true) {
            $type === 'badge' => 'badge',
            $type === 'badges' => 'badges',
            $rowIntent !== '' => 'intent',
            $type === 'number' => 'number',
            $type === 'code' => 'code',
            $type === 'link' && $href !== '' => 'link',
            default => 'plain',
        };

        $items = [];
        if ($kind === 'badges') {
            foreach (self::badgeItems($row, $column) as $item) {
                $items[] = ['text' => $item, 'intent' => self::badgeIntent($item, $column)];
            }
        }

        return [
            'kind' => $kind,
            'text' => $text,
            'intent' => match ($kind) {
                'badge' => self::badgeIntent($text, $column),
                'intent' => $rowIntent,
                default => '',
            },
            'items' => $items,
            'href' => $kind === 'link' ? $href : '',
            'avatar' => self::truthy($column['avatarKey'] ?? null) ? self::textOf($row, $column['avatarKey']) : '',
            'sub' => self::truthy($column['subKey'] ?? null) ? self::textOf($row, $column['subKey']) : '',
        ];
    }

    /**
     * The intent of a badge's value: the column's own map first, read in any case, then the words
     * the script knows, else neutral. An intent the column maps to that the badge does not know is
     * neutral as well.
     *
     * @param  array<array-key, mixed>  $column
     */
    public static function badgeIntent(string $value, array $column): string
    {
        $word = mb_strtolower($value);
        $intents = $column['intents'] ?? null;

        if (self::truthy($intents) && is_array($intents)) {
            foreach (self::keysInScriptOrder($intents) as $key) {
                if (mb_strtolower((string) $key) !== $word) {
                    continue;
                }

                $intent = mb_strtolower(ScriptText::of($intents[$key]));

                return in_array($intent, self::INTENTS, true) ? $intent : 'neutral';
            }
        }

        foreach (self::WORDS as $intent => $words) {
            if (in_array($word, $words, true)) {
                return $intent;
            }
        }

        return 'neutral';
    }

    /**
     * The intent the row names for this cell, through the column's `intentKey`, where it is one the
     * badge knows; empty otherwise.
     *
     * @param  array<array-key, mixed>  $row
     * @param  array<array-key, mixed>  $column
     */
    private static function rowIntent(array $row, array $column): string
    {
        if (! self::truthy($column['intentKey'] ?? null)) {
            return '';
        }

        $value = self::valueOf($row, $column['intentKey']);

        if ($value === null || $value === '') {
            return '';
        }

        $name = mb_strtolower(ScriptText::of($value));

        return in_array($name, self::INTENTS, true) ? $name : '';
    }

    /**
     * The entries of a badges cell: a list as it stands, one value as a list of one, without the
     * entries that are null or empty.
     *
     * @param  array<array-key, mixed>  $row
     * @param  array<array-key, mixed>  $column
     * @return list<string>
     */
    private static function badgeItems(array $row, array $column): array
    {
        $value = self::valueOf($row, $column['key'] ?? null);

        if ($value === null || $value === '') {
            return [];
        }

        $entries = is_array($value) && array_is_list($value) ? $value : [$value];
        $items = [];

        foreach ($entries as $entry) {
            if ($entry !== null && $entry !== '') {
                $items[] = ScriptText::of($entry);
            }
        }

        return $items;
    }

    /**
     * A field of the row as text: nothing for a field it does not carry or that is null.
     *
     * @param  array<array-key, mixed>  $row
     */
    private static function textOf(array $row, mixed $key): string
    {
        $value = self::valueOf($row, $key);

        return $value === null ? '' : ScriptText::of($value);
    }

    /**
     * A field of the row by a key the column names, read as the script reads a property: by the
     * key as text, so a number and its digits name the same field.
     *
     * @param  array<array-key, mixed>  $row
     */
    private static function valueOf(array $row, mixed $key): mixed
    {
        $name = ScriptText::of($key);

        return array_key_exists($name, $row) ? $row[$name] : null;
    }

    /**
     * The URL of a link cell, or nothing for a value that names none or that could run script. The
     * script's `safeHref()` reads any other value as its text, and so does this.
     */
    private static function href(mixed $value): string
    {
        return $value === null ? '' : SafeUrl::href(ScriptText::of($value));
    }

    /** Whether the script reads a value as true: anything but null, false, zero and the empty text. */
    private static function truthy(mixed $value): bool
    {
        return $value !== null && $value !== false && $value !== 0 && $value !== 0.0 && $value !== '';
    }

    /**
     * The keys of a map in the order the script's `Object.keys()` lists them: the keys that read as
     * an array index first, from the lowest, then every other key as it was written.
     *
     * @param  array<array-key, mixed>  $map
     * @return list<array-key>
     */
    private static function keysInScriptOrder(array $map): array
    {
        $indices = [];
        $names = [];

        foreach (array_keys($map) as $key) {
            if (is_int($key) && $key >= 0 && $key <= 4294967294) {
                $indices[] = $key;
            } else {
                $names[] = $key;
            }
        }

        sort($indices);

        return [...$indices, ...$names];
    }
}
