@props([
    'label' => null,
    'hint' => null,
    'error' => null,
    'indeterminate' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Resolve a stable, unique ID. Array-style names (e.g. "tags[]") cannot
    // be used verbatim as DOM ids — multiple checkboxes would collide and
    // <label for="..."> would always target the FIRST element with that id,
    // making every click activate the wrong checkbox. In that case (or when
    // no name is provided at all), fall back to a random suffix so each
    // rendered checkbox is independently addressable.
    $rawName = $attributes->get('name');
    if ($attributes->has('id')) {
        $id = $attributes->get('id');
    } elseif ($rawName !== null && ! str_contains($rawName, '[')) {
        $id = $rawName;
    } else {
        $idBase = $rawName !== null ? rtrim(preg_replace('/\[.*\]$/', '', $rawName), '-_') : 'checkbox';
        $id = ($idBase !== '' ? $idBase : 'checkbox') . '-' . \Illuminate\Support\Str::random(6);
    }
    $name = $rawName ?? $id;

    // Error detection: explicit prop OR Laravel validation bag
    $hasError = $error || ($errors ?? null)?->has($name);
    $errorMessage = $error ?? ($errors ?? null)?->first($name);

    // Visual box styling. The <input> uses .peer + .sr-only, and this box listens
    // to peer-checked / peer-focus-visible / peer-disabled via sibling selectors.
    $boxClasses = WireKit::resolveClasses('checkbox', 'base', implode(' ', [
        'relative inline-flex items-center justify-center shrink-0',
        'w-5 h-5',
        'rounded-[var(--radius-wk-sm)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'peer-hover:border-[var(--color-wk-border-hover)]',
        'bg-[var(--color-wk-bg-input)]',
        'peer-checked:bg-[var(--color-wk-accent)]',
        'peer-checked:border-[var(--color-wk-accent)]',
        'peer-indeterminate:bg-[var(--color-wk-accent)]',
        'peer-indeterminate:border-[var(--color-wk-accent)]',
        'peer-focus-visible:ring-[length:var(--ring-wk-width)]',
        'peer-focus-visible:ring-offset-[length:var(--ring-wk-offset)]',
        'peer-focus-visible:ring-[var(--color-wk-ring)]',
        'peer-focus-visible:ring-offset-[var(--color-wk-ring-offset)]',
        'peer-disabled:opacity-[var(--opacity-wk-disabled)]',
        'peer-disabled:cursor-not-allowed',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'cursor-pointer',
        'text-[var(--color-wk-accent-fg)]',
    ]), $scope);

    if ($hasError) {
        $boxClasses .= ' border-[var(--color-wk-border-error)]';
    }
@endphp

<div class="space-y-1.5">
    <label for="{{ $id }}" class="inline-flex items-start gap-2 cursor-pointer relative">
        {{-- Native checkbox: visually hidden but fully accessible + Livewire-compatible.
             Siblings below consume its :checked / :indeterminate / :focus-visible / :disabled state via peer-*. --}}
        <input
            type="checkbox"
            id="{{ $id }}"
            name="{{ $name }}"
            class="peer sr-only"
            @if($indeterminate) x-init="$el.indeterminate = true" @endif
            @if($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            @if($hint && !$hasError) aria-describedby="{{ $id }}-hint" @endif
            {{ $attributes->except(['id', 'name']) }}
        />

        {{-- Visual box — sibling of input, so peer-* variants work --}}
        <span class="{{ $boxClasses }}" aria-hidden="true"></span>

        {{-- Checkmark overlay — absolutely positioned ON TOP of the box.
             Must also be a sibling of .peer (not nested in the box) so peer-checked matches. --}}
        <svg
            class="hidden peer-checked:block pointer-events-none absolute left-0 top-0 w-5 h-5 p-0.5 text-[var(--color-wk-accent-fg)]"
            fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"
        >
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
        </svg>
        {{-- Indeterminate dash overlay — same positioning, visible only when input.indeterminate is true --}}
        <svg
            class="hidden peer-indeterminate:block pointer-events-none absolute left-0 top-0 w-5 h-5 p-0.5 text-[var(--color-wk-accent-fg)]"
            fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"
        >
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" />
        </svg>

        @if($slot->isNotEmpty())
            {{-- Slot-based label: supports rich HTML (links, formatting) for use cases like GDPR consent --}}
            <span class="text-[length:var(--text-wk-md)] text-[var(--color-wk-text)] select-none leading-tight pt-0.5">{{ $slot }}</span>
        @elseif($label)
            <span class="text-[length:var(--text-wk-md)] text-[var(--color-wk-text)] select-none leading-tight pt-0.5">{{ $label }}</span>
        @endif
    </label>

    {{-- Error message or hint text --}}
    @if($hasError && $errorMessage)
        <p id="{{ $id }}-error" class="text-[length:var(--text-wk-sm)] text-[var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @elseif($hint)
        <p id="{{ $id }}-hint" class="text-[length:var(--text-wk-sm)] text-[var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
</div>
