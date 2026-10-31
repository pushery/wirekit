<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * The initials an avatar shows for a person without a picture.
 *
 * The first letter of the first word and of the last: "Ada Lovelace" gives "AL", "Ada Byron
 * Lovelace" gives "AL" as well, because the middle name is not the readable pair, and "Ada" gives
 * "A". A word's first letter or digit rather than its first character, so "(Ada) Lovelace" gives
 * "AL" rather than a bracket, and a word with neither, an emoji after a name, gives nothing. Read
 * by code point: names are exactly where non-ASCII lives, and substr() would cut a character in
 * half and emit broken UTF-8. The marks that combine with the letter stay with it: a name written
 * decomposed, as a file name from macOS is, keeps the accent of "Émile", and a vowel sign stays
 * with the consonant it belongs to.
 *
 * Every component that draws a person from a name reads its initials here, so the same name gets
 * the same letters, and through AvatarPalette the same color, wherever it appears. The browser
 * reads the same rule in resources/js/utils/initials.js, held to the same table
 * (tests/fixtures/initials-cases.json).
 *
 * @internal
 */
final class Initials
{
    /**
     * The initials of a name, or null when no word in it holds a letter or a digit.
     */
    public static function from(?string $name): ?string
    {
        $letters = [];

        foreach (preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            if (preg_match('/[\p{L}\p{N}]\p{M}*/u', $word, $match) === 1) {
                $letters[] = $match[0];
            }
        }

        return match (count($letters)) {
            0 => null,
            1 => mb_strtoupper($letters[0]),
            default => mb_strtoupper($letters[0].$letters[count($letters) - 1]),
        };
    }
}
