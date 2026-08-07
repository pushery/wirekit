{{-- optimistic-ui: supported
     Its checked state is a server value even though the menu around it is not, so
     `undo` is right: a single boolean whose previous value belongs to the server,
     and putting it back costs the reader nothing — the same call toggle-button made.

     THE OBSTACLE WAS REAL, AND IT WAS SETTLED BY MEASUREMENT.

     This component IS the button, so the announcer has nowhere to go inside it —
     a live region there becomes part of the accessible name. The established
     answer is a `display: contents` wrapper, which five components already use;
     none of them, however, sits inside a `role="menu"`. Here the wrapper lands
     between the panel's `role="menu"` and this `role="menuitemcheckbox"`, and a
     menu owns its menuitems DIRECTLY. Break that and the item stops being part
     of the menu for a screen reader, silently — the DOM looks identical.

     The specs say a role-less `display: contents` element leaves the
     accessibility tree along with the box tree, which would keep the ownership.
     "Say" is not a basis for shipping an ARIA relationship, so it was read out of
     real trees, in all three baseline engines, via `ariaSnapshot()`:

         chromium  identical with and without the wrapper
         firefox   identical
         webkit    identical

         - menu "Options":
           - menuitemcheckbox "Wrap lines" [checked]
           - text: announcer
           - menuitemcheckbox "Show gutter"

     Both items stay direct children of the menu in every engine. The wrapper is
     invisible to assistive technology, and this ships on that measurement rather
     than on the spec's promise. --}}
@props([
    // The Livewire method to call when the item is toggled, showing the new
    // checked state before the server agrees. A refusal puts the old state back.
    // Adds a `display: contents` wrapper — see the note above for why that is
    // safe inside a menu, and how it was checked.
    // Extra arguments appended to the optimistic action call, after the new value.
    // A list of identical controls — one per row — needs to tell the server WHICH row,
    // and the optimistic layer has always been able to carry that: it spreads `args`
    // into the call. No component exposed it, so the capability existed and was
    // unreachable, and the only way to build the commonest optimistic surface there is
    // was to hand-mount the factory and give up the component.
    'optimisticArgs' => [],
    'optimistic' => null,
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

    // The layer holds the value: without `optimistic` this item keeps its own
    // `x-data="{ on }"` and behaves byte-identically to before. With it, the
    // layer owns `value` and the local state would be a second writer of one
    // value, so the two are mutually exclusive by construction rather than by
    // documentation.
    $optimisticConfig = $optimistic === null ? null : \Pushery\WireKit\Support\AlpinePayload::from([
        'value' => (bool) filter_var($checked, FILTER_VALIDATE_BOOLEAN),
        'action' => $optimistic,
        'args' => array_values((array) $optimisticArgs),
        'debug' => (bool) config('app.debug'),
        // Twice-toggled would otherwise resolve by whichever answer arrives last
        // — network timing, which is both wrong and untestable.
        'mode' => 'reject',
        'messages' => [
            'pending' => __('Saving'),
            'reverted' => __('Could not save. Change undone.'),
        ],
    ]);

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

    // ONE name for the visual bindings, so the box and the checkmark follow the
    // value whichever scope owns it — the layer's `value` when optimistic, the
    // local `on` otherwise. Without this the bindings would keep reading a
    // property that does not exist in the optimistic render, and Alpine resolves
    // an unknown name to undefined rather than erroring: the checkmark would
    // simply never appear, on a control whose entire job is showing that state.
    $stateExpr = $optimisticConfig === null ? 'on' : 'value';
@endphp

@if($optimisticConfig)
{{-- `display: contents` so the announcer gets a sibling without the item leaving
     the menu's layout — and, measured in all three baseline engines, without it
     leaving the menu's ACCESSIBILITY tree either. See the note at the top. --}}
<div x-data="wirekitOptimistic({{ $optimisticConfig }})" style="display: contents">
@endif
<button
    type="button"
    role="menuitemcheckbox"
    tabindex="-1"
    @if($optimisticConfig)
        {{-- `value` and `toggle()` come from the layer wrapping this element; the
             local x-data is deliberately absent so there is one writer, not two. --}}
        x-on:click="toggle()"
        :aria-checked="value ? 'true' : 'false'"
        x-bind:aria-busy="isPending"
    @else
        x-data="{ on: {{ \Pushery\WireKit\Support\AlpinePayload::from($checkedBool) }} }"
        x-on:click="on = !on"
        :aria-checked="on ? 'true' : 'false'"
    @endif
    aria-checked="{{ $checkedBool ? 'true' : 'false' }}"
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
            :class="{{ $stateExpr }} ? 'border-[var(--color-wk-accent)] bg-[var(--color-wk-accent)] text-[color:var(--color-wk-accent-fg)]' : 'border-[var(--color-wk-border)]'"
        >
            <svg x-show="{{ $stateExpr }}" x-cloak class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
        </span>
    </span>

    {{ $slot }}

    @if($shortcut)
        <span class="ms-auto ps-[var(--padding-wk-x-md)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)] tabular-nums" aria-hidden="true">{{ $shortcut }}</span>
    @endif
</button>
@if($optimisticConfig)
    {{-- Rendered unconditionally and starting empty: a live region that arrives
         together with its text is a new node, and nothing is announced at all. --}}
    <div class="sr-only" data-wk-optimistic-announcer aria-live="assertive" aria-atomic="true" x-text="announcement"></div>
</div>
@endif
