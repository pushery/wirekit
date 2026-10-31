<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\Contracts\Support\Arrayable;

/**
 * The crop of `<x-wirekit::image-editor>`: `['x' => …, 'y' => …, 'w' => …, 'h' => …]` in percent of
 * the image, the unit `<x-wirekit::image-hotspots>` places its markers in.
 *
 * Besides reading the editor's props, it carries the two conversions an application needs with a
 * crop: the rectangle in the pixels of the image, for a resize on the server, and a point of the
 * whole image as a point of the crop, so the markers of a hotspot image follow its crop.
 */
final class ImageCrop
{
    /** The handles of a crop, at its corners and the middles of its edges. */
    public const HANDLES = ['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w'];

    /**
     * The crop a value gives, as four numbers; null where it gives none, which the editor shows as
     * the whole image. A side that is no number takes the image's edge, 0 for `x` and `y` and 100
     * for `w` and `h`, and the editor holds the crop to the image.
     *
     * @param  array<array-key, mixed>|Arrayable<array-key, mixed>|null  $value
     * @return array{x: float, y: float, w: float, h: float}|null
     */
    public static function from(array|Arrayable|null $value): ?array
    {
        $value = $value instanceof Arrayable ? $value->toArray() : $value;

        if ($value === null || $value === []) {
            return null;
        }

        $side = static fn (string $key, float $fallback): float => isset($value[$key]) && is_numeric($value[$key]) ? (float) $value[$key] : $fallback;

        return ['x' => $side('x', 0.0), 'y' => $side('y', 0.0), 'w' => $side('w', 100.0), 'h' => $side('h', 100.0)];
    }

    /**
     * The ratio a prop gives, width over height: `9/16`, or a number such as `0.5625`. Another
     * text is reported (StrictnessGate), and the crop is free.
     */
    public static function ratio(string|int|float|null $ratio): ?float
    {
        if ($ratio === null || $ratio === '') {
            return null;
        }

        $text = trim((string) $ratio);

        if (preg_match('/^(\d+(?:\.\d+)?)\s*\/\s*(\d+(?:\.\d+)?)$/', $text, $parts) === 1 && (float) $parts[2] > 0.0 && (float) $parts[1] > 0.0) {
            return (float) $parts[1] / (float) $parts[2];
        }

        if (is_numeric($text) && (float) $text > 0.0) {
            return (float) $text;
        }

        StrictnessGate::reject('image-editor', 'ratio', $text, 'a width over a height, such as "9/16", or a positive number', 'a free crop');

        return null;
    }

    /**
     * A crop in the pixels of an image of a size: whole pixels from edge to edge, so the crop's
     * right and bottom edges land where the percentages put them.
     *
     * @param  array{x: int|float, y: int|float, w: int|float, h: int|float}  $crop
     * @return array{x: int, y: int, w: int, h: int}
     */
    public static function pixels(array $crop, int $width, int $height): array
    {
        $x = (int) round($crop['x'] / 100 * $width);
        $y = (int) round($crop['y'] / 100 * $height);

        return [
            'x' => $x,
            'y' => $y,
            'w' => (int) round(($crop['x'] + $crop['w']) / 100 * $width) - $x,
            'h' => (int) round(($crop['y'] + $crop['h']) / 100 * $height) - $y,
        ];
    }

    /**
     * A point of the whole image, `x` and `y` in percent, as a point of the crop, or null where it
     * falls outside the crop. Every other key of the point stays with it: a marker keeps its label
     * and its target.
     *
     * @param  array{x: int|float, y: int|float, w: int|float, h: int|float}  $crop
     * @param  array<array-key, mixed>  $point
     * @return array<array-key, mixed>|null
     */
    public static function point(array $crop, array $point): ?array
    {
        if ($crop['w'] <= 0 || $crop['h'] <= 0 || ! isset($point['x'], $point['y']) || ! is_numeric($point['x']) || ! is_numeric($point['y'])) {
            return null;
        }

        $x = ((float) $point['x'] - $crop['x']) / $crop['w'] * 100;
        $y = ((float) $point['y'] - $crop['y']) / $crop['h'] * 100;

        if ($x < 0 || $x > 100 || $y < 0 || $y > 100) {
            return null;
        }

        return ['x' => round($x, 1), 'y' => round($y, 1)] + $point;
    }

    /**
     * The points of a list that fall inside the crop, as points of the crop, in their order.
     *
     * @param  array{x: int|float, y: int|float, w: int|float, h: int|float}  $crop
     * @param  iterable<array<array-key, mixed>>  $points
     * @return list<array<array-key, mixed>>
     */
    public static function points(array $crop, iterable $points): array
    {
        $inside = [];

        foreach ($points as $point) {
            $moved = self::point($crop, $point);

            if ($moved !== null) {
                $inside[] = $moved;
            }
        }

        return $inside;
    }
}
