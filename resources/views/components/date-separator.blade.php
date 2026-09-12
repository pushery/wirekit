{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'date' => null,
    'variant' => 'inline',
    'format' => null,
    'now' => null,
    'timezone' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;
    use Carbon\Carbon;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('date-separator', $attributes->getAttributes());

    $variantValue = match ($variant) {
        'inline', 'sticky' => $variant,
        default => WireKit::validateProp('date-separator', 'variant', $variant, ['inline', 'sticky']),
    };

    // Parse date with timezone support
    $tz = $timezone ?? config('app.timezone', 'UTC');
    $carbonDate = $date instanceof Carbon ? $date->timezone($tz) : Carbon::parse($date, $tz);
    $reference = $now instanceof Carbon ? $now->timezone($tz) : ($now ? Carbon::parse($now, $tz) : Carbon::now($tz));

    // Human-friendly relative label
    if ($format !== null) {
        $label = $carbonDate->format($format);
    } elseif ($carbonDate->isSameDay($reference)) {
        $label = __('wirekit::Today');
    } elseif ($carbonDate->isSameDay($reference->copy()->subDay())) {
        $label = __('wirekit::Yesterday');
    } elseif ($carbonDate->diffInDays($reference) < 7) {
        $label = $carbonDate->translatedFormat('l');
    } else {
        // Skeleton, not a format string: `translatedFormat('M j')` translated the month
        // NAME and kept the English arrangement, so German rendered "Sep. 7" where German
        // writes "7. Sep.". ICU derives the order from the locale instead.
        $label = \Pushery\WireKit\Support\LocalizedDate::bySkeleton($carbonDate, 'MMMd', 'M j');
    }

    // Full date for screen readers
    // Same reason as the label above, and it matters more here: this is what a screen
    // reader announces, and "Sonntag, September 7, 2026" is a sentence no German speaker
    // writes. English output is unchanged.
    $fullDate = \Pushery\WireKit\Support\LocalizedDate::bySkeleton($carbonDate, 'yMMMMEEEEd', 'l, F j, Y');

    $stickyClasses = $variantValue === 'sticky'
        // No z fallback: it read `var(--z-wk-sticky,10)` while the token is 40, so any
        // context where the token did not resolve dropped this separator four layers below
        // where every other sticky surface sits — silently, and only in that context. A
        // fallback that disagrees with the value it stands in for is worse than none: it
        // turns a missing token into a WRONG one, which nothing reports.
        ? 'sticky top-0 z-[var(--z-wk-sticky)] py-[var(--space-wk-xs,0.25rem)]'
        : 'py-[var(--space-wk-xs,0.25rem)]';
@endphp

<div
    role="separator"
    {{-- Only when the caller did not name it. HTML keeps the FIRST of two identical
         attributes, and the bag renders after this line — so a caller's `aria-label` was
         parsed and then discarded. The full date is the better default and stays one. --}}
    @unless($attributes->has('aria-label') || $attributes->has('aria-labelledby'))
    aria-label="{{ $fullDate }}"
    @endunless
    {{ $attributes->class([
        WireKit::resolveClasses('date-separator', 'base', implode(' ', [
            'flex items-center',
            'text-[length:var(--text-wk-xs)]',
            'text-[color:var(--color-wk-text-muted)]',
            'font-[family-name:var(--font-wk-sans)]',
            $stickyClasses,
        ]), $scope),
    ]) }}
>
    <span aria-hidden="true" class="grow border-t border-[var(--color-wk-border)]"></span>
    <time
        datetime="{{ $carbonDate->toDateString() }}"
        class="shrink-0 px-[var(--space-wk-sm,0.5rem)] font-[number:var(--font-wk-heading-weight)]"
    >
        {{ $slot->isEmpty() ? $label : $slot }}
    </time>
    <span aria-hidden="true" class="grow border-t border-[var(--color-wk-border)]"></span>
</div>
