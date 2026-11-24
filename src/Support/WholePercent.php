<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * The whole percent of a part, as a reader sees it written or hears it announced.
 *
 * It is rounded down, so a written share never claims a threshold the exact share has not
 * reached: 7,996 of 10,000 reads 79, below the 80 at which a warning band starts, and 99.5 of 100
 * reads 99, not the 100 that says the work is done. Before that the share is rounded to nine
 * places, because a share such as 29 of 100 is 28.999999999999996 as a float and would otherwise
 * fall to 28. The part is multiplied before it is divided, which carries less float error than a
 * ratio multiplied by 100.
 *
 * @internal Called from the component views that write a share (`usage-meter`, `radial-progress`,
 *           `progress.circle`); not a developer-facing API.
 */
final class WholePercent
{
    /**
     * The whole percent of `$part` in `$whole`, rounded down; 0 for a whole of zero or less.
     *
     * A share beyond the integer range is the largest or the smallest integer: `(int)` of such a
     * float warns from PHP 8.5 on, and the warning ends the page. One that is not a number is 0.
     */
    public static function of(float $part, float $whole): int
    {
        if ($whole <= 0.0) {
            return 0;
        }

        $share = floor(round($part * 100 / $whole, 9));

        return NumericProp::cast($share) ?? match (true) {
            $share > 0 => PHP_INT_MAX,
            $share < 0 => PHP_INT_MIN,
            default => 0,
        };
    }
}
