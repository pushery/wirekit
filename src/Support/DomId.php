<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;
use Pushery\WireKit\WireKit;

/**
 * Collision-aware DOM id derivation for form controls.
 *
 * A control with no explicit `id` derives one from `name`. Two controls that
 * share a `name` on one page — a create + an edit form, a filter bar + a modal,
 * a repeater row — then emit the same `id`. Per the HTML spec `label[for]` /
 * `getElementById` resolve to the first match, so every 2nd+ control is left with
 * no accessible name and its `aria-describedby` points at a foreign field's hint.
 * The form key `name` is correct to repeat; only the DOM `id` must be unique.
 *
 * This keeps a per-request registry: the first sight of a base returns it verbatim
 * (so a lone `id="email"` stays as written), and each subsequent collision
 * appends `-2`, `-3`, … The registry is reset after each HTTP request, which keeps it
 * per request under Octane (FPM starts every request with empty static state anyway),
 * and by {@see WireKit::flush()}.
 *
 * Opt out with `config('wirekit.a11y.dedupe_ids', false)` — then the preferred
 * value is returned verbatim, and may collide.
 *
 * A control with neither `id` nor `name` gets a derived id too, and that one is
 * counted rather than random. The difference is the whole point: a random id is
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
 * Caveat (documented, and it applies to both paths): across INDEPENDENTLY-updating
 * Livewire islands the registry resets per request, so an id that was `email-2` on
 * the full render can come back `email` after a partial island update, and a
 * counted id can restart at 1 in an island that re-renders alone. Within one
 * component template it is stable (label + describedby are recomputed together).
 * Repeat the same `name` across independent islands → pass an explicit `id`.
 *
 * That caveat is the price of the trade and it is the right way round: a random id
 * breaks the pairing on every render, a counted one only in a case that needs two
 * independently-morphing islands rendering the same nameless control. The first is
 * a defect every user meets; the second is a configuration a caller can fix by
 * passing an `id`.
 *
 * HTML rendered in an earlier request holds ids this request would count again: a page
 * that writes a fragment it keeps in a cache, and renders a layout around it, finds
 * `wk-tooltip-1` in both. {@see reserve()} and {@see reserveIn()} register such ids, so
 * none of them is handed out again before the registry resets.
 */
final class DomId
{
    /** @var array<string, int> base id → times seen this request */
    private static array $seen = [];

    /** @var array<string, int> fallback prefix → ids handed out this request */
    private static array $counters = [];

    /** @var array<string, int> whole id → times handed out this request, see distinct() */
    private static array $wholeIds = [];

    /** @var array<string, true> ids already on the page this request, see reserve() */
    private static array $reserved = [];

    /**
     * Return a page-unique id for a control.
     *
     * @param  string|null  $preferred  The caller's explicit `id`, else its `name`.
     * @param  string  $fallbackPrefix  Prefix for the counted id when neither is given.
     */
    public static function unique(?string $preferred, string $fallbackPrefix): string
    {
        // An id holds no whitespace (valid()), so a name such as `first name` makes `first-name`.
        $preferred = self::valid($preferred);

        // No id and no name → count instead of randomize. Unique by construction
        // either way; only the counted one survives a re-render, which is what a
        // `label[for]` and a Livewire morph both need.
        if ($preferred === null) {
            $number = self::$counters[$fallbackPrefix] ?? 0;

            do {
                $number++;
            } while (isset(self::$reserved[$fallbackPrefix.$number]));

            self::$counters[$fallbackPrefix] = $number;

            return $fallbackPrefix.$number;
        }

        // Opted out: the preferred value verbatim, which may collide.
        if (! BooleanProp::from(config('wirekit.a11y.dedupe_ids', true), true)) {
            return $preferred;
        }

        // An array field (`tags[]`, `tags[0]`) dedups on its base, and the brackets
        // leave the id anyway — a `[` is not valid in an id used with label[for].
        $base = str_contains($preferred, '[') ? Str::before($preferred, '[') : $preferred;

        // First occurrence keeps the clean base; collisions get -2, -3, …, and an id already
        // on the page is passed over.
        $seen = self::$seen[$base] ?? 0;

        do {
            $seen++;
            $id = $seen === 1 ? $base : $base.'-'.$seen;
        } while (isset(self::$reserved[$id]));

        self::$seen[$base] = $seen;

        return $id;
    }

