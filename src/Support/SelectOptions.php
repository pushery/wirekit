<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use BackedEnum;
use Closure;
use Illuminate\Contracts\Support\DeferringDisplayableValue;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * The options of a `select`, its placeholder first, written as one string.
 *
 * Livewire puts two comments around every control structure of a view, and a structure inside a
 * loop writes them on every pass: the choice between an option and a group wrote them beside each
 * option of the list. The options are elements of one kind, which Livewire's morph matches in
 * their order, so the list keeps its updates without them.
 *
 * Every value is escaped as `{{ }}` escapes it, through `e()`: markup stays markup, a backed enum
 * writes its value.
 */
final class SelectOptions
{
    /**
     * The three shapes of an entry mix freely: a flat label (`'de' => 'Germany'`), a label with
     * attributes (`'de' => ['label' => 'Germany', 'disabled' => true, 'lang' => 'de']`), and a
     * group (`'Europe' => ['de' => 'Germany']`), which is an array without a `label` key.
     *
     * @param  array<array-key, mixed>  $options
     * @param  Closure(int|string): bool  $isSelected
     */
    public static function html(array $options, Closure $isSelected, mixed $placeholder, bool $placeholderSelected): HtmlString
    {
        $html = '';

        // The placeholder only pre-selects itself when nothing else is chosen. Marking it
        // `selected` unconditionally made a `value` prop invisible even once the options honored it.
        if ((bool) $placeholder) {
            $html .= '<option value="" disabled'.($placeholderSelected ? ' selected' : '').'>'.self::text($placeholder).'</option>'."\n";
        }

        foreach ($options as $value => $label) {
            if (is_array($label) && ! array_key_exists('label', $label)) {
                $html .= '<optgroup label="'.e($value).'">'."\n";

                foreach ($label as $subValue => $subLabel) {
                    $html .= self::option($subValue, $subLabel, $isSelected)."\n";
                }

                $html .= '</optgroup>'."\n";

                continue;
            }

            $html .= self::option($value, $label, $isSelected)."\n";
        }

        return new HtmlString($html);
    }

    /** @param  Closure(int|string): bool  $isSelected */
    private static function option(int|string $value, mixed $label, Closure $isSelected): string
    {
        $text = is_array($label) ? ($label['label'] ?? $value) : $label;
        // A switch, read as a switch prop is: the text `false`, `no` and `off` leave the option open.
        $disabled = is_array($label) && BooleanProp::from($label['disabled'] ?? false);
        $lang = is_array($label) ? LanguageTag::of($label['lang'] ?? null) : null;

        return '<option value="'.e($value).'"'
            .($lang !== '' && $lang !== null ? ' lang="'.e($lang).'"' : '')
            .($disabled ? ' disabled' : '')
            .($isSelected($value) ? ' selected' : '')
            .'>'.self::text($text).'</option>';
    }

    /**
     * A label as `{{ }}` writes it. A list, or an object that has no text, which `{{ }}` cannot
     * write at all, is written as the name of its type.
     */
    private static function text(mixed $value): string
    {
        if ($value instanceof Htmlable || $value instanceof BackedEnum || $value instanceof DeferringDisplayableValue) {
            return e($value);
        }

        return e(NumericProp::text($value));
    }
}
