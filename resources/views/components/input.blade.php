@props([
    'label' => null,
    'hint' => null,
    'error' => null,
    'size' => config('wirekit.components.input.size', 'md'),
    'type' => 'text',
    'prefix' => null,
    'suffix' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Auto-generate ID from name attribute, or generate random if neither provided
    $id = $attributes->get('id', $attributes->get('name', 'input-' . \Illuminate\Support\Str::random(6)));
    $name = $attributes->get('name', $id);

    // Error detection: explicit prop OR Laravel validation bag
    $hasError = $error || ($errors ?? null)?->has($name);
    $errorMessage = $error ?? ($errors ?? null)?->first($name);

    // Base classes: all values reference design tokens — no hardcoded colors or sizes
    //
    // Note on :user-invalid styling:
    // The [&:user-invalid]:* utilities below give every input automatic visual
    // feedback for native HTML5 constraint violations (type, pattern, min, max,
    // required, minlength, maxlength, step). :user-invalid — unlike :invalid —
    // only activates AFTER the user has interacted with the field (touched it
    // and blurred, or tried to submit the form), which avoids the UX footgun
    // of showing red borders on every empty required field at page load.
    // This runs independently of the $error prop: $error handles server-side
    // Laravel validation errors, :user-invalid handles client-side HTML5
    // constraint violations. Both produce the same red border + red focus ring.
    $inputClasses = WireKit::resolveClasses('input', 'base', implode(' ', [
        'block w-full',
        'font-[family-name:var(--font-wk-sans)]',
        'tracking-[var(--font-wk-letter-spacing)]',
        'bg-[var(--color-wk-bg-input)]',
        'text-[var(--color-wk-text)]',
        'placeholder:text-[var(--color-wk-text-placeholder)]',
        'border-[length:var(--border-wk-width)]',
        'shadow-[var(--shadow-wk-sm)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'ease-[var(--transition-wk-easing)]',
        'hover:border-[var(--color-wk-border-hover)]',
        'focus:outline-none',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-offset-[length:var(--ring-wk-offset)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'focus-visible:ring-offset-[var(--color-wk-ring-offset)]',
        '[&:user-invalid]:border-[var(--color-wk-border-error)]',
        '[&:user-invalid:focus-visible]:ring-[var(--color-wk-danger)]',
        'disabled:opacity-[var(--opacity-wk-disabled)]',
        'disabled:cursor-not-allowed',
    ]), $scope);

    // Border color switches between normal and error state — both via tokens
    $stateClasses = $hasError
        ? 'border-[var(--color-wk-border-error)] focus-visible:ring-[var(--color-wk-danger)]'
        : 'border-[var(--color-wk-border)]';

    // Size classes: height, padding, font size, radius — all from sizing tokens
    $sizeClasses = match ($size) {
        'sm' => implode(' ', [
            'h-[var(--size-wk-sm)]',
            'px-[var(--padding-wk-x-sm)]',
            'text-[length:var(--text-wk-sm)]',
            'rounded-[var(--radius-wk-sm)]',
        ]),
        'md' => implode(' ', [
            'h-[var(--size-wk-md)]',
            'px-[var(--padding-wk-x-md)]',
            'text-[length:var(--text-wk-md)]',
            'rounded-[var(--radius-wk-md)]',
        ]),
        'lg' => implode(' ', [
            'h-[var(--size-wk-lg)]',
            'px-[var(--padding-wk-x-lg)]',
            'text-[length:var(--text-wk-lg)]',
            'rounded-[var(--radius-wk-md)]',
        ]),
        default => $size,
    };
@endphp

<div class="space-y-1.5">
    @if($label)
        <x-wirekit::label :for="$id">{{ $label }}</x-wirekit::label>
    @endif

    @if($prefix || $suffix)
        {{-- Wrapper: flex row places prefix/suffix as inline siblings so the input
             padding adjusts to the actual text width instead of a hardcoded value. --}}
        <div @class([
            'flex items-center',
            'bg-[var(--color-wk-bg-input)]',
            'border-[length:var(--border-wk-width)]',
            'shadow-[var(--shadow-wk-sm)]',
            'overflow-hidden',
            'transition-colors',
            'duration-[var(--transition-wk-duration)]',
            'ease-[var(--transition-wk-easing)]',
            'has-[:focus-visible]:ring-[length:var(--ring-wk-width)]',
            'has-[:focus-visible]:ring-offset-[length:var(--ring-wk-offset)]',
            'has-[:focus-visible]:ring-[var(--color-wk-ring)]',
            'has-[:focus-visible]:ring-offset-[var(--color-wk-ring-offset)]',
            // Mirror the inner input's :user-invalid state onto the wrapper
            // so the border and focus ring on the wrapper turn red too. Uses
            // :has() so we don't need any JS sync between input and wrapper.
            'has-[:user-invalid]:border-[var(--color-wk-border-error)]',
            'has-[:user-invalid:focus-visible]:ring-[var(--color-wk-danger)]',
            'hover:border-[var(--color-wk-border-hover)]',
            $hasError ? 'border-[var(--color-wk-border-error)]' : 'border-[var(--color-wk-border)]',
            match ($size) {
                'sm' => 'rounded-[var(--radius-wk-sm)] h-[var(--size-wk-sm)]',
                'lg' => 'rounded-[var(--radius-wk-md)] h-[var(--size-wk-lg)]',
                default => 'rounded-[var(--radius-wk-md)] h-[var(--size-wk-md)]',
            },
        ])>
            @if($prefix)
                <span class="shrink-0 select-none pl-[var(--padding-wk-x-md)] text-[var(--color-wk-text-subtle)] text-[length:var(--text-wk-md)] font-[family-name:var(--font-wk-sans)]">{{ $prefix }}</span>
            @endif

            <input
                id="{{ $id }}"
                name="{{ $name }}"
                type="{{ $type }}"
                @if($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
                @if($hint && !$hasError) aria-describedby="{{ $id }}-hint" @endif
                {{ $attributes->class([
                    'block w-full h-full bg-transparent border-none shadow-none',
                    'font-[family-name:var(--font-wk-sans)]',
                    'text-[var(--color-wk-text)]',
                    'placeholder:text-[var(--color-wk-text-placeholder)]',
                    'focus:outline-none focus:ring-0',
                    'disabled:opacity-[var(--opacity-wk-disabled)] disabled:cursor-not-allowed',
                    match ($size) {
                        'sm' => 'px-[var(--padding-wk-x-sm)] text-[length:var(--text-wk-sm)]',
                        'lg' => 'px-[var(--padding-wk-x-lg)] text-[length:var(--text-wk-lg)]',
                        default => 'px-[var(--padding-wk-x-md)] text-[length:var(--text-wk-md)]',
                    },
                    'pl-1' => (bool) $prefix,
                    'pr-1' => (bool) $suffix,
                ]) }}
            />

            @if($suffix)
                <span class="shrink-0 select-none pr-[var(--padding-wk-x-md)] text-[var(--color-wk-text-subtle)] text-[length:var(--text-wk-md)] font-[family-name:var(--font-wk-sans)]">{{ $suffix }}</span>
            @endif
        </div>
    @else
        {{-- No prefix/suffix: render plain input with full styling --}}
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="{{ $type }}"
            @if($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            @if($hint && !$hasError) aria-describedby="{{ $id }}-hint" @endif
            {{ $attributes->class([$inputClasses, $stateClasses, $sizeClasses]) }}
        />
    @endif

    {{-- Error message and hint text use design tokens for automatic dark mode --}}
    @if($hasError && $errorMessage)
        <p id="{{ $id }}-error" class="text-[length:var(--text-wk-sm)] text-[var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @elseif($hint)
        <p id="{{ $id }}-hint" class="text-[length:var(--text-wk-sm)] text-[var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
</div>
