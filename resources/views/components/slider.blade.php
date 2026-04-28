@props([
    'name' => null,
    'id' => null,
    'label' => null,
    'min' => config('wirekit.components.slider.min', 0),
    'max' => config('wirekit.components.slider.max', 100),
    'step' => config('wirekit.components.slider.step', 1),
    'value' => null,
    'size' => config('wirekit.components.slider.size', 'md'),
    'showValue' => false,
    'disabled' => false,
    'scope' => null,
])

@php
    use Illuminate\Support\Str;
    use Pushery\WireKit\WireKit;

    // Slider = styled HTML <input type="range">. Native element gives us
    // arrow-key support, drag handling, and accessibility for free; we only
    // need to style the track + thumb via CSS variables.
    $sliderId = $id ?? ($name ? 'wk-slider-' . $name : 'wk-slider-' . Str::random(6));
    $currentValue = $value ?? $min;

    // Track height per size token.
    $trackHeight = match ($size) {
        'sm' => 'h-1',
        'lg' => 'h-3',
        default => 'h-2',
    };

    // Wrapper gives us space for the thumb's vertical overflow.
    $wrapperClasses = WireKit::resolveClasses('slider', 'wrapper', 'flex items-center gap-[var(--padding-wk-x-sm)] w-full', $scope);

    // The native input — we make the thumb and track visible via `wk-slider`
    // utility class (see wirekit.css). Uses accent color for the fill.
    $inputClasses = WireKit::resolveClasses('slider', 'input', implode(' ', [
        'wk-slider',
        'flex-1',
        'appearance-none',
        'bg-transparent',
        'cursor-pointer',
        'focus-visible:outline-none',
        'disabled:opacity-[var(--opacity-wk-disabled)]',
        'disabled:cursor-not-allowed',
        $trackHeight,
    ]), $scope);

    // Live value display next to the slider.
    $valueClasses = WireKit::resolveClasses('slider', 'value', implode(' ', [
        'tabular-nums',
        'text-[length:var(--text-wk-sm)]',
        'text-[var(--color-wk-text)]',
        'min-w-[2.5ch]',
        'text-right',
    ]), $scope);

    // Accessible-name fallback. WCAG 2.1 (4.1.2) — every form input must
    // have a programmatically-determinable name. When no visible `label`
    // prop is set AND no `aria-label` / `aria-labelledby` is passed via
    // attributes, derive a sr-only fallback from `name` (humanized).
    $hasExplicitAriaName = $attributes->has('aria-label') || $attributes->has('aria-labelledby');
    $needsSrOnlyFallback = ! $label && ! $hasExplicitAriaName;
    $fallbackLabel = $name ? Str::headline((string) $name) : 'Slider';
@endphp

{{-- Alpine tracks the current value so the display updates on input.
     @input keeps `current` in sync as user drags. --}}
<div
    x-data="{ current: @js((string) $currentValue) }"
    class="{{ $wrapperClasses }}"
>
    @if($label)
        <label for="{{ $sliderId }}" class="text-[length:var(--text-wk-sm)] text-[var(--color-wk-text)]">{{ $label }}</label>
    @elseif($needsSrOnlyFallback)
        {{-- sr-only label fallback so the input always has an accessible
             name (axe rule "label" / WCAG 4.1.2). --}}
        <label for="{{ $sliderId }}" class="sr-only">{{ $fallbackLabel }}</label>
    @endif

    <input
        type="range"
        @if($name) name="{{ $name }}" @endif
        id="{{ $sliderId }}"
        min="{{ $min }}"
        max="{{ $max }}"
        step="{{ $step }}"
        :value="current"
        @input="current = $event.target.value"
        @if($disabled) disabled @endif
        {{ $attributes->class([$inputClasses]) }}
    />
    @if($showValue)
        {{-- aria-live="polite" so screen readers get the updated value when
             the user releases the slider, not on every tick. --}}
        <span class="{{ $valueClasses }}" aria-live="polite" x-text="current"></span>
    @endif
</div>
