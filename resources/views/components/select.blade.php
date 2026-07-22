@props([
    // A11y: render the error message in a polite live region by default so a
    // server-side validation error that appears after submit (when focus is
    // elsewhere) is announced. Mirrors the input component. Set false to opt out.
    'announceError' => null,
    'label' => null,
    'hideLabel' => false, // render the label sr-only (kept for assistive tech) — for compact toolbar / header fields
    'hint' => null,
    'error' => null,
    // Success / valid state — string shows a green confirmation below, `true`
    // shows just the green border. `error` always wins when both are set.
    'success' => null,
    'size' => config('wirekit.components.select.size', 'md'),
    'placeholder' => null,
    'options' => [],
    'scope' => null,
])

@aware(['announceErrors' => null])

@php
    // announce-error precedence: explicit prop > form container (@aware announceErrors) > global config.
    $announceError ??= $announceErrors ?? config('wirekit.a11y.announce_error', true);

    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props.
    WireKit::warnUnknownProps('select', $attributes->getAttributes());

    // Auto-generate ID from name attribute, or generate random if neither provided
    $id = $attributes->get('id', $attributes->get('name', 'select-' . \Illuminate\Support\Str::random(6)));
    $name = $attributes->get('name', $id);

    // Error detection: explicit prop OR Laravel validation bag
    $hasError = $error || ($errors ?? null)?->has($name);
    $errorMessage = $error ?? ($errors ?? null)?->first($name);

    // Success / valid state — only when there is no error (error wins).
    $hasSuccess = ! $hasError && $success !== null && $success !== false;
    $successMessage = is_string($success) ? $success : null;

    // Base classes: all values reference design tokens — no hardcoded colors or sizes
    $selectClasses = WireKit::resolveClasses('select', 'base', implode(' ', [
        'block w-full appearance-none',
        'font-[family-name:var(--font-wk-sans)]',
        'tracking-[var(--font-wk-letter-spacing)]',
        'bg-[var(--color-wk-bg-input)]',
        'text-[color:var(--color-wk-text)]',
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
        'cursor-pointer',
    ]), $scope);

    // Border color switches between error, success, and normal state — all via tokens
    $stateClasses = match (true) {
        (bool) $hasError => 'border-[var(--color-wk-border-error)] focus-visible:ring-[var(--color-wk-danger)]',
        $hasSuccess => 'border-[var(--color-wk-border-success)] focus-visible:ring-[var(--color-wk-success)]',
        default => 'border-[var(--color-wk-border-strong)]',
    };

    // Size classes: height, padding, font size, radius — all from sizing tokens
    // pr-8 kept for dropdown arrow space
    $sizeClasses = match ($size) {
        'sm' => implode(' ', [
            'h-[var(--size-wk-sm)]',
            'px-[var(--padding-wk-x-sm)]',
            'pr-8',
            'text-[length:var(--text-wk-sm)]',
            'rounded-[var(--radius-wk-sm)]',
        ]),
        'md-compact' => implode(' ', [
            'h-[var(--size-wk-md-compact)]',
            'px-[var(--padding-wk-x-md)]',
            'pr-8',
            'text-[length:var(--text-wk-sm)]',
            'rounded-[var(--radius-wk-md)]',
        ]),
        'md' => implode(' ', [
            'h-[var(--size-wk-md)]',
            'px-[var(--padding-wk-x-md)]',
            'pr-8',
            'text-[length:var(--text-wk-md)]',
            'rounded-[var(--radius-wk-md)]',
        ]),
        'lg' => implode(' ', [
            'h-[var(--size-wk-lg)]',
            'px-[var(--padding-wk-x-lg)]',
            'pr-8',
            'text-[length:var(--text-wk-lg)]',
            'rounded-[var(--radius-wk-md)]',
        ]),
        default => WireKit::validateProp('select', 'size', $size, ['sm', 'md-compact', 'md', 'lg']),
    };
@endphp

<div class="space-y-1.5">
    @if($label)
        <x-wirekit::label :for="$id" :class="$hideLabel ? 'sr-only' : ''">{{ $label }}</x-wirekit::label>
    @endif

    <div class="relative">
        <select
            id="{{ $id }}"
            name="{{ $name }}"
            @if($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            @if($hasSuccess && $successMessage && !$hasError) aria-describedby="{{ $id }}-success" @endif
            @if($hint && !$hasError && !($hasSuccess && $successMessage)) aria-describedby="{{ $id }}-hint" @endif
            {{-- wk-field: lifts font-size to the 16px iOS-zoom floor on phones (dist/wirekit.css) --}}
            {{ $attributes->class(['wk-field', $selectClasses, $stateClasses, $sizeClasses]) }}
        >
            @if($placeholder)
                <option value="" disabled selected>{{ $placeholder }}</option>
            @endif
            {{--
                Options accept three shapes (mix freely):
                  - Flat:            ['de' => 'Germany']                       → <option>
                  - Per-option attrs:['de' => ['label' => 'Germany',
                                               'disabled' => true]]            → disabled <option>
                  - Grouped:         ['Europe' => ['de' => 'Germany', ...]]    → <optgroup>
                A group is an array value WITHOUT a 'label' key; a single option
                with attributes is an array value WITH a 'label' key.
            --}}
            @foreach($options as $value => $optionLabel)
                @if(is_array($optionLabel) && ! array_key_exists('label', $optionLabel))
                    <optgroup label="{{ $value }}">
                        @foreach($optionLabel as $subValue => $subLabel)
                            @php
                                $sLabel = is_array($subLabel) ? ($subLabel['label'] ?? $subValue) : $subLabel;
                                $sDisabled = is_array($subLabel) && ! empty($subLabel['disabled']);
                            @endphp
                            <option value="{{ $subValue }}"{{ $sDisabled ? ' disabled' : '' }}>{{ $sLabel }}</option>
                        @endforeach
                    </optgroup>
                @else
                    @php
                        $oLabel = is_array($optionLabel) ? ($optionLabel['label'] ?? $value) : $optionLabel;
                        $oDisabled = is_array($optionLabel) && ! empty($optionLabel['disabled']);
                    @endphp
                    <option value="{{ $value }}"{{ $oDisabled ? ' disabled' : '' }}>{{ $oLabel }}</option>
                @endif
            @endforeach
            {{ $slot }}
        </select>

        {{-- Dropdown arrow indicator — color via design token for automatic dark mode --}}
        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5">
            <svg class="h-4 w-4 text-[color:var(--color-wk-text-subtle)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
            </svg>
        </div>
    </div>

    {{-- Error / success / hint text use design tokens for automatic dark mode (error wins, then success, then hint) --}}
    @if($hasError && $errorMessage)
        <p id="{{ $id }}-error" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @elseif($hasSuccess && $successMessage)
        <p id="{{ $id }}-success" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-success-text)]">{{ $successMessage }}</p>
    @elseif($hint)
        <p id="{{ $id }}-hint" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
</div>
