<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\Support\Str;

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
 *
 * A configuration switch has the same problem from another side. Its value comes from an
 * environment variable through EnvValue::get(), which hands on a word `env()` does not
 * translate as text, so `WIREKIT_DEDUPE_IDS=off` arrives as the string 'off'. The switches
 * are read here too.
 */
final class BooleanProp
{
    /**
     * Interpret a prop value as a boolean.
     *
     * `filter_var` handles the spellings developers actually write — "false",
     * "0", "off", "no", and their true counterparts — in either case. Anything it
     * cannot classify falls back to PHP's own truthiness, so an unexpected value
     * keeps the meaning PHP gives it rather than silently becoming false and
     * turning a feature off.
     *
     * A bare attribute (`<x-wirekit::faq schema>`) is compiled by Blade as the
     * bound expression `true`, so it arrives as PHP `true` and keeps meaning "on".
     *
     * Given the names of the component and the prop, a text that says neither on nor off is
     * reported through the strictness gate as well, and still reads as on: without the report,
     * the typing error `wrap="flase"` would switch on, silently, what its caller wrote as off.
     * The prop's own name is a spelling of on, `disabled="disabled"` as HTML writes it.
     */
    public static function from(mixed $value, bool $default = false, ?string $component = null, ?string $prop = null): bool
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
            // itself treats a bare attribute as `true`, so blank is the one string
            // that should NOT inherit that meaning — it is an explicit nothing.
            if (trim($value) === '') {
                return false;
            }

            if ($component !== null && $prop !== null && ! self::namesItself(trim($value), $prop)) {
                StrictnessGate::reject($component, $prop, trim($value), 'on or off: true, false, 1, 0, on, off, yes, no or the name of the prop', 'true');
            }
        }

        return (bool) $value;
    }

    /** Whether the text is the prop's own name, in either spelling Blade takes it in. */
    private static function namesItself(string $text, string $prop): bool
    {
        $text = strtolower($text);

        return $text === strtolower($prop) || $text === Str::kebab($prop);
    }

    /**
     * Interpret a value as a boolean, or as unset when it says neither.
     *
     * For a SWITCH whose third state, "not set", hands the decision to a default:
     * `validation.strict` and `validation.throw_on_invalid` in the configuration. The
     * spellings {@see from()} reads are read here too, and a word that says neither,
     * `nein` for instance, is unset rather than on, so the default decides.
     *
     * The value is whatever a published configuration or the environment holds, so the
     * parameter takes any type, and a type that cannot say on or off reads as unset.
     */
    public static function orNull(mixed $value): ?bool
    {
        if ($value === null || is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return trim($value) === '' ? null : filter_var(trim($value), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        }

        if (is_int($value) || is_float($value)) {
            return (bool) $value;
        }

        return null;
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

            // A bare attribute arrives as `true` (Blade binds it as the expression
            // `true`) and must stay on. Only an explicitly false-ish value is stripped.
            if (! self::from($value, true)) {
                $attributes = $attributes->except($flag);
            }
        }

        return $attributes;
    }
}
