<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * A CSS hex color, read into its channels.
 *
 * All four forms CSS accepts: `#rgb`, `#rgba`, `#rrggbb` and `#rrggbbaa`. The short forms repeat
 * each digit, so `#fff` is `#ffffff` and `#0008` is `#00000088`. Taking the first two digits of
 * `fff` as red and the next two as green reads the same value as `rgb(255, 15, 0)`, which is why
 * the form is checked before anything is split.
 *
 * The `#` is optional, as it always was for the components that take a hex color as a prop.
 */
final class HexColor
{
    /**
     * Red, green and blue from 0 to 255, and the alpha on the same scale or null when the color
     * gives none. Null for a value that is not a hex color.
     *
     * @return array{0: int, 1: int, 2: int, 3: int|null}|null
     */
    public static function parse(string $value): ?array
    {
        if (preg_match('/^#?([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', trim($value), $match) !== 1) {
            return null;
        }

        $digits = $match[1];

        if (strlen($digits) <= 4) {
            $digits = implode('', array_map(static fn (string $digit): string => $digit.$digit, str_split($digits)));
        }

        $channels = array_map(static fn (string $pair): int => (int) hexdec($pair), str_split($digits, 2));

        return [$channels[0], $channels[1], $channels[2], $channels[3] ?? null];
    }
}
