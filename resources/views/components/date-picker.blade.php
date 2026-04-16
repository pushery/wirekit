@props([
    'name' => null,
    'id' => null,
    'value' => null,
    'min' => null,
    'max' => null,
    'size' => config('wirekit.components.date-picker.size', 'md'),
    'disabled' => false,
    'required' => false,
    'placeholder' => null,
    'error' => null,
    'hint' => null,
    'scope' => null,
])

@php
    use Illuminate\Support\Str;
    use Pushery\WireKit\WireKit;

    // Date picker wraps native <input type="date"> for maximum accessibility
    // and zero-dependency operation. The browser provides its own calendar
    // popup + keyboard navigation (arrow keys, PageUp/PageDown for months,
    // etc.), and ships localized to the user's OS locale automatically.
    $dateId = $id ?? ($name ? 'wk-date-' . $name : 'wk-date-' . Str::random(6));
    $errorId = $dateId . '-error';
    $hintId = $dateId . '-hint';

    $hasError = $error || ($errors ?? null)?->has($name);
    $errorMessage = $error ?? ($hasError && $name ? $errors->first($name) : null);

    // Sizing shared with other form controls for visual consistency.
    $heightClasses = match ($size) {
        'sm' => 'h-[var(--size-wk-sm)] text-[length:var(--text-wk-sm)]',
        'lg' => 'h-[var(--size-wk-lg)] text-[length:var(--text-wk-lg)]',
        default => 'h-[var(--size-wk-md)] text-[length:var(--text-wk-md)]',
    };

    $inputClasses = WireKit::resolveClasses('date-picker', 'input', implode(' ', [
        'w-full',
        'px-[var(--padding-wk-x-md)]',
        'bg-[var(--color-wk-bg-input)]',
        'text-[var(--color-wk-text)]',
        'placeholder:text-[var(--color-wk-text-placeholder)]',
        'border-[length:var(--border-wk-width)]',
        $hasError ? 'border-[var(--color-wk-border-error)]' : 'border-[var(--color-wk-border)]',
        'rounded-[var(--radius-wk-md)]',
        'focus:outline-none',
        'focus:ring-[length:var(--ring-wk-width)]',
        'focus:ring-[var(--color-wk-ring)]',
        'focus:border-[var(--color-wk-accent)]',
        'disabled:opacity-[var(--opacity-wk-disabled)]',
        'disabled:cursor-not-allowed',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        $heightClasses,
    ]), $scope);

    // Build aria-describedby from hint + error ids conditionally.
    $describedBy = trim(($hint ? $hintId : '') . ' ' . ($hasError ? $errorId : ''));
@endphp

<div class="w-full">
    <input
        type="date"
        @if($name) name="{{ $name }}" @endif
        id="{{ $dateId }}"
        @if($value) value="{{ $value }}" @endif
        @if($min) min="{{ $min }}" @endif
        @if($max) max="{{ $max }}" @endif
        @if($placeholder) placeholder="{{ $placeholder }}" @endif
        @if($disabled) disabled @endif
        @if($required) required aria-required="true" @endif
        @if($hasError) aria-invalid="true" @endif
        @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->class([$inputClasses]) }}
    />

    @if($hint && !$hasError)
        <p id="{{ $hintId }}" class="mt-[var(--padding-wk-y-xs)] text-[length:var(--text-wk-xs)] text-[var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif

    @if($hasError)
        {{-- Error message linked via aria-describedby for assistive tech. --}}
        <p id="{{ $errorId }}" class="mt-[var(--padding-wk-y-xs)] text-[length:var(--text-wk-xs)] text-[var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @endif
</div>
