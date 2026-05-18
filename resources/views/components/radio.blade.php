@props([
    'label' => null,
    'hint' => null,
    'error' => null,
    'value' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Auto-generate ID: when multiple radios share a name, we suffix by value to stay unique
    $nameAttr = $attributes->get('name');
    $defaultId = $nameAttr && $value !== null
        ? $nameAttr . '-' . \Illuminate\Support\Str::slug((string) $value)
        : ($nameAttr ?? 'radio-' . \Illuminate\Support\Str::random(6));
    $id = $attributes->get('id', $defaultId);

    // Error detection: explicit prop OR Laravel validation bag (grouped by name)
    $hasError = $error || ($errors ?? null)?->has($nameAttr ?? '');
    $errorMessage = $error ?? ($errors ?? null)?->first($nameAttr ?? '');

    // Visual circle — sibling of the peer input, reacts via peer-checked/focus/disabled
    $boxClasses = WireKit::resolveClasses('radio', 'base', implode(' ', [
        'relative inline-flex items-center justify-center shrink-0',
        'w-5 h-5',
        'rounded-full',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'peer-hover:border-[var(--color-wk-border-hover)]',
        'bg-[var(--color-wk-bg-input)]',
        'peer-checked:border-[var(--color-wk-accent)]',
        'peer-focus-visible:ring-[length:var(--ring-wk-width)]',
        'peer-focus-visible:ring-offset-[length:var(--ring-wk-offset)]',
        'peer-focus-visible:ring-[var(--color-wk-ring)]',
        'peer-focus-visible:ring-offset-[var(--color-wk-ring-offset)]',
        'peer-disabled:opacity-[var(--opacity-wk-disabled)]',
        'peer-disabled:cursor-not-allowed',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'cursor-pointer',
    ]), $scope);

    if ($hasError) {
        $boxClasses .= ' border-[var(--color-wk-border-error)]';
    }
@endphp

<div class="space-y-1.5">
    <label for="{{ $id }}" class="inline-flex items-start gap-2 cursor-pointer relative">
        {{-- Native radio input — visually hidden but accessible + Livewire wire:model compatible --}}
        <input
            type="radio"
            id="{{ $id }}"
            @if($value !== null) value="{{ $value }}" @endif
            class="peer sr-only"
            @if($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            @if($hint && !$hasError) aria-describedby="{{ $id }}-hint" @endif
            {{ $attributes->except(['id']) }}
        />

        {{-- Visual circle — sibling of .peer, consumes peer-checked border --}}
        <span class="{{ $boxClasses }}" aria-hidden="true"></span>

        {{-- Inner accent dot overlay — must be a sibling of .peer (not nested) for peer-checked to work --}}
        <span
            class="hidden peer-checked:block pointer-events-none absolute left-1.5 top-1.5 w-2 h-2 rounded-full bg-[var(--color-wk-accent)]"
            aria-hidden="true"
        ></span>

        @if($label)
            <span class="text-[length:var(--text-wk-md)] text-[color:var(--color-wk-text)] select-none leading-tight pt-0.5">{{ $label }}</span>
        @endif
    </label>

    {{-- Error message or hint text --}}
    @if($hasError && $errorMessage)
        <p id="{{ $id }}-error" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @elseif($hint)
        <p id="{{ $id }}-hint" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
</div>
