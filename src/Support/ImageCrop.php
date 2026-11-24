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
 * whole image as a point of the crop, so the markers of a hotspot image follow its crop. An editor
 * that draws marks keeps them in the same unit, and marks() reads a stored list of them.
 */
final class ImageCrop
{
    /** The handles of a crop, at its corners and the middles of its edges. */
    public const HANDLES = ['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w'];

    /** The kinds of mark the editor draws, in the order its toolbar offers them. */
    public const MARK_TYPES = ['arrow', 'line', 'ellipse', 'rect', 'freehand'];

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

    /**
     * The marks a value gives, each held as the editor holds it: a known kind, a color a swatch
     * takes (CssColor), a width between 0.1 and 10 percent of the image's longer side (0.8 where
     * it names none), and coordinates between 0 and 100 kept to one decimal. A mark of another
     * kind, with a color that is no color, or without extent is left out; the others keep their
     * order.
     *
     * @param  array<array-key, mixed>|Arrayable<array-key, mixed>|null  $value  the `marks` of the editor's value
     * @return list<array<string, string|float|list<float>>>
     */
    public static function marks(array|Arrayable|null $value): array
    {
        $value = $value instanceof Arrayable ? $value->toArray() : ($value ?? []);
        $marks = [];

        foreach ($value as $mark) {
            // A mark from a query or a filter arrives as a Collection, and is read as the array it holds.
            $mark = $mark instanceof Arrayable ? $mark->toArray() : $mark;
            $kept = is_array($mark) ? self::mark($mark) : null;

            if ($kept !== null) {
                $marks[] = $kept;
            }
        }

        return $marks;
    }

    /**
     * One mark held as marks() describes, or null.
     *
     * @param  array<array-key, mixed>  $mark
     * @return array<string, string|float|list<float>>|null
     */
    private static function mark(array $mark): ?array
    {
        $type = $mark['type'] ?? null;
        $color = isset($mark['color']) && is_string($mark['color']) ? CssColor::value($mark['color']) : '';

        if (! is_string($type) || ! in_array($type, self::MARK_TYPES, true) || $color === '') {
            return null;
        }

        $at = static fn (float $given): float => round(min(max($given, 0.0), 100.0), 1);
        $width = self::number($mark, 'width');
        $style = ['type' => $type, 'color' => $color, 'width' => $width > 0 ? max(0.1, round(min($width, 10.0), 1)) : 0.8];

        if ($type === 'arrow' || $type === 'line') {
            $line = $style + [
                'x1' => $at(self::number($mark, 'x1')),
                'y1' => $at(self::number($mark, 'y1')),
                'x2' => $at(self::number($mark, 'x2')),
                'y2' => $at(self::number($mark, 'y2')),
            ];

            return $line['x1'] === $line['x2'] && $line['y1'] === $line['y2'] ? null : $line;
        }

        if ($type === 'ellipse' || $type === 'rect') {
            $left = $at(self::number($mark, 'x'));
            $top = $at(self::number($mark, 'y'));
            $right = $at(self::number($mark, 'x') + self::number($mark, 'w'));
            $bottom = $at(self::number($mark, 'y') + self::number($mark, 'h'));
            $x = min($left, $right);
            $y = min($top, $bottom);
            $w = round(max($left, $right) - $x, 1);
            $h = round(max($top, $bottom) - $y, 1);

            return $w === 0.0 || $h === 0.0 ? null : $style + ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h];
        }

        $given = $mark['points'] ?? null;
        $given = $given instanceof Arrayable ? $given->toArray() : $given;
        $raw = is_array($given) ? array_values($given) : [];
        $points = [];
        $first = null;
        $spread = 0.0;

        for ($index = 0; $index + 1 < count($raw); $index += 2) {
            $x = $at(self::number($raw, $index));
            $y = $at(self::number($raw, $index + 1));
            $first ??= [$x, $y];
            $spread = max($spread, abs($x - $first[0]), abs($y - $first[1]));
            $points[] = $x;
            $points[] = $y;
        }