    /**
     * Return a whole derived id unchanged the first time this request, and `-2`, `-3`, … after.
     *
     * For a component that builds its id from a name or a bound property and writes it as it is,
     * brackets included (`wk-date-settings[due]`). unique() cuts a bracketed value at its base,
     * which would move the id of the first instance as well; here only a repeat on the page
     * changes, so the second instance's `label[for]` and `aria-describedby` stop resolving to
     * the first one's elements. Kept apart from unique()'s registry, so a derived id never moves
     * the id of another kind of control.
     */
    public static function distinct(string $id): string
    {
        $id = self::valid($id) ?? $id;

        if (! BooleanProp::from(config('wirekit.a11y.dedupe_ids', true), true)) {
            return $id;
        }

        $seen = self::$wholeIds[$id] ?? 0;

        do {
            $seen++;
            $candidate = $seen === 1 ? $id : $id.'-'.$seen;
        } while (isset(self::$reserved[$candidate]));

        self::$wholeIds[$id] = $seen;

        return $candidate;
    }

    /**
     * Register ids that are on the page already, so that none of them is handed out again this
     * request.
     *
     * For HTML rendered in an earlier request, such as a fragment a host keeps in a cache and
     * writes into a layout it renders now. The registry starts empty in every request, so without
     * this the layout counts from 1 again and repeats the fragment's ids, and `label[for]`,
     * `aria-describedby` and `getElementById()` find the first element of the two. A counted id
     * continues after the highest number of its prefix registered here (`wk-tooltip-6`, then
     * `wk-tooltip-7`), and an id derived from a name or written whole moves on to its next free
     * suffix (`email`, then `email-2`). Register before anything else renders.
     */
    public static function reserve(string ...$ids): void
    {
        foreach ($ids as $id) {
            $id = self::valid($id);

            if ($id === null) {
                continue;
            }

            self::$reserved[$id] = true;

            // `wk-tooltip-12` is the prefix `wk-tooltip-` counted to 12. Nine digits at most: a
            // counted id stays far below that, and a longer run would overflow the counter.
            if (preg_match('/^(.*\D)(\d{1,9})$/', $id, $counted) === 1) {
                self::$counters[$counted[1]] = max(self::$counters[$counted[1]] ?? 0, (int) $counted[2]);
            }
        }
    }

    /**
     * Register every `id` attribute in a piece of rendered HTML, as reserve() registers each.
     *
     * A value in double or single quotes or without quotes is read, its character references
     * decoded as the browser decodes them. What reads as an id without being one, such as the
     * words `id="…"` in a text, is registered all the same and only moves a counter on.
     */
    public static function reserveIn(string|Htmlable $html): void
    {
        $html = $html instanceof Htmlable ? $html->toHtml() : $html;

        // Not anchored to the start of a tag: a value before the id may hold a `>`, as an Alpine
        // expression does, and an id passed over leaves its collision in place. `data-id` and a
        // bound `:id`, whose value is a script, are no ids.
        preg_match_all('/(?<![\w:.@-])id\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+))/i', $html, $attributes, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);

        $ids = [];

        // One of the three groups holds the value, in double quotes, in single quotes or bare.
        foreach ($attributes as $attribute) {
            $ids[] = html_entity_decode($attribute[1] ?? $attribute[2] ?? $attribute[3] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        self::reserve(...$ids);
    }

    /**
     * An id without whitespace, or null for none. HTML forbids ASCII whitespace in an id, and a list
     * of ids such as `aria-describedby` splits on it: a field named `first name` would be described
     * by `first` and `name-hint`, ids no element carries. Each run of it becomes a hyphen, and an id
     * that is empty or whitespace alone is none.
     */
    public static function valid(?string $id): ?string
    {
        if ($id === null) {
            return null;
        }

        $valid = (string) preg_replace('/[\t\n\f\r ]+/', '-', trim($id, "\t\n\f\r "));

        return $valid === '' ? null : $valid;
    }

    /** Reset the per-request registry (called after each request + by WireKit::flush()). */
    public static function reset(): void
    {
        self::$seen = [];
        self::$counters = [];
        self::$wholeIds = [];
        self::$reserved = [];
    }
}
