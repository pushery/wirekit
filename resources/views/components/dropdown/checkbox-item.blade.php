@props([
    'checked' => false,
    'disabled' => false,
    'shortcut' => null, // keyboard-shortcut hint at the inline-end
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $checked = BooleanProp::from($checked, false);
    $disabled = BooleanProp::from($disabled, false);

    // A self-toggling menu item with a checkmark (WAI-ARIA menuitemcheckbox). Alpine
    // owns the checked state (initialized from the `checked` prop) so it works in a
    // pure-Alpine context with no backend; add your own @click to also sync Livewire.
    $classes = WireKit::resolveClasses('dropdown.checkbox-item', 'base', implode(' ', [
        'flex items-center gap-x-[var(--gap-wk-sm)] w-full',
        'px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-sm)]',
        'text-[length:var(--text-wk-md)] font-[family-name:var(--font-wk-sans)]',
        'text-[color:var(--color-wk-text)]',
        'transition-colors duration-[var(--transition-wk-duration)] ease-[var(--transition-wk-easing)]',
        'focus:outline-none focus:bg-[var(--color-wk-bg-subtle)] hover:bg-[var(--color-wk-bg-subtle)]',
        'cursor-pointer',
    ]), $scope);

    $disabledClasses = $disabled ? 'opacity-[var(--opacity-wk-disabled)] pointer-events-none' : '';
    $checkedBool = (bool) $checked;
@endphp

<button
    type="button"
    role="menuitemcheckbox"
    tabindex="-1"
    x-data="{ on: @js($checkedBool) }"
    x-on:click="on = !on"
    :aria-checked="on ? 'true' : 'false'"
    @if($disabled) aria-disabled="true" @endif
    {{ $attributes->class([$classes, $disabledClasses]) }}
>
    {{-- Checkbox box — a bordered square that is ALWAYS visible (mirrors the
         radio-item's always-visible circle), filled with the accent + a
         checkmark when on. Previously only the checkmark rendered, so an
         UNCHECKED item showed no box at all — the control was invisible. --}}
    <span class="shrink-0 w-4 h-4 flex items-center justify-center" aria-hidden="true">
        <span
            class="w-3.5 h-3.5 rounded-[var(--radius-wk-sm)] border-[length:var(--border-wk-width)] flex items-center justify-center transition-colors duration-[var(--transition-wk-duration)]"
            :class="on ? 'border-[var(--color-wk-accent)] bg-[var(--color-wk-accent)] text-[color:var(--color-wk-accent-fg)]' : 'border-[var(--color-wk-border)]'"
        >
            <svg x-show="on" x-cloak class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
        </span>
    </span>

    {{ $slot }}

    @if($shortcut)
        <span class="ms-auto ps-[var(--padding-wk-x-md)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)] tabular-nums" aria-hidden="true">{{ $shortcut }}</span>
    @endif
</button>
