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
        // Digits as CSS writes them, and as the picker's `\d` reads them: under `u`, `\d` would take
        // the digits of every script.
        $number = '[0-9.]+';
        // rgb() and hsl() come in two forms, and CSS reads no mix of them: commas between all parts
        // and an alpha after a comma, or spaces and an alpha after a slash.
        $comma = "{$space}*,{$space}*";
        $commaAlpha = "(?:{$comma}{$number}%?)?";
        $slashAlpha = "(?:{$space}*\/{$space}*{$number}%?)?";

        // The hue of hsl() and oklch() is a number or a number in `deg`, the unit the picker turns
        // into the same degrees. Another angle unit would need converting and is no color here.
        $forms = [
            '/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/u',
            "/^rgba?\({$space}*{$number}{$comma}{$number}{$comma}{$number}{$commaAlpha}{$space}*\)$/u",
            "/^rgba?\({$space}*{$number}{$space}+{$number}{$space}+{$number}{$slashAlpha}{$space}*\)$/u",
            // rgb() with three percentages, in either form; three numbers or three percentages,
            // since the comma form takes no mix of them.
            "/^rgba?\({$space}*{$number}%{$comma}{$number}%{$comma}{$number}%{$commaAlpha}{$space}*\)$/u",
            "/^rgba?\({$space}*{$number}%{$space}+{$number}%{$space}+{$number}%{$slashAlpha}{$space}*\)$/u",
            "/^hsla?\({$space}*{$number}(?:deg)?{$comma}{$number}%{$comma}{$number}%{$commaAlpha}{$space}*\)$/u",
            "/^hsla?\({$space}*{$number}(?:deg)?{$space}+{$number}%{$space}+{$number}%{$slashAlpha}{$space}*\)$/u",
            "/^oklch\({$space}*{$number}%?{$space}+{$number}{$space}+{$number}(?:deg)?{$slashAlpha}{$space}*\)$/u",
        ];

        foreach ($forms as $form) {
            if (preg_match($form, $lower) === 1) {
                return $color;
            }
        }

        return '';
    }
}
