<?php

declare(strict_types=1);

namespace Pushery\WireKit\Theming;

/**
 * A color in OKLCH, read from CSS and written back as an `oklch()` value or a hex color.
 *
 * Every `--color-wk-*` token is an `oklch()` value, so a color that comes from somewhere else, a
 * brand color typed as `#e11d48` for one, has to be converted before it can stand beside them.
 * The conversion runs through OKLab with the matrices of the OKLab paper, the same ones
 * `WcagContrast` uses for the way back, so the ratio `WcagContrast` gives for a color converted
 * here is the ratio of that color.
 *
 * A color is a list of three numbers: lightness from 0 to 1, chroma from 0 upward, and the hue in
 * degrees from 0 up to 360. Written back, a color outside the sRGB gamut loses chroma until it
 * fits, at the same lightness and hue, rather than having each channel clipped: a clipped channel
 * shifts the hue and the lightness, and a contrast taken from the color before the clip no
 * longer describes the color on the screen.
 */
final class Oklch
{
    /** How close to the edge of the gamut a reduced chroma is searched, in OKLCH chroma. */
    private const GAMUT_PRECISION = 0.0001;

    /**
     * Below this chroma a color has no hue that a reader could see, and the hue is written as 0.
     */
    private const ACHROMATIC = 0.000_02;

    /**
     * The color in OKLCH, or null when it cannot be read or is not opaque.
     *
     * Reads every form `WcagContrast` reads. An `oklch()` value keeps the numbers it was written
     * with, even outside the sRGB gamut, so the conversion back can reduce its chroma instead of
     * clipping it. A translucent color is refused: its color on the page depends on what lies
     * beneath it.
     *
     * @return array{0: float, 1: float, 2: float}|null
     */
    public static function fromCss(string $color): ?array
    {
        $color = trim($color);

        if (preg_match('#^oklch\(\s*([\d.]+%?)\s+([\d.]+%?)\s+(-?[\d.]+)(?:deg)?\s*\)$#i', $color, $m) === 1) {
            $lightness = str_ends_with($m[1], '%') ? (float) rtrim($m[1], '%') / 100 : (float) $m[1];
            $chroma = str_ends_with($m[2], '%') ? (float) rtrim($m[2], '%') / 100 * 0.4 : (float) $m[2];

            return self::normalized($lightness, $chroma, (float) $m[3]);
        }

        $rgba = WcagContrast::parseToLinearRgba($color);

        if ($rgba === null || $rgba[3] < 1.0) {
            return null;
        }

        return self::fromLinearRgb($rgba[0], $rgba[1], $rgba[2]);
    }

    /**
     * The color as an `oklch()` value, inside the sRGB gamut.
     *
     * Lightness as a percentage with two decimals, chroma with four, the hue with two.
     *
     * @param  array{0: float, 1: float, 2: float}  $lch
     */
    public static function toCss(array $lch): string
    {
        [$lightness, $chroma, $hue] = self::toGamut($lch);

        $number = static fn (float $value, int $decimals): string => rtrim(rtrim(number_format($value, $decimals, '.', ''), '0'), '.');

        return sprintf(
            'oklch(%s%% %s %s)',
            $number($lightness * 100, 2),
            $number($chroma, 4),
            $number($chroma < self::ACHROMATIC ? 0.0 : $hue, 2),
        );
    }

    /**
     * The color as a `#rrggbb` hex color, inside the sRGB gamut.
     *
     * @param  array{0: float, 1: float, 2: float}  $lch
     */
    public static function toHex(array $lch): string
    {
        [$red, $green, $blue] = self::toLinearRgb(self::toGamut($lch));

        $byte = static fn (float $linear): string => sprintf('%02x', (int) round(self::encode(max(0.0, min(1.0, $linear))) * 255));

        return '#'.$byte($red).$byte($green).$byte($blue);
    }

    /**
     * The color with as much of its chroma as fits into the sRGB gamut, at the same lightness and hue.
     *
     * A lightness outside 0 to 1 is brought to the nearer end first; black and white have no chroma.
     *
     * @param  array{0: float, 1: float, 2: float}  $lch
     * @return array{0: float, 1: float, 2: float}
     */
    public static function toGamut(array $lch): array
    {
        [$lightness, $chroma, $hue] = self::normalized($lch[0], $lch[1], $lch[2]);

        if (self::inGamut([$lightness, $chroma, $hue])) {
            return [$lightness, $chroma, $hue];
        }

        // Bisection on the chroma: zero is always inside, the given chroma is outside.
        $inside = 0.0;
        $outside = $chroma;

        while ($outside - $inside > self::GAMUT_PRECISION) {
            $middle = ($inside + $outside) / 2;

            if (self::inGamut([$lightness, $middle, $hue])) {
                $inside = $middle;
            } else {
                $outside = $middle;
            }
        }

        return [$lightness, $inside, $hue];
    }

