<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\Support\Str;
use Pushery\WireKit\WireKit;

/**
 * Collision-aware DOM id derivation for form controls.
 *
 * A control with no explicit `id` derives one from `name`. Two controls that
 * share a `name` on one page — a create + an edit form, a filter bar + a modal,
 * a repeater row — then emit the SAME `id`. Per the HTML spec `label[for]` /
 * `getElementById` resolve to the FIRST match, so every 2nd+ control is left with
 * no accessible name AND its `aria-describedby` points at a foreign field's hint.
 * The form key `name` is CORRECT to repeat; only the DOM `id` must be unique.
 *
 * This keeps a per-request registry: the FIRST sight of a base returns it verbatim
 * (so a lone `id="email"` stays byte-identical), and each subsequent collision
 * appends `-2`, `-3`, … The registry is reset after each HTTP request (Octane-safe;
 * a fresh FPM process starts empty anyway) and by {@see WireKit::flush()}.
 *
 * Opt out with `config('wirekit.a11y.dedupe_ids', false)` — then the preferred
 * value is returned verbatim (the pre-2.20 behavior).
 *
 * A control with NEITHER `id` nor `name` gets a derived id too, and that one is
 * COUNTED rather than random. The difference is the whole point: a random id is
 * new on every render, so a Livewire morph replaces the node instead of patching
 * it — the reader loses focus mid-keystroke — and every `aria-controls` /
 * `label[for]` / `aria-describedby` that names it is left pointing at the id from
 * the render before. Nothing looks wrong in the markup: both halves are
 * well-formed, they simply name different things.
 *
 * The counter is per request and per prefix, so the same element gets the same
 * number on a re-render as long as the markup order is stable — which is exactly
 * the case a morph is.
 *
 * CAVEAT (documented, and it applies to BOTH paths): across INDEPENDENTLY-updating
 * Livewire islands the registry resets per request, so an id that was `email-2` on
 * the full render can come back `email` after a partial island update, and a
 * counted id can restart at 1 in an island that re-renders alone. Within one
 * component template it is stable (label + describedby are recomputed together).
 * Repeat the same `name` across independent islands → pass an explicit `id`.
 *
 * That caveat is the price of the trade and it is the right way round: a random id
 * breaks the pairing on EVERY render, a counted one only in a case that needs two
 * independently-morphing islands rendering the same nameless control. The first is
 * a defect every user meets; the second is a configuration a caller can fix by
 * passing an `id`.
 */
final class DomId
{
    /** @var array<string, int> base id → times seen this request */
    private static array $seen = [];

    /** @var array<string, int> fallback prefix → ids handed out this request */
    private static array $counters = [];

    /**
     * Return a page-unique id for a control.
     *
     * @param  string|null  $preferred  The caller's explicit `id`, else its `name`.
     * @param  string  $fallbackPrefix  Prefix for a random id when neither is given.
     */
    public static function unique(?string $preferred, string $fallbackPrefix): string
    {
        // No id and no name → count instead of randomize. Unique by construction
        // either way; only the counted one survives a re-render, which is what a
        // `label[for]` and a Livewire morph both need.
        if ($preferred === null || $preferred === '') {
            $seen = self::$counters[$fallbackPrefix] ?? 0;
            self::$counters[$fallbackPrefix] = $seen + 1;

            return $fallbackPrefix.($seen + 1);
        }

        // Opt-out restores the exact pre-2.20 behavior (verbatim, may collide).
        if (! config('wirekit.a11y.dedupe_ids', true)) {
            return $preferred;
        }

        // An array field (`tags[]`, `tags[0]`) dedups on its base, and the brackets
        // leave the id anyway — a `[` is not valid in an id used with label[for].
        $base = str_contains($preferred, '[') ? Str::before($preferred, '[') : $preferred;

        $seen = self::$seen[$base] ?? 0;
        self::$seen[$base] = $seen + 1;

        // First occurrence keeps the clean base; collisions get -2, -3, …
        return $seen === 0 ? $base : $base.'-'.($seen + 1);
    }

    /** Reset the per-request registry (called after each request + by WireKit::flush()). */
    public static function reset(): void
    {
        self::$seen = [];
        self::$counters = [];
    }
}
