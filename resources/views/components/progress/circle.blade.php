{{-- DEPRECATED: superseded by the radial-progress component, which is the canonical
     radial (circular) progress — richer (threshold coloring, valueText, a required
     accessible name) and consistently named. This sub-component still renders for
     back-compat; it will be removed in v3.0.0. New code should use radial-progress.
     (Component tag omitted from this comment on purpose — Blade compiles component
     tags even inside comments.) --}}
@props([
    'value' => null,
    'max' => 100,
    'label' => null,
    'showValue' => false,
    'variant' => config('wirekit.components.progress.variant', 'accent'),
    'size' => config('wirekit.components.progress.circle-size', 'md'),
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Determine if indeterminate (no value provided)
    $isIndeterminate = $value === null;
    $clamped = $isIndeterminate ? 0 : max(0, min((float) $value, (float) $max));
    $percent = $max > 0 ? ($clamped / $max) * 100 : 0;

    // SVG dimensions scale with size prop — use design tokens where available,
    // with fallback CSS values for sizes not in the core token set.
    $dimensions = match ($size) {
        'sm' => 'w-[var(--size-wk-sm)] h-[var(--size-wk-sm)]',
        'lg' => 'w-[var(--size-wk-lg)] h-[var(--size-wk-lg)]',
        'xl' => 'w-[var(--size-wk-xl,6rem)] h-[var(--size-wk-xl,6rem)]',
        default => 'w-[var(--size-wk-md)] h-[var(--size-wk-md)]',
    };

    // Circle geometry: radius 15.9155 gives circumference ~100 for easy percentage
    $radius = 15.9155;
    $circumference = 2 * M_PI * $radius;
    $dashOffset = $circumference - ($percent / 100) * $circumference;

    // Stroke color per variant — matches linear progress token usage
    $strokeColor = match ($variant) {
        'success' => 'var(--color-wk-success)',
        'warning' => 'var(--color-wk-warning)',
        'danger' => 'var(--color-wk-danger)',
        default => 'var(--color-wk-accent)',
    };

    // Value text font size scales with circle size — must be small enough
    // to fit "100%" inside the circle without overflowing.
    $textSize = match ($size) {
        'sm' => 'text-[length:0.5rem]',
        'lg' => 'text-[length:0.75rem]',
        'xl' => 'text-[length:1rem]',
        default => 'text-[length:0.625rem]',
    };

    $wrapperClasses = WireKit::resolveClasses('progress', 'circle', implode(' ', [
        'inline-flex flex-col items-center gap-1',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);

    // Auto-generate id for aria-labelledby
    $labelId = 'progress-circle-' . \Illuminate\Support\Str::random(6) . '-label';
@endphp

<div {{ $attributes->class([$wrapperClasses]) }}>
    {{-- SVG circular progress indicator --}}
    <div class="relative {{ $dimensions }}">
        <svg
            role="progressbar"
            @if($label) aria-labelledby="{{ $labelId }}" @endif
            @if(! $isIndeterminate)
                aria-valuenow="{{ (int) $clamped }}"
                aria-valuemin="0"
                aria-valuemax="{{ (int) $max }}"
            @else
                aria-label="Loading"
            @endif
            class="w-full h-full -rotate-90"
            viewBox="0 0 36 36"
        >
            {{-- Track circle (background) --}}
            <circle
                cx="18"
                cy="18"
                r="{{ $radius }}"
                fill="none"
                stroke="var(--color-wk-bg-muted)"
                stroke-width="3"
            />

            {{-- Fill circle (progress) --}}
            @if($isIndeterminate)
                {{-- Indeterminate: partial arc that rotates via CSS animation --}}
                <circle
                    cx="18"
                    cy="18"
                    r="{{ $radius }}"
                    fill="none"
                    stroke="{{ $strokeColor }}"
                    stroke-width="3"
                    stroke-linecap="round"
                    stroke-dasharray="{{ $circumference * 0.25 }} {{ $circumference * 0.75 }}"
                    class="wk-progress-circle-indeterminate origin-center"
                />
            @else
                <circle
                    cx="18"
                    cy="18"
                    r="{{ $radius }}"
                    fill="none"
                    stroke="{{ $strokeColor }}"
                    stroke-width="3"
                    stroke-linecap="round"
                    stroke-dasharray="{{ $circumference }}"
                    stroke-dashoffset="{{ $dashOffset }}"
                    style="transition: stroke-dashoffset var(--transition-wk-duration) var(--transition-wk-easing)"
                />
            @endif
        </svg>

        {{-- Center value text (only when showValue and determinate) --}}
        @if($showValue && ! $isIndeterminate)
            <div class="absolute inset-0 flex items-center justify-center {{ $textSize }} font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)] tabular-nums" aria-hidden="true">
                {{ (int) $percent }}%
            </div>
        @endif
    </div>

    {{-- Optional visible label below the circle --}}
    @if($label)
        <span id="{{ $labelId }}" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">
            {{ $label }}
        </span>
    @endif
</div>
