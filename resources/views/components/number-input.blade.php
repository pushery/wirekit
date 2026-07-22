@props([
    // A11y: render the error message in a polite live region by default so a
    // server-side validation error that appears after submit (when focus is
    // elsewhere) is announced. Mirrors the input component. Set false to opt out.
    'announceError' => null,
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

@aware(['announceErrors' => null])

@php
    // announce-error precedence: explicit prop > form container (@aware announceErrors) > global config.
    $announceError ??= $announceErrors ?? config('wirekit.a11y.announce_error', true);

    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props.
    WireKit::warnUnknownProps('number-input', $attributes->getAttributes());

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
        'text-[color:var(--color-wk-text)]',
        'text-center tabular-nums',
        'placeholder:text-[color:var(--color-wk-text-placeholder)]',
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
        : 'border-[var(--color-wk-border-strong)]';

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
        : 'border-[var(--color-wk-border-strong)]';

    // Stepper button classes — shared for both decrease/increase
    $buttonClasses = implode(' ', [
        'inline-flex items-center justify-center',
        'bg-[var(--color-wk-bg-subtle)]',
        'text-[color:var(--color-wk-text-muted)]',
        'border-[length:var(--border-wk-width)]',
        $buttonBorderColor,
        'hover:bg-[var(--color-wk-bg-muted)]',
        'hover:text-[color:var(--color-wk-text)]',
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

{{--
    Alpine tracks the current value to reactively disable stepper buttons
    at min/max boundaries. The `precision` getter pulls the decimal-place
    count out of the step value's string representation so we can snap the
    result of every increment / decrement back to that precision — without
    this, JS binary floating-point arithmetic produces drift like
    19.01 + 0.01 = 19.020000000000003 (visible as "19,02000…" on the German
    locale display) and the same value oscillates between displayed
    representations on every click. Snapping with `Number(next.toFixed(p))`
    is the canonical fix.
--}}
<div
    class="space-y-1.5"
    x-data="{
        value: {{ $attributes->get('value', $min ?? 0) }},
        min: {{ $min !== null ? $min : 'null' }},
        max: {{ $max !== null ? $max : 'null' }},
        step: {{ $step }},
        get precision() {
            // Decimal places implied by the step value. `5` → 0, `0.1` → 1,
            // `0.01` → 2, `0.001` → 3. Falls back to 0 for non-fractional
            // or scientific-notation step values; the round() below is a
            // no-op when precision is 0 so integer steps stay exact.
            const s = String(this.step);
            const dot = s.indexOf('.');
            return dot === -1 ? 0 : s.length - dot - 1;
        },
        round(n) {
            // toFixed() returns a string; Number() converts it back so
            // the input element displays the unpadded representation
            // (e.g. `19.02` not `19.020000000000003`).
            return Number(n.toFixed(this.precision));
        },
        get atMin() { return this.min !== null && this.value <= this.min; },
        get atMax() { return this.max !== null && this.value >= this.max; },
        // Snap to the step grid anchored at `min` (or 0 when no min is set).
        // Starting from an off-grid value (e.g. value=1.78, step=0.1), `+` must
        // move to the next grid point ABOVE (1.8, not 1.78+0.1=1.88 which the
        // old code then rounded to 1.9 via toFixed(1) — skipping 1.8 entirely).
        // Matches the W3C native <input type=number> stepper contract.
        // The 1e-10 tolerance absorbs binary-float drift so on-grid values
        // like 1.8 (stored as 1.7999999998) advance to the correct next step.
        decrease() {
            const origin = this.min !== null ? this.min : 0;
            const ratio = (this.value - origin) / this.step;
            const prevSteps = Math.ceil(ratio - 1e-10) - 1;
            const next = this.round(origin + prevSteps * this.step);
            this.value = this.min !== null ? Math.max(this.min, next) : next;
        },
        increase() {
            const origin = this.min !== null ? this.min : 0;
            const ratio = (this.value - origin) / this.step;
            const nextSteps = Math.floor(ratio + 1e-10) + 1;
            const next = this.round(origin + nextSteps * this.step);
            this.value = this.max !== null ? Math.min(this.max, next) : next;
        },
        clamp(val) {
            let v = Number(val);
            if (isNaN(v)) v = this.min ?? 0;
            if (this.min !== null) v = Math.max(this.min, v);
            if (this.max !== null) v = Math.min(this.max, v);
            return this.round(v);
        }
    }"
>
    @if($label)
        <x-wirekit::label :for="$id">{{ $label }}</x-wirekit::label>
    @endif

    <div class="flex items-center">
        {{-- Prefix — inline before the stepper group --}}
        @if($prefix)
            <span class="shrink-0 pr-[var(--padding-wk-x-sm)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]" aria-hidden="true">{{ $prefix }}</span>
        @endif

        {{-- Decrease button — disabled at min boundary --}}
        <button
            type="button"
            class="{{ $buttonClasses }} {{ $buttonPadding }} {{ $radiusLeft }} {{ $sizeClasses }}"
            aria-label="{{ __('Decrease') }}"
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
            {{-- wk-field: 16px iOS-zoom floor on phones (dist/wirekit.css) --}}
            {{ $attributes->class(['wk-field', $inputClasses, $stateClasses, $sizeClasses]) }}
        />

        {{-- Increase button — disabled at max boundary --}}
        <button
            type="button"
            class="{{ $buttonClasses }} {{ $buttonPadding }} {{ $radiusRight }} {{ $sizeClasses }}"
            aria-label="{{ __('Increase') }}"
            :disabled="atMax"
            :aria-disabled="atMax"
            @click="increase()"
        >
            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 16 16" fill="currentColor"><path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="2" fill="none"/></svg>
        </button>

        {{-- Suffix — inline after the stepper group --}}
        @if($suffix)
            <span class="shrink-0 pl-[var(--padding-wk-x-sm)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]" aria-hidden="true">{{ $suffix }}</span>
        @endif
    </div>

    @if($hasError && $errorMessage)
        <p id="{{ $id }}-error" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @elseif($hint)
        <p id="{{ $id }}-hint" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
</div>
