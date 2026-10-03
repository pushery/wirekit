<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * The initials an avatar shows for a person without a picture.
 *
 * The first letter of the first word and of the last: "Ada Lovelace" gives "AL", "Ada Byron
 * Lovelace" gives "AL" as well, because the middle name is not the readable pair, and "Ada" gives
 * "A". mb_* throughout: names are exactly where non-ASCII lives, and substr() would cut a code
 * point in half and emit broken UTF-8.
 *
 * Every component that draws a person from a name reads its initials here, so the same name gets
 * the same letters, and through AvatarPalette the same color, wherever it appears.
 *
 * @internal
 */
final class Initials
{
    /**
     * The initials of a name, or null when the name has no word in it.
     */
    public static function from(?string $name): ?string
    {
        $words = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return match (count($words)) {
            0 => null,
            1 => mb_strtoupper(mb_substr($words[0], 0, 1)),
            default => mb_strtoupper(mb_substr($words[0], 0, 1).mb_substr($words[count($words) - 1], 0, 1)),
        };
    }
}
