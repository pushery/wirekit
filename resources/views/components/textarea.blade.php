@props([
    'label' => null,
    'hint' => null,
    'error' => null,
    'size' => config('wirekit.components.textarea.size', 'md'),
    'rows' => config('wirekit.components.textarea.rows', 3),
    'resize' => true,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Auto-generate ID from name attribute, or generate random if neither provided
    $id = $attributes->get('id', $attributes->get('name', 'textarea-' . \Illuminate\Support\Str::random(6)));
    $name = $attributes->get('name', $id);

    // Error detection: explicit prop OR Laravel validation bag
    $hasError = $error || ($errors ?? null)?->has($name);
    $errorMessage = $error ?? ($errors ?? null)?->first($name);

    // Base classes: all values reference design tokens — no hardcoded colors or sizes
    //
    // :user-invalid styling mirrors the input component — see input.blade.php
    // for the rationale. Textarea inherits HTML5 constraints via required,
    // minlength, and maxlength, so the same visual feedback applies.
    $textareaClasses = WireKit::resolveClasses('textarea', 'base', implode(' ', [
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

    // Size classes: padding (horizontal + vertical), font size, radius — all from sizing tokens
    // Textarea uses padding-y instead of fixed height (unlike input/select)
    $sizeClasses = match ($size) {
        'sm' => implode(' ', [
            'px-[var(--padding-wk-x-sm)]',
            'py-[var(--padding-wk-y-sm)]',
            'text-[length:var(--text-wk-sm)]',
            'rounded-[var(--radius-wk-sm)]',
        ]),
        'md' => implode(' ', [
            'px-[var(--padding-wk-x-md)]',
            'py-[var(--padding-wk-y-md)]',
            'text-[length:var(--text-wk-md)]',
            'rounded-[var(--radius-wk-md)]',
        ]),
        'lg' => implode(' ', [
            'px-[var(--padding-wk-x-lg)]',
            'py-[var(--padding-wk-y-lg)]',
            'text-[length:var(--text-wk-lg)]',
            'rounded-[var(--radius-wk-md)]',
        ]),
        default => WireKit::validateProp('textarea', 'size', $size, ['sm', 'md', 'lg']),
    };
@endphp

<div class="space-y-1.5">
    @if($label)
        <x-wirekit::label :for="$id">{{ $label }}</x-wirekit::label>
    @endif

    <textarea
        id="{{ $id }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        @if($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        @if($hint && !$hasError) aria-describedby="{{ $id }}-hint" @endif
        {{ $attributes->class([$textareaClasses, $stateClasses, $sizeClasses, $resize ? 'resize-y' : 'resize-none']) }}
    >{{ $slot }}</textarea>

    {{-- Error message and hint text use design tokens for automatic dark mode --}}
    @if($hasError && $errorMessage)
        <p id="{{ $id }}-error" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @elseif($hint)
        <p id="{{ $id }}-hint" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
</div>
