@props([
    'label' => null,
    'hint' => null,
    'error' => null,
    'indeterminate' => false,
    'size' => config('wirekit.components.checkbox.size', 'md'),
    // 'default' (inline control + label) or 'card' (the whole bordered card is the
    // clickable target and highlights when checked — the selectable-option pattern).
    'variant' => config('wirekit.components.checkbox.variant', 'default'),
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Size scale (aligned with toggle/radio): sets the box w/h. The check +
    // indeterminate overlays are nested inside the box and fill it (w-full h-full),
    // so they need no size of their own and stay centered in every variant.
    // Lenient match-with-default mirrors the sibling toggle component.
    $sizing = match ($size) {
        'sm' => 'w-4 h-4',
        'lg' => 'w-6 h-6',
        default => 'w-5 h-5',
    };

    $variantValue = match ($variant) {
        'default', 'card' => $variant,
        default => WireKit::validateProp('checkbox', 'variant', $variant, ['default', 'card']),
    };
    // Card variant: the <label> becomes a bordered card that reacts to its inner
    // input via :has() (in the WireKit browser baseline) — accent border + tinted
    // surface when checked, focus ring when the input is focus-visible.
    // `group` so the check/indeterminate overlays (nested in the box, not siblings
    // of .peer) can toggle via group-has-[:checked] / group-has-[:indeterminate].
    //
    // align-top on the default (inline-flex) label kills a sub-pixel layout shift on
    // toggle: an inline-flex label is placed in its line box by its baseline, and the
    // box's flex baseline shifts when the checkmark SVG flips display none↔block — on a
    // 2× display that re-rounds the whole label ~0.5px, pulling the next row closer
    // (measured: CheckboxToggleShiftTest). align-top positions the label by its TOP
    // edge instead, independent of the changing baseline, so the row stays put. The
    // card variant is block-level `flex` (no line-box baseline) and is unaffected.
    $labelClasses = $variantValue === 'card'
        ? 'group flex items-start gap-3 cursor-pointer relative w-full rounded-[var(--radius-wk-lg)] px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-md)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] transition-colors duration-[var(--transition-wk-duration)] has-[:checked]:border-[var(--color-wk-accent)] has-[:checked]:bg-[var(--color-wk-bg-subtle)] has-[:focus-visible]:ring-[length:var(--ring-wk-width)] has-[:focus-visible]:ring-[var(--color-wk-ring)]'
        : 'group inline-flex items-start gap-2 cursor-pointer relative align-top';

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
        $sizing,
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
        'text-[color:var(--color-wk-accent-fg)]',
    ]), $scope);

    if ($hasError) {
        $boxClasses .= ' border-[var(--color-wk-border-error)]';
    }
@endphp

<div class="space-y-1.5">
    <label for="{{ $id }}" class="{{ $labelClasses }}">
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

        {{-- Visual box — sibling of .peer (consumes peer-checked bg/border). The
             check + indeterminate overlays are nested HERE and fill the box
             (w-full h-full), flex-centered by the box, so they stay over the box
             in BOTH the default and card variants (card label padding no longer
             offsets them). They toggle via the label's group-has-[:checked] /
             group-has-[:indeterminate] (a nested element isn't a sibling of .peer,
             so peer-checked can't reach it; the box bg/border still use peer-*). --}}
        <span class="{{ $boxClasses }}" aria-hidden="true">
            {{-- Checkmark --}}
            <svg
                class="hidden group-has-[:checked]:block pointer-events-none w-full h-full p-0.5 text-[color:var(--color-wk-accent-fg)]"
                fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
            {{-- Indeterminate dash — visible only when input.indeterminate is true --}}
            <svg
                class="hidden group-has-[:indeterminate]:block pointer-events-none w-full h-full p-0.5 text-[color:var(--color-wk-accent-fg)]"
                fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" />
            </svg>
        </span>

        @if($slot->isNotEmpty())
            {{-- Slot-based label: supports rich HTML (links, formatting) for use cases like GDPR consent --}}
            <span class="text-[length:var(--text-wk-md)] text-[color:var(--color-wk-text)] select-none leading-tight pt-0.5">{{ $slot }}</span>
        @elseif($label)
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
