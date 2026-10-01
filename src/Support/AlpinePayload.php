<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use JsonException;

/**
 * A PHP value, encoded so an Alpine directive can read it under a strict
 * Content-Security-Policy.
 *
 * Laravel's `Js::from()` is the obvious tool and it is the wrong one HERE, for a
 * reason that only shows up under the CSP build. For anything that is not a
 * scalar it emits `JSON.parse('…')`, and Alpine's CSP evaluator resolves an
 * identifier against the Alpine scope alone — there is no window fallback — so
 * `JSON` is simply not there. Alpine's own evaluator answers:
 *
 *     JSON.parse('[1,2]')   ->  Undefined variable: JSON
 *
 * That does not merely lose the payload. An `x-data` that throws while BUILDING
 * leaves the element with an empty scope, so every directive on it goes quiet —
 * an event calendar handed a week of events renders "No events in this range",
 * and the only report is an `Alpine Expression Error` in the browser console.
 *
 * A plain JS literal has no such problem: Alpine's CSP parser accepts object and
 * array literals, and its evaluator returns them as-is. So that is what this
 * emits.
 *
 * ## The four escaping traps
 *
 * **Unicode must stay literal.** `json_encode` escapes non-ASCII as `\u00fc` by
 * default, and Alpine's CSP tokenizer understands only `\n`, `\t`, `\r`, `\\`
 * and the quote — every other backslash is dropped, keeping the letters. So
 * `Grüße` arrives as `Gru00fce`: not an error, just quietly wrong text. That
 * describes @alpinejs/csp 3.16.3, and the tokenizers Livewire 4.0.0 and 4.3.5
 * bundle. The 3.17.2 tokenizer decodes a four-digit escape of that kind, and
 * still drops the backslash of a hex or a code-point escape. A page runs
 * whichever Alpine its Livewire bundles, which this package does not pin, so
 * this emits the one form every one of these tokenizers reads back: the literal
 * character. `JSON_UNESCAPED_UNICODE` is therefore not a preference, and neither
 * is `JSON_UNESCAPED_LINE_TERMINATORS`: without it U+2028 and U+2029, which
 * arrive in text pasted from PDF and office documents, are escaped as well and
 * read back as the letters `u2028`.
 *
 * **A control character has no form every tokenizer reads.** JSON must escape
 * the characters below U+0020. Newline, tab and carriage return keep the three
 * escapes the tokenizer knows; every other one would arrive wrong — `\f` reads
 * back as the letter `f` and `\b` as `b` in every one of these tokenizers, and
 * `\u0001` as `u0001` below Alpine 3.17 — and written raw it would put a control
 * character into an HTML attribute. So each becomes U+FFFD, the replacement
 * character, which is also what the HTML parser itself puts in place of a NUL in
 * an attribute value. Replaced rather than removed, so two words a stray control
 * character separated do not quietly become one.
 *
 * **The quotes are HTML's problem, not JavaScript's.** The result contains `"`,
 * which would end the attribute it sits in — so it MUST be echoed through
 * Blade's `{{ }}`, which escapes it to `&quot;`. The browser decodes that back
 * before Alpine ever sees the expression. Echoing this through `{!! !!}` breaks
 * the markup; that is what the accompanying guard test checks for.
 *
 * **A character reference in the value must not end the string.** `{{ }}` escapes an
 * ampersand, and an application that has called `Blade::withoutDoubleEncoding()`
 * tells it to leave one that begins a character reference as it is. The browser then
 * decodes the reference inside the attribute, in the middle of a string this encoder
 * had closed correctly:
 *
 *     value  &quot;+alert(1)+&quot;   ->  label: ""+alert(1)+""          <- Alpine runs it
 *     as written here                 ->  label: "\"+alert(1)+\""        <- one string
 *
 * So an ampersand that begins a reference gets a backslash in front of it. With the
 * standard echo nothing is decoded, and every evaluator reads `\&` as `&`. Where the
 * echo leaves the reference standing, the decoded character arrives behind the
 * backslash, an escaped character inside the string: the value is what the rest of
 * that page shows for the same text, and it stays one value.
 *
 * Two kinds of numeric reference are written differently, because a backslash would
 * change what they decode to. One to a letter or a digit is left as it is: `\u`,
 * `\x` and `\n` are escapes of their own, and a letter is harmless without one. One to
 * a line feed or a carriage return gets its backslash before the semicolon instead,
 * which stops either echo from reading a reference at all, so it arrives as the text
 * it was: a string cannot hold a line break, and the engine drops one that follows a
 * backslash.
 *
 * Slashes are left escaped or not as `json_encode` sees fit — the tokenizer
 * turns `\/` back into `/` correctly either way — but they are unescaped here
 * too, because a URL full of `\/` is unreadable in a page's source.
 *
 * ## Where this must NOT be used, and why it is not interchangeable with Js::from
 *
 * **Never inside a `<script>` block.** HTML escaping does not apply there, so a
 * payload containing `</script>` would close the block and turn everything after
 * it into markup. `Js::from` IS safe in that position — it escapes `<` to
 * `\u003C` — and it is the right tool there for the same reason it is the wrong
 * one in a directive.
 *
 * **Never `from()` for a value a script reads back with `JSON.parse()`.** A backslash
 * before an ampersand is JavaScript and not JSON. `json()` writes that value.
 *
 * The encoders are not ranked; they belong to different contexts:
 *
 *   attribute + Alpine directive      -> AlpinePayload::from(), always through `{{ }}`
 *   attribute a script parses as JSON -> AlpinePayload::json(), always through `{{ }}`
 *   inline `<script>`                 -> Js::from
 *
 * A guard in the package's own suite holds the boundaries.
 */
