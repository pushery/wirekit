@props([
    'name' => null,
    'id' => null,
    'value' => '#000000',
    'size' => config('wirekit.components.color-picker.size', 'md'),
    'showValue' => true,
    'disabled' => false,
    'scope' => null,
])

@php
    use Illuminate\Support\Str;
    use Pushery\WireKit\WireKit;

    // Color picker wraps the native <input type="color"> for maximum
    // compatibility. The native picker gives us the OS-level color dialog
    // on every major platform — no custom UI needed for the core feature.
    $pickerId = $id ?? ($name ? 'wk-color-' . $name : 'wk-color-' . Str::random(6));

    // Sizing — circular swatch that mirrors other form control heights.
    $swatchSize = match ($size) {
        'sm' => 'w-8 h-8',
        'lg' => 'w-12 h-12',
        default => 'w-10 h-10',
    };

    $wrapperClasses = WireKit::resolveClasses('color-picker', 'wrapper', 'inline-flex items-center gap-[var(--padding-wk-x-sm)]', $scope);

    // Swatch wrapper. We clip the native input to a round shape via overflow-hidden
    // on the wrapper and absolute positioning on the (hidden but functional) input.
    $swatchClasses = WireKit::resolveClasses('color-picker', 'swatch', implode(' ', [
        'relative inline-block',
        'rounded-full',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'overflow-hidden',
        'cursor-pointer',
        'focus-within:ring-[length:var(--ring-wk-width)]',
        'focus-within:ring-[var(--color-wk-ring)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        $swatchSize,
    ]), $scope);

    // Native input: full-bleed inside the swatch wrapper. We make it slightly
    // larger than its container so the color fills the circle without clipping
    // borders inside the platform-default square swatch.
    $inputClasses = WireKit::resolveClasses('color-picker', 'input', implode(' ', [
        'absolute -inset-1',
        'w-[calc(100%+0.5rem)] h-[calc(100%+0.5rem)]',
        'cursor-pointer',
        'border-0 p-0 bg-transparent',
        'disabled:cursor-not-allowed',
    ]), $scope);

    // Hex value readout next to the swatch.
    $valueClasses = WireKit::resolveClasses('color-picker', 'value', implode(' ', [
        'font-[family-name:var(--font-wk-mono)]',
        'text-[length:var(--text-wk-sm)]',
        'text-[var(--color-wk-text)]',
        'uppercase tracking-wider',
    ]), $scope);
@endphp

{{-- Alpine tracks the live value so the hex readout updates as user picks. --}}
<div
    x-data="{ current: @js($value) }"
    class="{{ $wrapperClasses }}"
>
    <label for="{{ $pickerId }}" class="{{ $swatchClasses }}">
        {{-- Native <input type="color"> — we style around it. Assistive tech
             treats it as a color input; the <label> provides the accessible name
             via the slot content (visually hidden via sr-only).

             Fallback: when no slot is provided, render an sr-only label
             derived from the `name` prop (humanized) so the input always
             has an accessible name — axe-core would otherwise flag it
             with rule "label" (form elements must have labels). --}}
        <input
            type="color"
            @if($name) name="{{ $name }}" @endif
            id="{{ $pickerId }}"
            :value="current"
            @input="current = $event.target.value"
            @if($disabled) disabled @endif
            {{ $attributes->class([$inputClasses]) }}
        />
        @if(trim((string) $slot) !== '')
            <span class="sr-only">{{ $slot }}</span>
        @else
            <span class="sr-only">{{ $name ? Str::headline((string) $name) . ' colour' : 'Colour picker' }}</span>
        @endif
    </label>
    @if($showValue)
        {{-- Live hex value — updates as user picks. aria-live so AT announces. --}}
        <span class="{{ $valueClasses }}" x-text="current" aria-live="polite"></span>
    @endif
</div>
