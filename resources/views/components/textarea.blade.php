@props([
    // A11y: render the error message in a polite live region by default so a
    // server-side validation error that appears after submit (when focus is
    // elsewhere) is announced. Mirrors the input component. Set false to opt out.
    'announceError' => config('wirekit.a11y.announce_error', true),
    'label' => null,
    'hideLabel' => false, // render the label sr-only (kept for assistive tech) — for compact toolbar / header fields
    'hint' => null,
    'error' => null,
    // Success / valid state — string shows a green confirmation below, `true`
    // shows just the green border. `error` always wins when both are set.
    'success' => null,
    'size' => config('wirekit.components.textarea.size', 'md'),
    // Number of rows, OR 'auto' to grow with content (CSS field-sizing: content,
    // baseline-safe). In auto mode `rows` acts as the minimum height.
    'rows' => config('wirekit.components.textarea.rows', 3),
    'resize' => true,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props.
    WireKit::warnUnknownProps('textarea', $attributes->getAttributes());

    // Auto-generate ID from name attribute, or generate random if neither provided
    $id = $attributes->get('id', $attributes->get('name', 'textarea-' . \Illuminate\Support\Str::random(6)));
    $name = $attributes->get('name', $id);

    // Error detection: explicit prop OR Laravel validation bag
    $hasError = $error || ($errors ?? null)?->has($name);
    $errorMessage = $error ?? ($errors ?? null)?->first($name);

    // Success / valid state — only when there is no error (error wins).
    $hasSuccess = ! $hasError && $success !== null && $success !== false;
    $successMessage = is_string($success) ? $success : null;

    // Auto-size: `rows="auto"` grows the textarea with its content via CSS
    // `field-sizing: content` (in the WireKit browser baseline). The numeric
    // `rows` still serves as the minimum height; we never emit `rows="auto"`
    // (invalid HTML) — auto falls back to a 2-row minimum.
    $autosize = $rows === 'auto' || $rows === true;
    $minRows = $autosize ? 2 : (int) $rows;

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

    // Border color switches between error, success, and normal state — all via tokens
    $stateClasses = match (true) {
        (bool) $hasError => 'border-[var(--color-wk-border-error)] focus-visible:ring-[var(--color-wk-danger)]',
        $hasSuccess => 'border-[var(--color-wk-border-success)] focus-visible:ring-[var(--color-wk-success)]',
        default => 'border-[var(--color-wk-border-strong)]',
    };

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
        <x-wirekit::label :for="$id" :class="$hideLabel ? 'sr-only' : ''">{{ $label }}</x-wirekit::label>
    @endif

    <textarea
        id="{{ $id }}"
        name="{{ $name }}"
        rows="{{ $minRows }}"
        @if($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        @if($hasSuccess && $successMessage && !$hasError) aria-describedby="{{ $id }}-success" @endif
        @if($hint && !$hasError && !($hasSuccess && $successMessage)) aria-describedby="{{ $id }}-hint" @endif
        {{-- wk-field: 16px iOS-zoom floor on phones (dist/wirekit.css) --}}
        {{ $attributes->class(['wk-field', $textareaClasses, $stateClasses, $sizeClasses, $resize ? 'resize-y' : 'resize-none', '[field-sizing:content]' => $autosize]) }}
    >{{ $slot }}</textarea>

    {{-- Error / success / hint text use design tokens for automatic dark mode (error wins, then success, then hint) --}}
    @if($hasError && $errorMessage)
        <p id="{{ $id }}-error" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @elseif($hasSuccess && $successMessage)
        <p id="{{ $id }}-success" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-success-text)]">{{ $successMessage }}</p>
    @elseif($hint)
        <p id="{{ $id }}-hint" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
</div>
