<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * Reads a Blade component prop that is meant as a boolean.
 *
 * Blade compiles an UNBOUND attribute to a PHP string. `schema="false"` therefore
 * arrives as the string `'false'`, which is truthy, so a component that tests the
 * prop directly does the opposite of what the call site reads as:
 *
 *     <x-wirekit::faq schema="false">   → schema is 'false' → truthy → schema ON
 *     <x-wirekit::faq :schema="false">  → schema is false   → falsy  → schema OFF
 *
 * The failure is silent and asymmetric: the page renders normally either way, and
 * the direction that breaks is the one a developer chooses deliberately (turning a
 * second FAQ's JSON-LD off so one page does not emit two competing FAQPage nodes).
 *
 * Normalizing here means both spellings agree, which is what a developer expects
 * from an attribute that is documented as a boolean.
 */
final class BooleanProp
{
    /**
     * Interpret a prop value as a boolean.
     *
     * `filter_var` handles the spellings developers actually write — "false",
     * "0", "off", "no", and their true counterparts — in either case. Anything it
     * cannot classify falls back to PHP's own truthiness so an unexpected value
     * behaves as it did before this helper existed, rather than silently becoming
     * false and turning a feature off.
     *
     * A bare attribute (`<x-wirekit::faq schema>`) compiles to the string "true"
     * in Blade, so it keeps meaning "on".
     */
    public static function from(mixed $value, bool $default = false): bool
    {
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $parsed = filter_var(trim($value), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

            if ($parsed !== null) {
                return $parsed;
            }

            // An empty attribute (`schema=""`) reads as "present but blank". Blade
            // itself treats a bare attribute as "true", so blank is the one string
            // that should NOT inherit that meaning — it is an explicit nothing.
            if (trim($value) === '') {
                return false;
            }
        }

        return (bool) $value;
    }

    /**
     * Is this prop value an EXPLICIT false — WITHOUT collapsing a third state?
     *
     * For TRI-STATE props (countdown `animate`, `stats`/`feature-grid` `stagger`,
     * form `success`, editor `toolbar`) whose value is `null | true | <something>`.
     * Such a prop must NOT go through {@see from()} — that would collapse its third
     * state (a message string, a step count, 'text') to a bool. But the guards that
     * test `$value !== false` still let the UNBOUND-attribute string `'false'` (which
     * is truthy) slip past, so `success="false"` paints the success state and
     * `toolbar="false"` keeps the toolbar. This recognizes ONLY the genuine false
     * spellings — bool `false` and the case-insensitive strings `'false'` / `'0'` /
     * `'off'` / `'no'` — and returns false for everything else (null, a non-false
     * bool, an int, and any string it cannot classify like `'text'` / `'basic'` /
     * `'150'` / a success message), so the third state survives untouched. This is
     * the string-hardening the countdown `animate` match already applies inline;
     * sharing it here keeps the five other tri-state props consistent.
     */
    public static function isFalse(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value === false;
        }

        if (is_string($value)) {
            return filter_var(trim($value), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === false;
        }

        return false;
    }

    /**
     * HTML attributes whose mere PRESENCE means "on", whatever their value.
     *
     * Per the HTML spec `disabled="false"` disables the control — the value is
     * ignored entirely. A developer writing it means the opposite, and gets no
     * error either way.
     */
    private const HTML_BOOLEAN_FLAGS = ['disabled', 'required', 'readonly', 'checked', 'multiple', 'autofocus'];

    /**
     * Drop HTML boolean flags whose value reads as false.
     *
     * Components that declare such a flag in `@props` already normalize it and
     * emit it deliberately. This is for the ones that pass the attribute bag
     * straight through to the control: there the string `"false"` reaches the
     * DOM verbatim and the browser reads presence, not value.
     *
     * Removing the attribute is the correct normalization, because HTML has no
     * way to spell "explicitly not disabled" other than leaving it out.
     */
    public static function stripFalseHtmlFlags(mixed $attributes): mixed
    {
        foreach (self::HTML_BOOLEAN_FLAGS as $flag) {
            if (! $attributes->has($flag)) {
                continue;
            }

            $value = $attributes->get($flag);

            // A bare attribute compiles to "true" in Blade and must stay on. Only
            // an explicitly false-ish value is stripped.
            if (! self::from($value, true)) {
                $attributes = $attributes->except($flag);
            }
        }

        return $attributes;
    }
}
