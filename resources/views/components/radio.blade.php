@props([
    // A11y: render the error message in a polite live region by default so a
    // server-side validation error that appears after submit (when focus is
    // elsewhere) is announced. Mirrors the input component. Set false to opt out.
    'announceError' => null,
    'label' => null,
    'hint' => null,
    'error' => null,
    'value' => null,
    'size' => config('wirekit.components.radio.size', 'md'),
    // 'default' (inline control + label) or 'card' (the whole bordered card is the
    // clickable target and highlights when selected — the pricing-tier pattern).
    'variant' => config('wirekit.components.radio.variant', 'default'),
    'scope' => null,
])

@aware(['announceErrors' => null])

@php
    // announce-error precedence: explicit prop > form container (@aware announceErrors) > global config.
    $announceError ??= $announceErrors ?? config('wirekit.a11y.announce_error', true);

    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props.
    WireKit::warnUnknownProps('radio', $attributes->getAttributes());

    // Size scale (aligned with toggle/checkbox): the circle + its inner accent dot
    // scale together. The dot lives INSIDE the circle and is flex-centered by the
    // circle (items-center/justify-center), so it only needs a size — no left/top
    // offsets. (Absolute offsets relative to the label mis-centered the dot in the
    // `card` variant, whose label padding insets the circle.)
    $sizing = match ($size) {
        'sm' => ['box' => 'w-4 h-4', 'dot' => 'w-1.5 h-1.5'],
        'lg' => ['box' => 'w-6 h-6', 'dot' => 'w-2.5 h-2.5'],
        default => ['box' => 'w-5 h-5', 'dot' => 'w-2 h-2'],
    };

    $variantValue = match ($variant) {
        'default', 'card' => $variant,
        default => WireKit::validateProp('radio', 'variant', $variant, ['default', 'card']),
    };
    // Card variant: the <label> becomes a bordered card reacting to its inner input
    // via :has() — accent border + tinted surface when selected, focus ring on focus.
    // `group` so the inner dot can toggle via group-has-[:checked] (it's nested in
    // the circle, not a sibling of .peer, so peer-checked can't reach it).
    //
    // align-top on the default (inline-flex) label kills a sub-pixel layout shift on
    // toggle — see the matching note in checkbox.blade.php. The inline-flex label is
    // placed by its baseline; the dot flipping display none↔block re-rounds that
    // baseline on a 2× display and nudges the next row. align-top pins the label by
    // its top edge instead. The card variant is block-level `flex` and is unaffected.
    $labelClasses = $variantValue === 'card'
        ? 'group flex items-start gap-3 cursor-pointer relative w-full rounded-[var(--radius-wk-lg)] px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-md)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] transition-colors duration-[var(--transition-wk-duration)] has-[:checked]:border-[var(--color-wk-accent)] has-[:checked]:bg-[var(--color-wk-bg-subtle)] has-[:focus-visible]:ring-[length:var(--ring-wk-width)] has-[:focus-visible]:ring-[var(--color-wk-ring)]'
        : 'group inline-flex items-start gap-2 cursor-pointer relative align-top';

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
        $sizing['box'],
        'rounded-full',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border-strong)]',
        'peer-hover:border-[var(--color-wk-border-strong-hover)]',
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
    <label for="{{ $id }}" class="{{ $labelClasses }}">
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

        {{-- Visual circle — sibling of .peer, consumes peer-checked border. The
             inner accent dot is nested HERE and flex-centered by the circle's
             items-center/justify-center, so it stays centered in both the default
             and card variants. It toggles via the label's group-has-[:checked]
             (a nested element isn't a sibling of .peer, so peer-checked can't
             reach it; the circle border still uses peer-checked, unchanged). --}}
        <span class="{{ $boxClasses }}" aria-hidden="true">
            <span class="hidden group-has-[:checked]:block pointer-events-none {{ $sizing['dot'] }} rounded-full bg-[var(--color-wk-accent)]"></span>
        </span>

        @if($label)
            <span class="text-[length:var(--text-wk-md)] text-[color:var(--color-wk-text)] select-none leading-tight pt-0.5">{{ $label }}</span>
        @endif
    </label>

    {{-- Error message or hint text --}}
    @if($hasError && $errorMessage)
        <p id="{{ $id }}-error" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @elseif($hint)
        <p id="{{ $id }}-hint" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
</div>