final class AlpinePayload
{
    /**
     * What Blade's echo leaves standing when it does not encode twice: an ampersand, a name or
     * a number, and a semicolon directly behind it. Every form it accepts is an optional `#`
     * and a run of letters and digits. Any other ampersand is escaped by either echo and
     * reaches the browser as itself.
     */
    private const REFERENCE = '/&(#?)([A-Za-z0-9]+);/';

    /** The named references that decode to a quote, a backslash or a control character. */
    private const NAMES_A_JSON_STRING_CANNOT_HOLD = ['quot', 'QUOT', 'bsol', 'Tab', 'NewLine'];

    /**
     * `mixed`, because encoding any value a component hands an Alpine directive is the whole
     * job: a string, a number, a list, a map. What JSON cannot represent throws.
     *
     * @throws JsonException when the value cannot be represented as JSON
     */
    public static function from(mixed $value): string
    {
        $literal = json_encode(
            $value,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS
        );

        // Most payloads carry neither an escape nor an ampersand, and then there is nothing to
        // rewrite.
        if (str_contains($literal, '\\')) {
            // The escapes are consumed left to right, so the `b` in an escaped backslash
            // followed by a letter (`\\b`, a Windows path) is never read as a control escape.
            // What is left after the flags above: `\"`, `\\`, `\n`, `\t`, `\r`, and the
            // control characters JSON must escape, as `\b`, `\f` or `\u00XX`.
            $literal = (string) preg_replace_callback(
                '/\\\\(u[0-9a-fA-F]{4}|.)/',
                static fn (array $escape): string => self::isControlEscape($escape[1]) ? "\u{FFFD}" : $escape[0],
                $literal,
            );
        }

        if (! str_contains($literal, '&')) {
            return $literal;
        }

        return (string) preg_replace_callback(self::REFERENCE, static function (array $reference): string {
            $point = $reference[1] === '#' ? self::codePoint($reference[2]) : null;

            // A letter or a digit is harmless as it is, and behind a backslash `u`, `x` and `n`
            // would open escapes of their own. U+2028 and U+2029 are legal in a string, and
            // behind a backslash the engine drops them.
            if ($point !== null && (self::isLetterOrDigit($point) || $point === 0x2028 || $point === 0x2029)) {
                return $reference[0];
            }

            // A line break cannot stand in a string, and behind a backslash the engine drops
            // it. With the backslash before its semicolon the reference is none to either echo.
            if ($point === 0x0A || $point === 0x0D) {
                return '&#'.$reference[2].'\\;';
            }

            return '\\'.$reference[0];
        }, $literal);
    }

