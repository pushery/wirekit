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
 * `JSON` is simply not there. Measured against Alpine's own evaluator:
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
 * ## The three escaping traps, all measured rather than assumed
 *
 * **Unicode must stay literal.** `json_encode` escapes non-ASCII as `\u00fc` by
 * default, and Alpine's CSP tokenizer understands only `\n`, `\t`, `\r`, `\\`
 * and the quote — every other backslash is dropped, keeping the letters. So
 * `Grüße` arrives as `Gru00fce`: not an error, just quietly wrong text. That
 * describes @alpinejs/csp 3.16.3, and the tokenizers Livewire 4.0.0 and 4.3.5
 * bundle. The 3.17.2 tokenizer decodes a four-digit escape of that kind, and
 * still drops the backslash of a hex or a code-point escape. A page runs
 * whichever Alpine its Livewire bundles, which this package does not pin, so
 * this emits the one form every tokenizer measured reads back: the literal
 * character. `JSON_UNESCAPED_UNICODE` is therefore not a preference, and neither
 * is `JSON_UNESCAPED_LINE_TERMINATORS`: without it U+2028 and U+2029, which
 * arrive in text pasted from PDF and office documents, are escaped as well and
 * read back as the letters `u2028`.
 *
 * **A control character has no form every tokenizer reads.** JSON must escape
 * the characters below U+0020. Newline, tab and carriage return keep the three
 * escapes the tokenizer knows; every other one would arrive wrong — `\f` reads
 * back as the letter `f` and `\b` as `b` in every tokenizer measured, and
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
 * The two encoders are not ranked; they belong to different contexts:
 *
 *   attribute + Alpine directive -> AlpinePayload, always through `{{ }}`
 *   inline `<script>`            -> Js::from
 *
 * A guard in the package's own suite holds both boundaries.
 */
final class AlpinePayload
{
    /**
     * `mixed`, because encoding any value a component hands an Alpine directive is the whole
     * job: a string, a number, a list, a map. What JSON cannot represent throws.
     *
     * @throws JsonException when the value cannot be represented as JSON
     */
    public static function from(mixed $value): string
    {
        $json = json_encode(
            $value,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS
        );

        // Most payloads carry no escape at all, and then there is nothing to rewrite.
        if (! str_contains($json, '\\')) {
            return $json;
        }

        // The escapes are consumed left to right, so the `b` in an escaped backslash
        // followed by a letter (`\\b`, a Windows path) is never read as a control escape.
        // What is left after the flags above: `\"`, `\\`, `\n`, `\t`, `\r`, and the
        // control characters JSON must escape, as `\b`, `\f` or `\u00XX`.
        return (string) preg_replace_callback(
            '/\\\\(u[0-9a-fA-F]{4}|.)/',
            static fn (array $escape): string => self::isControlEscape($escape[1]) ? "\u{FFFD}" : $escape[0],
            $json,
        );
    }

    /**
     * The same, for a value that must arrive in JavaScript as a STRING.
     *
     * This exists because of what it replaces. Ninety-five places wrote a prop straight
     * into a single-quoted JS literal — `x-data="wirekitModal('{{ $name }}')"` — and
     * `{{ }}` does not protect that position: the browser decodes `&#039;` back to `'`
     * before Alpine ever evaluates the attribute. Measured, with a benign control:
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
     * Whether an escape, without its backslash, stands for a control character other than
     * newline, tab and carriage return, the three every tokenizer measured reads back.
     */
    private static function isControlEscape(string $escape): bool
    {
        if ($escape === 'b' || $escape === 'f') {
            return true;
        }

        return strlen($escape) === 5 && hexdec(substr($escape, 1)) < 0x20;
    }
}
