@props([
    'value' => null,             // numeric value (0..max). null = indeterminate
    'max' => 100,                // max value the bar represents
    'label' => null,             // visible label rendered above the bar
    'showValue' => false,        // show "42 / 100" next to label
    'variant' => config('wirekit.components.progress.variant', 'accent'), // accent | success | warning | danger
    'size' => config('wirekit.components.progress.size', 'md'),           // sm | md | lg
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Clamp the value to [0, max] and compute percentage for fill width
    $isIndeterminate = $value === null;
    $clamped = $isIndeterminate ? 0 : max(0, min((float) $value, (float) $max));
    $percent = $max > 0 ? ($clamped / $max) * 100 : 0;

    // Track (the background bar) — use design tokens for color + radius
    $trackClasses = WireKit::resolveClasses('progress', 'track', implode(' ', [
        'relative w-full overflow-hidden',
        'rounded-[var(--radius-wk-full)]',
        'bg-[var(--color-wk-bg-muted)]',
    ]), $scope);

    // Track height scales with the size prop
    $heightClass = match ($size) {
        'sm' => 'h-1',
        'lg' => 'h-3',
        default => 'h-2',
    };

    // Fill color changes with semantic variant — always via design tokens
    $fillColor = match ($variant) {
        'success' => 'bg-[var(--color-wk-success)]',
        'warning' => 'bg-[var(--color-wk-warning)]',
        'danger' => 'bg-[var(--color-wk-danger)]',
        default => 'bg-[var(--color-wk-accent)]',
    };

    // Determinate: animate width transitions for smooth updates.
    // Indeterminate: rely on .wk-progress-indeterminate keyframes (see dist/wirekit.css)
    $fillClasses = $isIndeterminate
        ? $fillColor . ' absolute inset-y-0 rounded-[var(--radius-wk-full)] wk-progress-indeterminate'
        : $fillColor . ' h-full rounded-[var(--radius-wk-full)] transition-[width] duration-[var(--transition-wk-duration)] ease-[var(--transition-wk-easing)]';

    // Auto-generate an id so label + progressbar can be linked via aria-labelledby
    $labelId = 'progress-' . \Illuminate\Support\Str::random(6) . '-label';
@endphp

<div {{ $attributes->class(['w-full font-[family-name:var(--font-wk-sans)]']) }}>
    @if($label || $showValue)
        <div class="mb-1 flex items-center justify-between text-[length:var(--text-wk-sm)]">
            @if($label)
                <span id="{{ $labelId }}" class="text-[var(--color-wk-text)]">{{ $label }}</span>
            @else
                <span></span>
            @endif
            @if($showValue && ! $isIndeterminate)
                <span class="text-[var(--color-wk-text-muted)] tabular-nums">
                    {{ (int) $clamped }} / {{ (int) $max }}
                </span>
            @endif
        </div>
    @endif

    {{-- role="progressbar" + aria-value* are the WCAG/WAI-ARIA contract for progress indicators --}}
    <div
        role="progressbar"
        @if($label) aria-labelledby="{{ $labelId }}" @endif
        @if(! $isIndeterminate)
            aria-valuenow="{{ (int) $clamped }}"
            aria-valuemin="0"
            aria-valuemax="{{ (int) $max }}"
        @endif
        class="{{ $trackClasses }} {{ $heightClass }}"
    >
        @if($isIndeterminate)
            <div class="{{ $fillClasses }}"></div>
        @else
            <div class="{{ $fillClasses }}" style="width: {{ $percent }}%"></div>
        @endif
    </div>
</div>
