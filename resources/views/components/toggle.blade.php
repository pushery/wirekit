@props([
    // A11y: render the error message in a polite live region by default so a
    // server-side validation error that appears after submit (when focus is
    // elsewhere) is announced. Mirrors the input component. Set false to opt out.
    'announceError' => null,
    'label' => null,
    'hint' => null,
    'error' => null,
    'size' => config('wirekit.components.toggle.size', 'md'),
    'scope' => null,
])

@aware(['announceErrors' => null])

@php
    // `@aware` reads a value from the parent component, but — unlike `@props` —
    // it does NOT remove that key from the attribute bag. So when the key is also
    // written as an attribute on the tag, it survives into `{{ $attributes }}` and
    // renders as a stray HTML attribute on the element. Blade accepts both
    // spellings on a tag, so both are dropped here.
    $attributes = $attributes->except(['announceErrors', 'announce-errors']);
@endphp


@php
    // announce-error precedence: explicit prop > form container (@aware announceErrors) > global config.
    $announceError ??= $announceErrors ?? config('wirekit.a11y.announce_error', true);

    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props.
    WireKit::warnUnknownProps('toggle', $attributes->getAttributes());

    // Auto-generate ID from name or fall back to a random identifier
    $id = $attributes->get('id', $attributes->get('name', 'toggle-' . \Illuminate\Support\Str::random(6)));
    $name = $attributes->get('name', $id);

    // Accessible name fallback: if the caller provided neither a visible `label`
    // prop nor an `aria-label` attribute, generate a humanized label from the
    // `name` attribute so axe-core and screen readers still announce the switch
    // correctly. Visible labels still win when present (they'll use `<label
    // for="...">` instead of aria-label).
    $hasAccessibleName = $label !== null || $attributes->has('aria-label') || $attributes->has('aria-labelledby');
    $fallbackAriaLabel = $hasAccessibleName ? null : ucfirst(str_replace(['-', '_'], ' ', (string) $name));

    // Error detection: explicit prop OR Laravel validation bag
    $hasError = $error || ($errors ?? null)?->has($name);
    $errorMessage = $error ?? ($errors ?? null)?->first($name);

    // Size scale: track width/height + knob offset distance
    // Knob diameter = track height minus 4px of padding
    $sizing = match ($size) {
        'sm' => ['track' => 'w-8 h-4', 'knob' => 'w-3 h-3', 'translate' => 'peer-checked:translate-x-4'],
        'lg' => ['track' => 'w-12 h-6', 'knob' => 'w-5 h-5', 'translate' => 'peer-checked:translate-x-6'],
        default => ['track' => 'w-10 h-5', 'knob' => 'w-4 h-4', 'translate' => 'peer-checked:translate-x-5'],
    };

    // Wrapper styles: fixed-size positioning context for the (absolutely placed) track + knob
    $wrapperClasses = implode(' ', [
        'relative inline-flex shrink-0 items-center',
        'cursor-pointer',
        $sizing['track'],
    ]);

    // Track: OFF state uses border color (neutral-300) for visible contrast against white backgrounds.
    // Old bg-muted (neutral-100) was ~1.04:1 contrast — nearly invisible. WCAG 1.4.11 requires ≥3:1.
    // MUST be a direct sibling of .peer for peer-checked:* to resolve.
    $trackClasses = WireKit::resolveClasses('toggle', 'track', implode(' ', [
        'absolute inset-0',
        'rounded-full',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border-strong)]',
        'bg-[var(--color-wk-border)]',
        'peer-checked:bg-[var(--color-wk-accent)]',
        'peer-checked:border-[var(--color-wk-accent)]',
        'peer-focus-visible:ring-[length:var(--ring-wk-width)]',
        'peer-focus-visible:ring-offset-[length:var(--ring-wk-offset)]',
        'peer-focus-visible:ring-[var(--color-wk-ring)]',
        'peer-focus-visible:ring-offset-[var(--color-wk-ring-offset)]',
        'peer-disabled:opacity-[var(--opacity-wk-disabled)]',
        'peer-disabled:cursor-not-allowed',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'pointer-events-none',
    ]), $scope);

    // Knob styles: a circle that slides from left to right when checked.
    // MUST be a direct sibling of .peer for peer-checked:* to resolve.
    $knobClasses = implode(' ', [
        'absolute left-0.5 top-1/2 -translate-y-1/2',
        'rounded-full',
        'bg-[var(--color-wk-bg-elevated)]',
        'shadow-[var(--shadow-wk-sm)]',
        'transition-transform',
        'duration-[var(--transition-wk-duration)]',
        'ease-[var(--transition-wk-easing)]',
        'pointer-events-none',
        $sizing['knob'],
        $sizing['translate'],
    ]);
@endphp

<div class="space-y-1.5">
    <label for="{{ $id }}" class="inline-flex items-center gap-3 cursor-pointer">
        {{-- Switch visual: wrapper contains input (.peer), track, and knob as siblings --}}
        {{-- so peer-checked:* selectors resolve correctly (peer-checked targets siblings only). --}}
        <span class="{{ $wrapperClasses }}">
            {{-- Native checkbox: visually hidden but accessible (screen readers + Livewire wire:model) --}}
            {{-- role="switch" tells AT this is a toggle, not a regular checkbox --}}
            <input
                type="checkbox"
                id="{{ $id }}"
                name="{{ $name }}"
                role="switch"
                class="peer sr-only"
                @if($fallbackAriaLabel) aria-label="{{ $fallbackAriaLabel }}" @endif
                @if($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
                @if($hint && !$hasError) aria-describedby="{{ $id }}-hint" @endif
                {{ $attributes->except(['id', 'name']) }}
            />

            {{-- Track: sibling of .peer, background color flips via peer-checked --}}
            <span class="{{ $trackClasses }}" aria-hidden="true"></span>

            {{-- Knob: sibling of .peer, slides via peer-checked:translate-x-* --}}
            <span class="{{ $knobClasses }}" aria-hidden="true"></span>
        </span>

        @if($label)
            <span class="text-[length:var(--text-wk-md)] text-[color:var(--color-wk-text)] select-none">{{ $label }}</span>
        @endif
    </label>

    {{-- Error message or hint text --}}
    @if($hasError && $errorMessage)
        <p id="{{ $id }}-error" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @elseif($hint)
        <p id="{{ $id }}-hint" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
</div>