    /**
     * Whether the color can be shown in sRGB without clipping a channel.
     *
     * @param  array{0: float, 1: float, 2: float}  $lch
     */
    public static function inGamut(array $lch): bool
    {
        foreach (self::toLinearRgb($lch) as $channel) {
            if ($channel < -0.000_001 || $channel > 1.000_001) {
                return false;
            }
        }

        return true;
    }

    /**
     * Lightness inside 0 to 1, chroma not negative, the hue inside 0 up to 360.
     *
     * @return array{0: float, 1: float, 2: float}
     */
    private static function normalized(float $lightness, float $chroma, float $hue): array
    {
        $lightness = max(0.0, min(1.0, $lightness));
        $chroma = $lightness <= 0.0 || $lightness >= 1.0 ? 0.0 : max(0.0, $chroma);
        $hue = fmod($hue, 360.0);

        if ($hue < 0.0) {
            $hue += 360.0;
        }

        return [$lightness, $chroma, $hue];
    }

    /**
     * Linear sRGB to OKLCH, through LMS and OKLab (the M1 and M2 matrices of the OKLab paper).
     *
     * @return array{0: float, 1: float, 2: float}
     */
    private static function fromLinearRgb(float $red, float $green, float $blue): array
    {
        $long = 0.4122214708 * $red + 0.5363325363 * $green + 0.0514459929 * $blue;
        $medium = 0.2119034982 * $red + 0.6806995451 * $green + 0.1073969566 * $blue;
        $short = 0.0883024619 * $red + 0.2817188376 * $green + 0.6299787005 * $blue;

        $longRoot = self::cubeRoot($long);
        $mediumRoot = self::cubeRoot($medium);
        $shortRoot = self::cubeRoot($short);

        $lightness = 0.2104542553 * $longRoot + 0.7936177850 * $mediumRoot - 0.0040720468 * $shortRoot;
        $a = 1.9779984951 * $longRoot - 2.4285922050 * $mediumRoot + 0.4505937099 * $shortRoot;
        $b = 0.0259040371 * $longRoot + 0.7827717662 * $mediumRoot - 0.8086757660 * $shortRoot;

        $chroma = sqrt($a * $a + $b * $b);
        $hue = $chroma < self::ACHROMATIC ? 0.0 : rad2deg(atan2($b, $a));

        return self::normalized($lightness, $chroma < self::ACHROMATIC ? 0.0 : $chroma, $hue);
    }

    /**
     * OKLCH to linear sRGB, unclamped, so a channel outside 0 to 1 says the color is outside the gamut.
     *
     * @param  array{0: float, 1: float, 2: float}  $lch
     * @return array{0: float, 1: float, 2: float}
     */
    private static function toLinearRgb(array $lch): array
    {
        [$lightness, $chroma, $hue] = $lch;
        $radians = deg2rad($hue);
        $a = $chroma * cos($radians);
        $b = $chroma * sin($radians);

        $long = ($lightness + 0.3963377774 * $a + 0.2158037573 * $b) ** 3;
        $medium = ($lightness - 0.1055613458 * $a - 0.0638541728 * $b) ** 3;
        $short = ($lightness - 0.0894841775 * $a - 1.2914855480 * $b) ** 3;

        return [
            4.0767416621 * $long - 3.3077115913 * $medium + 0.2309699292 * $short,
            -1.2684380046 * $long + 2.6097574011 * $medium - 0.3413193965 * $short,
            -0.0041960863 * $long - 0.7034186147 * $medium + 1.7076147010 * $short,
        ];
    }

    /** Gamma-encode a linear sRGB channel. */
    private static function encode(float $channel): float
    {
        return $channel <= 0.0031308 ? 12.92 * $channel : 1.055 * $channel ** (1 / 2.4) - 0.055;
    }

    /** The real cube root, negative for a negative number. */
    private static function cubeRoot(float $value): float
    {
        return $value < 0.0 ? -((-$value) ** (1 / 3)) : $value ** (1 / 3);
    }
}
