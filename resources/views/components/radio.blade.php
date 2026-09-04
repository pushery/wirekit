{{-- optimistic-ui: candidate
     This was classified as supported once, and a rendered browser test refuted it
     the same day — which is what that test is for.

     A radio is a GROUP control and this component is one element of it. Each
     radio would carry its own optimistic layer holding its own `value`
     attribute — not the group's selection — so there is nothing for a layer to
     roll back to: by the time any handler runs the browser has already
     deselected the sibling, and no per-element undo can put that back.

     It needs a group-level surface to bind to, and WireKit has none: radios here
     are standalone elements sharing a `name`. That is a real design step, not
     wiring, so it stays a candidate rather than shipping something that looks
     enabled and cannot roll back. --}}
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
    use Pushery\WireKit\Support\BooleanProp;

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

    // HTML reads a boolean attribute by PRESENCE, so `disabled="false"` disables the
    // control — the opposite of what the call site says, with no error either way.
    // Strip such flags when their value reads as false, before the bag reaches the control.
    $attributes = BooleanProp::stripFalseHtmlFlags($attributes);


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

    // Auto-generate ID: a radio set shares one `name` by definition, so the default
    // folds the value in — that part was always right and is unchanged.
    //
    // What it did NOT do is go through the page-unique registry the other fourteen
    // name-derived controls use, so two IDENTICAL sets on one page (a filter bar and
    // the same filter inside a modal) still emitted the same ids. Radio's hint and
    // error ids derive from $id too, so the second set's aria-describedby pointed at
    // the FIRST set's help text — the reader hears the wrong hint, and nothing looks
    // broken. The 2.20.0 changelog already promised "input, textarea, select and the
    // other name-derived controls", so this was a shipped claim the component did
    // not honor.
    //
    // The random fallback moves INTO DomId::unique, which does exactly the same
    // thing for a null preferred id — keeping it here would register a value that is
    // unique by construction and never collides, filling the registry for nothing.
    $nameAttr = $attributes->get('name');
    $defaultId = $nameAttr && $value !== null
        ? $nameAttr . '-' . \Illuminate\Support\Str::slug((string) $value)
        : $nameAttr;
    $id = \Pushery\WireKit\Support\DomId::unique($attributes->get('id') ?? $defaultId, 'radio-');

    // Error detection: explicit prop OR Laravel validation bag (grouped by name)
    $hasError = $error || ($errors ?? null)?->has($nameAttr ?? '');
    $errorMessage = $error ?? ($errors ?? null)?->first($nameAttr ?? '');

    // Visual circle — sibling of the peer input, reacts via peer-checked/focus/disabled
    $boxClasses = WireKit::resolveClasses('radio', 'base', implode(' ', [
        'relative inline-flex items-center justify-center shrink-0',
        // The hit-area reserve, on the BOX rather than the label: the label is the box PLUS
        // its text, so an area centered on it sits over the words instead of over the control.
        // Not in the `card` variant — there the label IS the target, already bordered and
        // full-width, and a 2.75rem area hung off the 20px box inside it reaches past the
        // card's own edge, so a tap in the gap between two stacked cards lands on the upper
        // one. Same split, and the same reasoning, as `checkbox.blade.php`.
        $variantValue === 'card' ? '' : 'wk-touch-target',
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
