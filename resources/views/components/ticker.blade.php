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

    // Delta aria label for screen readers
    $deltaAriaLabel = $delta !== null
        ? 'Change: ' . ($delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'unchanged')) . ' ' . abs($delta) . ($deltaFormat === 'percent' ? ' percent' : '')
        : null;

    $tickerId = 'ticker-' . md5($label ?? 'default');
@endphp

<article
    aria-labelledby="{{ $tickerId }}-label"
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
            <span
                class="{{ $deltaClasses }} text-[length:var(--text-wk-sm)] font-[number:var(--font-wk-heading-weight)] tabular-nums"
                @if($deltaAriaLabel) aria-label="{{ $deltaAriaLabel }}" @endif
            >
                {{ $formattedDelta }}
            </span>
        @endif
    </span>

    {{-- Optional sparkline via chart component --}}
    @if($trend !== null)
        <span role="img" aria-label="Trend visualization" class="h-8 w-full">
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
