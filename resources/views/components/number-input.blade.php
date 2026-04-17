@props([
    'label' => null,
    'hint' => null,
    'error' => null,
    'size' => config('wirekit.components.number-input.size', 'md'),
    'min' => null,
    'max' => null,
    'step' => 1,
    'prefix' => null,
    'suffix' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Auto-generate ID from name attribute
    $id = $attributes->get('id', $attributes->get('name', 'number-input-' . \Illuminate\Support\Str::random(6)));
    $name = $attributes->get('name', $id);

    // Error detection: explicit prop OR Laravel validation bag
    $hasError = $error || ($errors ?? null)?->has($name);
    $errorMessage = $error ?? ($errors ?? null)?->first($name);

    // Base input classes — same foundation as standard input
    $inputClasses = WireKit::resolveClasses('number-input', 'base', implode(' ', [
        'block w-16',
        'font-[family-name:var(--font-wk-sans)]',
        'tracking-[var(--font-wk-letter-spacing)]',
        'bg-[var(--color-wk-bg-input)]',
        'text-[var(--color-wk-text)]',
        'text-center tabular-nums',
        'placeholder:text-[var(--color-wk-text-placeholder)]',
        'border-y-[length:var(--border-wk-width)]',
        'border-x-0',
        'shadow-[var(--shadow-wk-sm)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'ease-[var(--transition-wk-easing)]',
        'focus:outline-none',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'disabled:opacity-[var(--opacity-wk-disabled)]',
        'disabled:cursor-not-allowed',
        // Hide native spinner arrows
        '[appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none',
    ]), $scope);

    // Border color switches between normal and error state. Only the top/bottom
    // border is colored on the input itself because the stepper buttons sit
    // flush against it and provide the left/right border — see $buttonBorder
    // below for the matching error-aware color on those buttons.
    $stateClasses = $hasError
        ? 'border-[var(--color-wk-border-error)] focus-visible:ring-[var(--color-wk-danger)]'
        : 'border-[var(--color-wk-border)]';

    // Size classes
    $sizeClasses = match ($size) {
        'sm' => 'h-[var(--size-wk-sm)] text-[length:var(--text-wk-sm)]',
        'lg' => 'h-[var(--size-wk-lg)] text-[length:var(--text-wk-lg)]',
        default => 'h-[var(--size-wk-md)] text-[length:var(--text-wk-md)]',
    };

    // Stepper button padding scales with size variant
    $buttonPadding = match ($size) {
        'sm' => 'px-[var(--padding-wk-x-xs)]',
        'lg' => 'px-[var(--padding-wk-x-md)]',
        default => 'px-[var(--padding-wk-x-sm)]',
    };

    // Stepper button border color — must match the input's state so the entire
    // stepper group looks like a single framed control. Without this, a
    // number-input in error state shows a red line only above and below the
    // middle <input> (because the buttons kept the neutral border), producing
    // a visually broken "gapped" frame.
    $buttonBorderColor = $hasError
        ? 'border-[var(--color-wk-border-error)]'
        : 'border-[var(--color-wk-border)]';

    // Stepper button classes — shared for both decrease/increase
    $buttonClasses = implode(' ', [
        'inline-flex items-center justify-center',
        'bg-[var(--color-wk-bg-subtle)]',
        'text-[var(--color-wk-text-muted)]',
        'border-[length:var(--border-wk-width)]',
        $buttonBorderColor,
        'hover:bg-[var(--color-wk-bg-muted)]',
        'hover:text-[var(--color-wk-text)]',
        'cursor-pointer',
        'transition-colors duration-[var(--transition-wk-duration)]',
        'focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
        'disabled:opacity-[var(--opacity-wk-disabled)] disabled:cursor-not-allowed',
    ]);

    $radiusLeft = match ($size) {
        'sm' => 'rounded-l-[var(--radius-wk-sm)]',
        'lg' => 'rounded-l-[var(--radius-wk-md)]',
        default => 'rounded-l-[var(--radius-wk-md)]',
    };
    $radiusRight = match ($size) {
        'sm' => 'rounded-r-[var(--radius-wk-sm)]',
        'lg' => 'rounded-r-[var(--radius-wk-md)]',
        default => 'rounded-r-[var(--radius-wk-md)]',
    };

    // Build aria-describedby from hint + error
    $describedBy = trim(($hint && !$hasError ? $id . '-hint' : '') . ' ' . ($hasError ? $id . '-error' : ''));
@endphp

{{-- Alpine tracks the current value to reactively disable stepper buttons at min/max boundaries. --}}
<div
    class="space-y-1.5"
    x-data="{
        value: {{ $attributes->get('value', $min ?? 0) }},
        min: {{ $min !== null ? $min : 'null' }},
        max: {{ $max !== null ? $max : 'null' }},
        step: {{ $step }},
        get atMin() { return this.min !== null && this.value <= this.min; },
        get atMax() { return this.max !== null && this.value >= this.max; },
        decrease() {
            const next = this.value - this.step;
            this.value = this.min !== null ? Math.max(this.min, next) : next;
        },
        increase() {
            const next = this.value + this.step;
            this.value = this.max !== null ? Math.min(this.max, next) : next;
        },
        clamp(val) {
            let v = Number(val);
            if (isNaN(v)) v = this.min ?? 0;
            if (this.min !== null) v = Math.max(this.min, v);
            if (this.max !== null) v = Math.min(this.max, v);
            return v;
        }
    }"
>
    @if($label)
        <x-wirekit::label :for="$id">{{ $label }}</x-wirekit::label>
    @endif

    <div class="flex items-center">
        {{-- Prefix — inline before the stepper group --}}
        @if($prefix)
            <span class="shrink-0 pr-[var(--padding-wk-x-sm)] text-[length:var(--text-wk-sm)] text-[var(--color-wk-text-muted)]" aria-hidden="true">{{ $prefix }}</span>
        @endif

        {{-- Decrease button — disabled at min boundary --}}
        <button
            type="button"
            class="{{ $buttonClasses }} {{ $buttonPadding }} {{ $radiusLeft }} {{ $sizeClasses }}"
            aria-label="Decrease"
            :disabled="atMin"
            :aria-disabled="atMin"
            @click="decrease()"
        >
            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 16 16" fill="currentColor"><path d="M3 8h10" stroke="currentColor" stroke-width="2" fill="none"/></svg>
        </button>

        {{-- Number input — x-model keeps Alpine state and native input in sync --}}
        <input
            type="number"
            id="{{ $id }}"
            name="{{ $name }}"
            x-model.number="value"
            @blur="value = clamp(value)"
            @if($min !== null) min="{{ $min }}" @endif
            @if($max !== null) max="{{ $max }}" @endif
            step="{{ $step }}"
            @if($hasError) aria-invalid="true" @endif
            @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->class([$inputClasses, $stateClasses, $sizeClasses]) }}
        />

        {{-- Increase button — disabled at max boundary --}}
        <button
            type="button"
            class="{{ $buttonClasses }} {{ $buttonPadding }} {{ $radiusRight }} {{ $sizeClasses }}"
            aria-label="Increase"
            :disabled="atMax"
            :aria-disabled="atMax"
            @click="increase()"
        >
            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 16 16" fill="currentColor"><path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="2" fill="none"/></svg>
        </button>

        {{-- Suffix — inline after the stepper group --}}
        @if($suffix)
            <span class="shrink-0 pl-[var(--padding-wk-x-sm)] text-[length:var(--text-wk-sm)] text-[var(--color-wk-text-muted)]" aria-hidden="true">{{ $suffix }}</span>
        @endif
    </div>

    @if($hasError && $errorMessage)
        <p id="{{ $id }}-error" class="text-[length:var(--text-wk-sm)] text-[var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @elseif($hint)
        <p id="{{ $id }}-hint" class="text-[length:var(--text-wk-sm)] text-[var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
</div>