    /**
     * The value as JSON, for an attribute a script reads back with `JSON.parse()`.
     *
     * A carrier such as `<template data-state="{{ … }}">` is no directive. No Alpine evaluator
     * reads it, a script does, so the text has to be JSON, and every escape JSON knows is there
     * to use. `from()` is the wrong encoder here for the reason this one is wrong in a
     * directive: `\&` is not JSON, and not every evaluator of a CSP build reads `\u0026`.
     *
     * What `from()` says about a character reference holds here as well, and JSON has no
     * backslash that keeps a decoded quote inside its string. So the ampersand of a reference
     * that decodes to a quote, a backslash or a control character is written as `\u0026`,
     * which leaves no reference for the page to decode: that one arrives as the text it was.
     * Every other reference stays, and decodes to a character a JSON string can hold.
     *
     * The quotes are still HTML's to escape, so it is echoed through `{{ }}` as well.
     *
     * `mixed`, for the reason `from()` takes it: the value is whatever the component carries.
     *
     * @throws JsonException when the value cannot be represented as JSON
     */
    public static function json(mixed $value): string
    {
        $json = json_encode(
            $value,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS
        );

        if (! str_contains($json, '&')) {
            return $json;
        }

        return (string) preg_replace_callback(self::REFERENCE, static function (array $reference): string {
            if ($reference[1] === '#') {
                $point = self::codePoint($reference[2]);
                $inert = $point !== null && $point >= 0x20 && $point !== 0x22 && $point !== 0x5C;
            } else {
                $inert = ! in_array($reference[2], self::NAMES_A_JSON_STRING_CANNOT_HOLD, true);
            }

            return $inert ? $reference[0] : '\\u0026'.substr($reference[0], 1);
        }, $json);
    }

    /**
     * The same, for a value that must arrive in JavaScript as a STRING.
     *
     * A prop written straight into a single-quoted JS literal, as in
     * `x-data="wirekitModal('{{ $name }}')"`, is not protected by `{{ }}`: the browser decodes
     * `&#039;` back to `'` before Alpine ever evaluates the attribute:
     *
     *     value  "');alert(1);('"   ->  wirekitFoo('');alert(1);('')     <- Alpine runs it
     *     value  "plain-name"       ->  wirekitFoo('plain-name')         <- fine
     *     encoded, either value     ->  wirekitFoo("…")                  <- one argument
     *
     * The cast is the point, and `from()` alone would have been the wrong fix. A prop
     * holding `5` reaches JavaScript as the STRING `'5'` today; `from(5)` would emit the
     * NUMBER `5`, so any `=== '5'` in a factory would quietly stop matching. Casting
     * first preserves the type the caller already gets, which makes the change a
     * security fix and nothing else.
     *
     * `null` casts to `''`, which is what Blade already echoed for it.
     *
     * `mixed`, for the same reason `{{ }}` takes anything: the value is a prop, and the cast is
     * what makes it a string.
     *
     * @throws JsonException when the value cannot be represented as JSON
     */
    public static function string(mixed $value): string
    {
        return self::from((string) $value);
    }

    /**
     * The code point a numeric character reference names, from what follows its `#`, or null
     * when that is no plain decimal or hexadecimal number or names nothing up to U+10FFFF.
     */
    private static function codePoint(string $number): ?int
    {
        if (preg_match('/^(?:[xX]([0-9A-Fa-f]+)|([0-9]+))$/', $number, $digits) !== 1) {
            return null;
        }

        $hexadecimal = $digits[1] !== '';
        $significant = ltrim($hexadecimal ? $digits[1] : ($digits[2] ?? ''), '0');

        // U+10FFFF is the last code point: six hexadecimal digits, seven decimal ones.
        if (strlen($significant) > 7) {
            return null;
        }

        $point = intval($significant, $hexadecimal ? 16 : 10);

        return $point <= 0x10FFFF ? $point : null;
    }

    private static function isLetterOrDigit(int $point): bool
    {
        return ($point >= 0x30 && $point <= 0x39) || ($point >= 0x41 && $point <= 0x5A) || ($point >= 0x61 && $point <= 0x7A);
    }

    /**
     * Whether an escape, without its backslash, stands for a control character other than
     * newline, tab and carriage return, the three every one of those tokenizers reads back.
     */
    private static function isControlEscape(string $escape): bool
    {
        if ($escape === 'b' || $escape === 'f') {
            return true;
        }

        return strlen($escape) === 5 && hexdec(substr($escape, 1)) < 0x20;
    }
}
