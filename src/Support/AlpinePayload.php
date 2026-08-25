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
 * an event calendar handed a week of events renders "No events in this range"
 * and logs nothing.
 *
 * A plain JS literal has no such problem: Alpine's CSP parser accepts object and
 * array literals, and its evaluator returns them as-is. So that is what this
 * emits.
 *
 * ## The two escaping traps, both measured rather than assumed
 *
 * **Unicode must stay literal.** `json_encode` escapes non-ASCII as `ü` by
 * default, and Alpine's CSP tokenizer understands only `\n`, `\t`, `\r`, `\\`
 * and the quote — every other backslash is dropped, keeping the letters. So
 * `Grüße` arrives as `Gru00fce`: not an error, just quietly wrong text.
 * `JSON_UNESCAPED_UNICODE` is therefore not a preference.
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
     * @throws JsonException when the value cannot be represented as JSON
     */
    public static function from(mixed $value): string
    {
        return json_encode(
            $value,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
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
     * @throws JsonException when the value cannot be represented as JSON
     */
    public static function string(mixed $value): string
    {
        return self::from((string) $value);
    }
}