        // A freehand line needs two points, and two that are not in the same place.
        return count($points) < 4 || $spread === 0.0 ? null : $style + ['points' => $points];
    }

    /**
     * A number an array holds under a key, 0 where it holds none.
     *
     * @param  array<array-key, mixed>  $from
     */
    private static function number(array $from, int|string $key): float
    {
        return isset($from[$key]) && is_numeric($from[$key]) ? (float) $from[$key] : 0.0;
    }

    /**
     * The crop the editor shows as its script starts, for the fields the page loads with: the crop
     * held to the image and to its smallest size, fitted to the ratio and held again, as `_settle()`
     * in resources/js/components/image-editor.js does it. The smallest size and the ratio need the
     * image's size in pixels, which the script knows from `width` and `height` at its start and
     * otherwise only once the image has loaded; without both the crop is held to the image alone,
     * as the script holds it until then. Each side keeps one decimal.
     *
     * @internal Called by the editor's view; held to tests/fixtures/image-crop-settle-cases.json.
     *
     * @param  array{x: float, y: float, w: float, h: float}|null  $crop  as from() reads it
     * @return array{x: float, y: float, w: float, h: float}
     */
    public static function settled(?array $crop, ?int $width, ?int $height, ?float $ratio, int $min): array
    {
        $crop ??= ['x' => 0.0, 'y' => 0.0, 'w' => 100.0, 'h' => 100.0];
        $least = ['w' => 0.0, 'h' => 0.0];
        $k = null;

        if ($width !== null && $height !== null && $width > 0 && $height > 0) {
            $side = min($min, $width, $height);
            $least = ['w' => ($side / $width) * 100.0, 'h' => ($side / $height) * 100.0];
            $k = $ratio !== null && $ratio > 0 ? ($ratio * $height) / $width : null;
        }

        return self::held(self::fitted(self::held($crop, $least), $k), $least);
    }

    /**
     * A crop held to the image and its smallest size, `clampCrop()`: the size first, then the place.
     *
     * @param  array{x: float, y: float, w: float, h: float}  $crop
     * @param  array{w: float, h: float}  $least
     * @return array{x: float, y: float, w: float, h: float}
     */
    private static function held(array $crop, array $least): array
    {
        $w = self::tenth(min(max($crop['w'], min($least['w'], 100.0)), 100.0));
        $h = self::tenth(min(max($crop['h'], min($least['h'], 100.0)), 100.0));

        return [
            'x' => self::tenth(min(max($crop['x'], 0.0), 100 - $w)),
            'y' => self::tenth(min(max($crop['y'], 0.0), 100 - $h)),
            'w' => $w,
            'h' => $h,
        ];
    }

    /**
     * A crop fitted to a ratio, `fitRatio()`: it keeps the middle of its box, and its longer side
     * shrinks until it fits.
     *
     * @param  array{x: float, y: float, w: float, h: float}  $crop
     * @return array{x: float, y: float, w: float, h: float}
     */
    private static function fitted(array $crop, ?float $k): array
    {
        if ($k === null || $k <= 0 || $crop['w'] <= 0 || $crop['h'] <= 0) {
            return $crop;
        }

        if ($k < $crop['w'] / $crop['h']) {
            $w = $crop['h'] * $k;
            $left = $crop['x'] + ($crop['w'] - $w) / 2;

            return self::edges($left, $crop['y'], $left + $w, $crop['y'] + $crop['h']);
        }

        $h = $crop['w'] / $k;
        $top = $crop['y'] + ($crop['h'] - $h) / 2;

        return self::edges($crop['x'], $top, $crop['x'] + $crop['w'], $top + $h);
    }

    /**
     * A crop from its four edges, each to one decimal, `fromEdges()`.
     *
     * @return array{x: float, y: float, w: float, h: float}
     */
    private static function edges(float $left, float $top, float $right, float $bottom): array
    {
        $x = self::tenth($left);
        $y = self::tenth($top);

        return ['x' => $x, 'y' => $y, 'w' => self::tenth(self::tenth($right) - $x), 'h' => self::tenth(self::tenth($bottom) - $y)];
    }

    /**
     * A number to one decimal as the script's `Math.round(value * 10) / 10` gives it: halves up,
     * from the same product. PHP's round() pre-rounds the number it is given, which the script's
     * product does not, so a side next to a half could land on another tenth there.
     */
    private static function tenth(float $value): float
    {
        return floor($value * 10 + 0.5) / 10;
    }
}
