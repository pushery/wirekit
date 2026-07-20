@props([
    // A11y: render the error message in a polite live region by default so a
    // server-side validation error that appears after submit (when focus is
    // elsewhere) is announced. Mirrors the input component. Set false to opt out.
    'announceError' => config('wirekit.a11y.announce_error', true),
    'name' => null,
    'id' => null,
    'label' => null,
    'value' => null,
    // false (default) renders one native date input. true renders a linked
    // start + end pair (two native inputs) — see the range parsing below.
    'range' => false,
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
    $isRange = filter_var($range, FILTER_VALIDATE_BOOLEAN);

    // Range value can be an array ['start' => .., 'end' => ..] or a slash string
    // "Y-m-d/Y-m-d" (e.g. "2025-01-20/2025-02-09"). Single mode keeps `value`.
    $startValue = null;
    $endValue = null;
    if ($isRange) {
        if (is_array($value)) {
            $startValue = $value['start'] ?? ($value[0] ?? null);
            $endValue = $value['end'] ?? ($value[1] ?? null);
        } elseif (is_string($value) && str_contains($value, '/')) {
            [$startValue, $endValue] = array_pad(explode('/', $value, 2), 2, null);
        }
    }

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
        'text-[color:var(--color-wk-text)]',
        'placeholder:text-[color:var(--color-wk-text-placeholder)]',
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

    // Accessible-name fallback. WCAG 2.1 (4.1.2) requires every input to
    // have a programmatically-determinable name. When no visible `label`
    // prop is set AND no `aria-label` / `aria-labelledby` is passed via
    // attributes, derive a sr-only fallback from `name` (humanized) so
    // screen readers always announce something. Caller-provided
    // `aria-label` always wins.
    $hasExplicitAriaName = $attributes->has('aria-label') || $attributes->has('aria-labelledby');
    $needsSrOnlyFallback = ! $label && ! $hasExplicitAriaName;
    $fallbackLabel = $name ? Str::headline((string) $name) : 'Date';
@endphp

<div class="w-full">
    @if($label)
        <label for="{{ $dateId }}" class="block mb-[var(--padding-wk-y-xs)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text)]">
            {{ $label }}
            @if($required)<span aria-hidden="true" class="text-[color:var(--color-wk-danger-text)]">&nbsp;*</span>@endif
        </label>
    @elseif($needsSrOnlyFallback)
        {{-- Screen-reader-only label fallback. Visible-label-less demos still
             pass WCAG 4.1.2 because the input has a programmatically-
             determinable name. --}}
        <label for="{{ $dateId }}" class="sr-only">{{ $fallbackLabel }}</label>
    @endif

    @if($isRange)
        {{-- Range = two native date inputs. A tiny Alpine scope holds the current
             start (s) + end (e) so each input can constrain the other reactively:
             the end can't precede the start, the start can't follow the end. This
             is additive — it doesn't touch the values, so wire:model / native form
             submission of the {name}[start] / {name}[end] fields still works. --}}
        <div
            x-data="{ s: @js($startValue), e: @js($endValue) }"
            class="flex items-center gap-[var(--padding-wk-x-sm)]"
        >
            <input
                type="date"
                @if($name) name="{{ $name }}[start]" @endif
                id="{{ $dateId }}"
                value="{{ $startValue }}"
                x-on:change="s = $event.target.value"
                :min="@js($min)"
                :max="e || @js($max)"
                @if($disabled) disabled @endif
                @if($required) required aria-required="true" @endif
                @if($hasError) aria-invalid="true" @endif
                @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
                @unless($label) aria-label="{{ $fallbackLabel }} start" @endunless
                class="wk-field {{ $inputClasses }}"
            />
            <span aria-hidden="true" class="shrink-0 text-[color:var(--color-wk-text-muted)]">&ndash;</span>
            <input
                type="date"
                @if($name) name="{{ $name }}[end]" @endif
                id="{{ $dateId }}-end"
                value="{{ $endValue }}"
                x-on:change="e = $event.target.value"
                :min="s || @js($min)"
                :max="@js($max)"
                @if($disabled) disabled @endif
                @if($required) required aria-required="true" @endif
                @if($hasError) aria-invalid="true" @endif
                @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
                aria-label="{{ $label ?: $fallbackLabel }} end"
                class="wk-field {{ $inputClasses }}"
            />
        </div>
    @else
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
            {{-- wk-field: 16px iOS-zoom floor on phones (dist/wirekit.css) --}}
            {{ $attributes->class(['wk-field', $inputClasses]) }}
        />
    @endif

    @if($hint && !$hasError)
        <p id="{{ $hintId }}" class="mt-[var(--padding-wk-y-xs)] text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif

    @if($hasError)
        {{-- Error message linked via aria-describedby for assistive tech. --}}
        <p id="{{ $errorId }}" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="mt-[var(--padding-wk-y-xs)] text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @endif
</div>
