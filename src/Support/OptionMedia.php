<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;
use Stringable;

/**
 * The optional keys a `combobox` or `multi-select` option carries beside its value and label.
 *
 * Both components render their options in the browser from a JSON payload, so what this class
 * returns is exactly what reaches that payload, and a key it leaves out is absent there. An
 * option that uses none of these keys therefore normalizes to the entry it always did, down to
 * the byte, and the templates keep their plain row for a list that uses none of them.
 *
 * The keys, and why each one is spelled the way it is:
 *
 * - `icon`, `image` — the same keys `scope-switcher` reads for the same two things. A second
 *   vocabulary for one concept is the drift the other guards in this package exist against.
 * - `avatar` — a photo URL, drawn round. It counts as SET even when it is null, and that is the
 *   point of it: a people picker maps `'avatar' => $user->avatar_url`, some users have no photo,
 *   and those rows show the person's initials instead of dropping out of alignment.
 * - `initials` — only beside `avatar`, for a name whose initials are not its first and last
 *   words ("Dr. Ada Lovelace"). Without it they are derived from the label.
 * - `description` — context under the label in the open panel, never in the field or a pill.
 *   Plain text: the panel renders in the browser through `x-text`, so markup would show as tags.
 * - `keywords` — extra words the search matches. The description is deliberately NOT searched:
 *   typing into a filter should narrow to options whose name fits, and a sentence of context
 *   matches far more than the reader meant.
 * - `flag` — a country or region code such as `de`. The option
 *   carries the code, lower-cased; the component turns it into the flag's URL through
 *   FlagPackage, the way it turns an icon name into a sprite reference, so this class stays a
 *   normalizer that reads nothing from disk.
 * - `selectedLabel` — shorter text for the field or the pill than the list shows. It is searched
 *   as well, so reopening a field that shows it still finds the option it names.
 */
final class OptionMedia
{
    /** The media an option can show, of which it shows at most one. */
    public const MEDIA = ['icon', 'image', 'avatar', 'flag'];

    /**
     * @param  array<array-key, mixed>  $option  one option as the developer wrote it
     * @return array<string, string>
     */
    public static function fields(string $component, array $option, string $value, string $label): array
    {
        $icon = self::text($component, $value, $option, 'icon', 'icon');
        $image = self::text($component, $value, $option, 'image', 'image');
        $avatar = self::text($component, $value, $option, 'avatar', 'avatar');
        $initials = self::text($component, $value, $option, 'initials', 'initials');
        $flag = self::text($component, $value, $option, 'flag', 'flag');

        $given = array_keys(array_filter([
            'icon' => $icon !== null,
            'image' => $image !== null,
            'avatar' => array_key_exists('avatar', $option),
            'flag' => $flag !== null,
        ]));

        if (count($given) > 1) {
            throw new InvalidArgumentException(sprintf(
                'wirekit::%s: option "%s" sets %s, and an option shows one medium. Keep one of them; '
                .'`avatar` counts even when it is null, because an avatar without a photo shows initials.',
                $component,
                $value,
                implode(' and ', array_map(static fn (string $key): string => "`{$key}`", $given)),
            ));
        }

        if ($initials !== null && ! array_key_exists('avatar', $option)) {
            throw new InvalidArgumentException(sprintf(
                'wirekit::%s: option "%s" sets `initials` without `avatar`. Initials are what an avatar '
                .'shows when it has no photo, so add `avatar` (null is fine) or drop `initials`.',
                $component,
                $value,
            ));
        }

        $fields = [];

        if ($icon !== null) {
            $fields['media'] = 'icon';
            $fields['icon'] = $icon;
        } elseif ($image !== null) {
            $fields['media'] = 'image';
            $fields['src'] = $image;
        } elseif ($flag !== null) {
            $fields['media'] = 'flag';
            $fields['flag'] = strtolower($flag);
        } elseif (array_key_exists('avatar', $option)) {
            $fields['media'] = 'avatar';
            if ($avatar !== null) {
                $fields['src'] = $avatar;
            }
            $fields['initials'] = $initials ?? self::initialsOf($label);
        }

        $description = self::text($component, $value, $option, 'description', 'description');
        if ($description !== null) {
            $fields['description'] = $description;
        }

        $keywords = self::keywords($component, $value, $option);
        if ($keywords !== null) {
            $fields['keywords'] = $keywords;
        }

        $selectedLabel = self::text($component, $value, $option, 'selectedLabel', 'selectedLabel');
        if ($selectedLabel !== null) {
            $fields['selectedLabel'] = $selectedLabel;
        }

        return $fields;
    }

    /**
     * Whether any option in a normalized list carries a medium or a description, keyed by what
     * the template needs to know. A list with neither renders the plain row it always rendered.
     *
     * @param  list<array<string, mixed>>  $options
     * @return array{media: bool, descriptions: bool}
     */
    public static function uses(array $options): array
    {
        $uses = ['media' => false, 'descriptions' => false];

        foreach ($options as $option) {
            $uses['media'] = $uses['media'] || isset($option['media']);
            $uses['descriptions'] = $uses['descriptions'] || isset($option['description']);
        }

        return $uses;
    }

    /**
     * The first letter of the first and the last word, so "Ada Lovelace" is "AL" and "Ada" is
     * "A". A word's first LETTER or digit rather than its first character, so "(Ada) Lovelace"
     * does not become a bracket.
     */
    public static function initialsOf(string $label): string
    {
        $letters = [];

        foreach (preg_split('/\s+/u', trim($label)) ?: [] as $word) {
            if (preg_match('/[\p{L}\p{N}]/u', $word, $match) === 1) {
                $letters[] = $match[0];
            }
        }

        if ($letters === []) {
            return '';
        }

        $picked = count($letters) > 1 ? $letters[0].$letters[count($letters) - 1] : $letters[0];

        return mb_strtoupper($picked);
    }

    /**
     * One text-valued key, or null when it is absent, null or blank.
     *
     * @param  array<array-key, mixed>  $source
     */
    private static function text(string $component, string $value, array $source, int|string $key, string $name): ?string
    {
        if (! array_key_exists($key, $source) || $source[$key] === null) {
            return null;
        }

        if ($source[$key] instanceof Htmlable) {
            throw new InvalidArgumentException(sprintf(
                'wirekit::%s: option "%s" passes HTML as `%s`. Options render in the browser as plain text, '
                .'so markup would show up as literal tags. Pass a string.',
                $component,
                $value,
                $name,
            ));
        }

        if (! is_string($source[$key]) && ! is_int($source[$key]) && ! is_float($source[$key]) && ! $source[$key] instanceof Stringable) {
            throw new InvalidArgumentException(sprintf(
                'wirekit::%s: option "%s" has a `%s` of type %s. Pass a string.',
                $component,
                $value,
                $name,
                get_debug_type($source[$key]),
            ));
        }

        $text = trim((string) $source[$key]);

        return $text === '' ? null : $text;
    }

    /**
     * `keywords` as one space-separated string, from a string or a list of strings.
     *
     * @param  array<array-key, mixed>  $option
     */
    private static function keywords(string $component, string $value, array $option): ?string
    {
        if (! array_key_exists('keywords', $option) || $option['keywords'] === null) {
            return null;
        }

        $list = is_array($option['keywords']) ? array_values($option['keywords']) : [$option['keywords']];
        $words = [];

        foreach (array_keys($list) as $index) {
            $word = self::text($component, $value, $list, $index, 'keywords');
            if ($word !== null) {
                $words[] = $word;
            }
        }

        return $words === [] ? null : implode(' ', $words);
    }
}
