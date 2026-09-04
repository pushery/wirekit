{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'label' => null,
    'value' => null,
    'delta' => null,
    'deltaFormat' => 'percent',
    'intent' => null,
    'trend' => null,
    'size' => config('wirekit.components.ticker.size', 'md'),
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('ticker', $attributes->getAttributes());

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
    $formattedDelta = null;
    if ($delta !== null) {
        $deltaText = is_string($delta) ? ltrim($delta, '+') : (string) $delta;
        $sign = is_numeric($deltaText) && (float) $deltaText > 0 ? '+' : '';
        $formattedDelta = $deltaFormat === 'percent'
            ? "{$sign}{$deltaText}%"
            : "{$sign}{$deltaText}";
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
    $valueSizeClass = match ($size) {
        'sm' => 'text-[length:var(--text-wk-lg)]',
        'md' => 'text-[length:var(--text-wk-xl)]',
        'lg' => 'text-[length:var(--text-wk-2xl)]',
        default => WireKit::validateProp('ticker', 'size', $size, ['sm', 'md', 'lg']),
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
    // the reader's own language ("8.4%" → "8,4 Prozent"), so the unit does not need
    // a key of its own and the number matches the one on screen.
    $deltaMagnitude = $delta !== null
        ? abs(is_numeric($delta) ? $delta : (float) ltrim((string) $delta, '+')).($deltaFormat === 'percent' ? '%' : '')
        : null;

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
    @if(filled($label)) aria-labelledby="{{ $tickerId }}-label" @endif
    {{ $attributes->class([$baseClasses]) }}
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
            {{ $value }}
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

    {{-- Optional sparkline via chart component --}}
    @if($trend !== null)
        <span role="img" aria-label="{{ __('wirekit::Trend visualization') }}" class="h-8 w-full">
            {{ $slot }}
        </span>
    @endif

    {{-- Footer slot --}}
    @if(isset($footer))
        <span class="text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]">
            {{ $footer }}
        </span>
    @endif
</article>
