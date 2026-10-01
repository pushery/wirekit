<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * A color in one of the forms the color picker reads, or nothing.
 *
 * A value written into a `style` attribute is CSS, and escaping does not stop a `;` there: a
 * string that carries further declarations behind a color styles the element it lands on. So a
 * color that arrives in a list is matched against the four forms the picker itself parses, a hex
 * color, `rgb()`, `hsl()` and `oklch()`, and a value that is none of them is no color. What
 * matches holds digits, separators and the function name, and nothing that ends a declaration.
 *
 * `parseColor()` in `resources/js/utils/color.js` reads the same forms, and both are held to one
 * table of cases, so a color the server draws as a swatch is one the picker takes on the click.
 */
final class CssColor
{
    /**
     * The color without surrounding whitespace, or '' when the picker would not read the value.
     *
     * A string that is not valid UTF-8 gives '' as well.
     */
    public static function value(string $value): string
    {
        $space = BrowserTrim::SPACE;
        $color = BrowserTrim::trim($value);

        if ($color === null || $color === '') {
            return '';
        }

        // Matched in lowercase, as the picker does; the color is returned as it was written.
        $lower = mb_strtolower($color, 'UTF-8');
        $number = '[\d.]+';
        $separator = "(?:{$space}|,)+";
        $alpha = "(?:(?:{$space}|[,\/])+{$number}%?)?";

        $forms = [
            '/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/u',
            "/^rgba?\({$space}*{$number}{$separator}{$number}{$separator}{$number}{$alpha}{$space}*\)$/u",
            "/^hsla?\({$space}*{$number}{$separator}{$number}%{$separator}{$number}%{$alpha}{$space}*\)$/u",
            "/^oklch\({$space}*{$number}%?{$space}+{$number}{$space}+{$number}(?:deg)?(?:{$space}*\/{$space}*{$number}%?)?{$space}*\)$/u",
        ];

        foreach ($forms as $form) {
            if (preg_match($form, $lower) === 1) {
                return $color;
            }
        }

        return '';
    }
}
