{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
@props([
    'label' => null,
    'value' => null,
    'delta' => null,
    'deltaFormat' => 'percent',
    'intent' => null,
    'trend' => null,
    // Names the sparkline in the default slot. Opt-in, and null by default: a graphic
    // that already carries its own name must not be given a second, worse one — see the
    // block that renders the slot.
    'trendLabel' => null,
    'size' => config('wirekit.components.ticker.size', 'md'),
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('ticker', $attributes->getAttributes());

    // An unknown `deltaFormat` is reported (Support\StrictnessGate) and renders as `absolute`, as a
    // bound `null` or empty string always has: only `percent` reads as a percentage.
    $deltaFormat = \Pushery\WireKit\WireKit::validateProp('ticker', 'deltaFormat', \Pushery\WireKit\Support\NumericProp::text($deltaFormat ?: 'absolute'), ['absolute', 'percent']);

    // A delta that is no number, NAN or INF, is no change to show: no arm below would hold NAN, and
    // PHP 8.5 warns when either is written as text.
    $delta = is_float($delta) && ! is_finite($delta) ? null : $delta;

    // Auto-derive intent from delta if not explicitly set
    $resolvedIntent = $intent ?? match (true) {
        $delta === null, $delta == 0 => 'neutral',
        $delta > 0 => 'success',
        $delta < 0 => 'danger',
    };

    // Format delta display.
    // Accept both "8.4" and "+8.4" inputs by stripping a leading "+" before
    // we re-add the sign based on the numeric value. Without the strip, a
    // signed-string input ("+8.4") would render as "++8.4%" — the component
    // prepends "+" for positives, then interpolates the original string
    // verbatim. Negative inputs keep their leading "-" and skip the strip.
    //
    // A delta that is a number is written in the application's locale, as every number WireKit
    // writes itself: `+8,4 %` in German and `+8.4%` in English, and grouped where it is absolute
    // (`+1.234,5`). Up to six decimals, trailing zeros dropped, so the figure keeps the precision it
    // was given. The plus is the component's; a negative number takes the locale's minus. The
    // magnitude, without either, is what the delta's name says. A delta that is no number is
    // written as it comes.
    $formattedDelta = null;
    $deltaMagnitude = null;
    if ($delta !== null) {
        $deltaText = is_string($delta) ? ltrim($delta, '+') : (string) $delta;

        if (is_numeric($deltaText)) {
            $writeDelta = static fn (float $number): string => $deltaFormat === 'percent'
                ? \Pushery\WireKit\Support\LocalizedNumber::percent($number, 6)
                : \Pushery\WireKit\Support\LocalizedNumber::format($number, maxPrecision: 6);
            $deltaNumber = (float) $deltaText;
            $formattedDelta = ($deltaNumber > 0 ? '+' : '').$writeDelta($deltaNumber);
            $deltaMagnitude = $writeDelta(abs($deltaNumber));
        } else {
            $formattedDelta = $deltaFormat === 'percent' ? "{$deltaText}%" : $deltaText;
            $deltaMagnitude = abs((float) $deltaText).($deltaFormat === 'percent' ? '%' : '');
        }
    }

    // Delta color classes — use the *-text variants because they are
    // calibrated for ≥4.5:1 contrast against the surface tokens in BOTH
    // light and dark mode. The bare `--color-wk-success` / `--color-wk-danger`
    // foundation tokens are surface colors (button bg etc.) and fail
    // WCAG 1.4.3 when used as small text on dark backgrounds.
    $deltaClasses = match ($resolvedIntent) {
        'success' => 'text-[color:var(--color-wk-success-text)]',
        'danger' => 'text-[color:var(--color-wk-danger-text)]',
        default => 'text-[color:var(--color-wk-text-muted)]',
    };

    // Value size
    $valueSizeClass = match (WireKit::validateProp('ticker', 'size', $size, ['sm', 'md', 'lg'])) {
        'sm' => 'text-[length:var(--text-wk-lg)]',
        'md' => 'text-[length:var(--text-wk-xl)]',
        'lg' => 'text-[length:var(--text-wk-2xl)]',
    };

    $baseClasses = WireKit::resolveClasses('ticker', 'base', implode(' ', [
        'flex flex-col gap-[var(--space-wk-xs,0.25rem)]',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);

    // Delta aria label for screen readers.
    //
    // Through the catalog rather than built from English words: this string lives
    // in PHP, so the guard that catches an untranslated literal in a template never
    // looked at it, and a fully German dashboard announced "Change: up 12 percent".
    // One key per direction instead of a sentence assembled from fragments — word
    // order is not the same in every language, and "up" alone cannot be translated
    // correctly without knowing what follows it.
    //
    // The magnitude carries its own unit sign, which every screen reader expands in
    // the reader's own language ("8,4 %" → "8,4 Prozent"), so the unit does not need
    // a key of its own and the number matches the one on screen.

    $deltaAriaLabel = match (true) {
        $delta === null => null,
        $delta > 0 => __('wirekit::Change: up :value', ['value' => $deltaMagnitude]),
        $delta < 0 => __('wirekit::Change: down :value', ['value' => $deltaMagnitude]),
        default => __('wirekit::Change: unchanged'),
    };

    // Page-unique, and NOT derived from the label. `md5($label)` gave every ticker
    // with the same heading the same id — two "Revenue" cards on one dashboard, or
    // any two tickers with no label at all, and the second `<article>` was named by
    // the first one's label while axe reported a duplicate id. See Support\DomId.
    $tickerId = \Pushery\WireKit\Support\DomId::unique($attributes->get('id'), 'ticker-');
@endphp

{{-- `aria-labelledby` only when there is a label to point at: an <article> whose
     name resolves to an empty element is worse than an unnamed one, because
     assistive technology announces the landmark and then has nothing to say. --}}
<article
    @if(filled($label)) @unless($attributes->has('aria-label') || $attributes->has('aria-labelledby')) aria-labelledby="{{ $tickerId }}-label" @endunless @endif
    {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$baseClasses])) }}
>
    {{-- Label --}}
    <span
        id="{{ $tickerId }}-label"
        class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)] font-[number:var(--font-wk-body-weight)]"
    >
        {{ $label }}
    </span>

    {{-- Value + delta row --}}
    <span class="flex items-baseline gap-[var(--space-wk-sm,0.5rem)]">
        <span class="{{ $valueSizeClass }} font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)] tabular-nums">
            {{-- A value that is not finite is written as PHP writes it, `NAN` or `INF`, without the
                 warning PHP 8.5 raises when one is cast; every other value as Blade writes it. --}}
            {{ is_float($value) ? \Pushery\WireKit\Support\NumericProp::text($value) : $value }}
        </span>
        @if($formattedDelta !== null)
            {{-- `role="img"` because the name below has to survive: `aria-label` is
                 prohibited on the implicit `generic` role of a bare <span>, so screen
                 readers were free to drop it — and mostly did, leaving "+8.4%" with
                 no direction and the documented announcement never happening. A span
                 with an image role is the same shape the sparkline below uses: the
                 visible glyph stays, and the name says what it means. --}}
            <span
                @if($deltaAriaLabel) role="img" aria-label="{{ $deltaAriaLabel }}" @endif
                class="{{ $deltaClasses }} text-[length:var(--text-wk-sm)] font-[number:var(--font-wk-heading-weight)] tabular-nums"
            >
                {{ $formattedDelta }}
            </span>
        @endif
    </span>

    {{-- Optional sparkline via chart component.

         The slot decides, not `trend`. `trend` names no glyph anywhere in this
         component: it opens this box and nothing else, so on its own
         `<x-wirekit::ticker trend="up" />` would emit a 2rem-tall element with
         nothing inside it. The comment above the <article> names the rule: a name
         that resolves to an empty element is worse than no name, because assistive
         technology announces it and then has nothing to say. --}}
    @if($trend !== null && $slot->hasActualContent())
        {{-- `role="img"` prunes everything inside it: the subtree becomes one graphic with
             one name, and whatever the slot content called itself is discarded. With that
             role and a fixed name on the wrapper, a
             `<x-wirekit-chart aria-label="Revenue, last 24 hours">` in the slot would lose
             the name the caller wrote, and every ticker on a page would announce the same
             sentence.
             The chart names itself — it falls back to `role="img" aria-label="Chart"` when
             nobody supplies one — so the wrapper's job is layout, and it does only that.

             `trendLabel` is the way back in, for a slot whose content cannot name itself: a
             bare `<svg>`, a canvas, an image sprite. Given one, the wrapper becomes the
             graphic and the name is the CALLER's rather than an invented one.

             `hasActualContent()`, not `isNotEmpty()`: `isNotEmpty()` is a strict `!== ''` and
             does not trim. Blade trims a default slot holding only whitespace before the
             component sees it, but the trimming variant answers the question the line is
             asking, so it does not depend on that staying true. --}}
        <span
            @if(filled($trendLabel)) role="img" aria-label="{{ $trendLabel }}" @endif
            class="h-8 w-full"
        >
            {{ $slot }}
        </span>
    @endif

    {{-- Footer slot --}}
    @if(\Pushery\WireKit\Support\SlotContent::given($footer ?? null))
        <span class="text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]">
            {{ $footer }}
        </span>
    @endif
</article>
