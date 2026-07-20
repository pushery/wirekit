@props([
    // A11y: render the error message in a polite live region by default so a
    // server-side validation error that appears after submit (when focus is
    // elsewhere) is announced. Mirrors the input component. Set false to opt out.
    'announceError' => config('wirekit.a11y.announce_error', true),
    'label' => null,
    'hint' => null,
    'error' => null,
    'size' => config('wirekit.components.password-input.size', 'md'),
    'toggle' => true,
    'strengthMeter' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props.
    WireKit::warnUnknownProps('password-input', $attributes->getAttributes());

    $id = $attributes->get('id', $attributes->get('name', 'password-input-' . \Illuminate\Support\Str::random(6)));
    $name = $attributes->get('name', $id);

    $hasError = $error || ($errors ?? null)?->has($name);
    $errorMessage = $error ?? ($errors ?? null)?->first($name);

    // Base input classes — same as standard input
    $inputClasses = WireKit::resolveClasses('password-input', 'base', implode(' ', [
        'block w-full',
        'font-[family-name:var(--font-wk-sans)]',
        'tracking-[var(--font-wk-letter-spacing)]',
        'bg-[var(--color-wk-bg-input)]',
        'text-[color:var(--color-wk-text)]',
        'placeholder:text-[color:var(--color-wk-text-placeholder)]',
        'border-[length:var(--border-wk-width)]',
        'shadow-[var(--shadow-wk-sm)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'ease-[var(--transition-wk-easing)]',
        'hover:border-[var(--color-wk-border-strong-hover)]',
        'focus:outline-none',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-offset-[length:var(--ring-wk-offset)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'focus-visible:ring-offset-[var(--color-wk-ring-offset)]',
        'disabled:opacity-[var(--opacity-wk-disabled)]',
        'disabled:cursor-not-allowed',
        $toggle ? 'pr-[var(--size-wk-md)]' : '',
    ]), $scope);

    $stateClasses = $hasError
        ? 'border-[var(--color-wk-border-error)] focus-visible:ring-[var(--color-wk-danger)]'
        : 'border-[var(--color-wk-border-strong)]';

    $sizeClasses = match ($size) {
        'sm' => implode(' ', [
            'h-[var(--size-wk-sm)]',
            'px-[var(--padding-wk-x-sm)]',
            'text-[length:var(--text-wk-sm)]',
            'rounded-[var(--radius-wk-sm)]',
        ]),
        'lg' => implode(' ', [
            'h-[var(--size-wk-lg)]',
            'px-[var(--padding-wk-x-lg)]',
            'text-[length:var(--text-wk-lg)]',
            'rounded-[var(--radius-wk-md)]',
        ]),
        default => implode(' ', [
            'h-[var(--size-wk-md)]',
            'px-[var(--padding-wk-x-md)]',
            'text-[length:var(--text-wk-md)]',
            'rounded-[var(--radius-wk-md)]',
        ]),
    };

    $describedBy = trim(
        ($hint && !$hasError ? $id . '-hint' : '') . ' '
        . ($hasError ? $id . '-error' : '') . ' '
        . ($strengthMeter ? $id . '-strength' : '')
    );
@endphp

<div class="space-y-1.5" x-data="{
    showPassword: false,
    @if($strengthMeter)
        password: '',
        get strength() {
            const pw = this.password;
            if (!pw) return 0;
            let score = 0;
            if (pw.length >= 8) score++;
            if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) score++;
            if (/\d/.test(pw)) score++;
            if (/[^a-zA-Z0-9]/.test(pw)) score++;
            return score;
        },
        barColor(index) {
            if (index >= this.strength) return 'var(--color-wk-bg-muted)';
            if (this.strength <= 1) return 'var(--color-wk-danger)';
            if (this.strength === 2) return 'var(--color-wk-warning)';
            if (this.strength === 3) return 'var(--color-wk-warning)';
            return 'var(--color-wk-success)';
        },
    @endif
}">
    @if($label)
        <x-wirekit::label :for="$id">{{ $label }}</x-wirekit::label>
    @endif

    <div class="relative">
        <input
            :type="showPassword ? 'text' : 'password'"
            id="{{ $id }}"
            name="{{ $name }}"
            @if($strengthMeter) x-model="password" @endif
            @if($hasError) aria-invalid="true" @endif
            @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            {{-- wk-field: 16px iOS-zoom floor on phones (dist/wirekit.css) --}}
            {{ $attributes->class(['wk-field', $inputClasses, $stateClasses, $sizeClasses]) }}
        />

        {{-- Toggle visibility button --}}
        @if($toggle)
            <button
                type="button"
                class="absolute inset-y-0 right-0 flex items-center px-[var(--padding-wk-x-sm)] cursor-pointer text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-text)] transition-colors duration-[var(--transition-wk-duration)]"
                @click="showPassword = !showPassword"
                {{-- Static aria-label guards pre-Alpine render (axe scans DOM
                     before hydration may complete). :aria-label overrides live. --}}
                aria-label="Show password"
                :aria-label="showPassword ? 'Hide password' : 'Show password'"
            >
                {{-- Eye icon (show) --}}
                <svg x-show="!showPassword" aria-hidden="true" class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z"/>
                    <circle cx="8" cy="8" r="2"/>
                </svg>
                {{-- Eye-off icon (hide) --}}
                <svg x-show="showPassword" x-cloak aria-hidden="true" class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z"/>
                    <circle cx="8" cy="8" r="2"/>
                    <path d="M2 14L14 2" stroke-width="2"/>
                </svg>
            </button>
        @endif
    </div>

    {{-- Strength meter — 4 bars that fill based on password complexity score.
         Score: +1 for length≥8, +1 mixed case, +1 digit, +1 symbol. --}}
    @if($strengthMeter)
        <div id="{{ $id }}-strength" role="status" aria-live="polite" class="flex gap-1">
            <template x-for="i in 4" :key="i">
                <div
                    class="h-1 flex-1 rounded-full transition-colors duration-[var(--transition-wk-duration)]"
                    :style="'background-color:' + barColor(i - 1)"
                ></div>
            </template>
        </div>
    @endif

    @if($hasError && $errorMessage)
        <p id="{{ $id }}-error" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @elseif($hint)
        <p id="{{ $id }}-hint" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
</div>
