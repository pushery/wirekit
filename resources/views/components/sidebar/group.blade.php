@props([
    'label' => null,
    // When set, the group heading becomes a disclosure button that folds its items.
    // `open` is the initial state (default open — a section keeps showing its children
    // until the user folds it), `persist` is an optional localStorage key so the fold
    // state survives a reload (same semantics as the sidebar's own `persist`).
    'collapsible' => false,
    'open' => true,
    'persist' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\Support\PersistedToggle;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — normalize
    // against each prop's own default so a cast never flips a feature that was on.
    $collapsible = BooleanProp::from($collapsible, false);
    $open = BooleanProp::from($open, true);

    // A group clusters related items under an optional label. The label acts
    // as a section heading (via aria-label on a role="group" container) so
    // screen readers announce "group, <label>" when the user enters.
    $groupClasses = WireKit::resolveClasses('sidebar.group', 'base', 'flex flex-col gap-[2px]', $scope);

    // Label styling — small uppercase label, muted color.
    $labelClasses = WireKit::resolveClasses('sidebar.group', 'label', implode(' ', [
        'px-[var(--padding-wk-x-sm)] pt-[var(--padding-wk-y-sm)] pb-[2px]',
        'text-[length:var(--text-wk-xs)]',
        'font-[number:var(--font-wk-heading-weight)]',
        'uppercase tracking-wider',
        'text-[color:var(--color-wk-text-subtle)]',
    ]), $scope);

    // Collapsible trigger — the same heading look, but a full-width button with a
    // trailing chevron. Only rendered when `collapsible` is set.
    $triggerClasses = WireKit::resolveClasses('sidebar.group', 'trigger', implode(' ', [
        'flex items-center justify-between w-full gap-[var(--padding-wk-x-sm)]',
        'px-[var(--padding-wk-x-sm)] pt-[var(--padding-wk-y-sm)] pb-[2px]',
        'text-[length:var(--text-wk-xs)]',
        'font-[number:var(--font-wk-heading-weight)]',
        'uppercase tracking-wider',
        'text-[color:var(--color-wk-text-subtle)]',
        'hover:text-[color:var(--color-wk-text-muted)]',
        'focus-visible:outline-none',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'rounded-[var(--radius-wk-md)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'cursor-pointer',
    ]), $scope);
@endphp

@if($collapsible)
    {{-- Collapsible section: the heading is a disclosure button, the children fold via
         x-collapse, and the open state optionally persists to localStorage. The outer
         container keeps role="group" + aria-label so AT still announces the labeled
         group; the button carries the expand/collapse role. --}}
    <div
        role="group"
        @if($label) aria-label="{{ $label }}" @endif
        x-data="{{ PersistedToggle::data('open', $open, $persist) }}"
        {{ $attributes->class([$groupClasses]) }}
    >
        <button
            type="button"
            x-on:click="toggle()"
            :aria-expanded="open ? 'true' : 'false'"
            {{-- No visible label to name the button? fall back to a generic name so the
                 disclosure control is never nameless (WCAG 4.1.2). --}}
            @unless($label) aria-label="{{ __('Section') }}" @endunless
            {{-- In the collapsed icon rail the button is `hidden` (not sr-only): the group
                 has no icon to show at rail width and its children are hidden too, so a
                 focusable-but-invisible sr-only control would be a keyboard focus trap with
                 no visible focus indicator (WCAG 2.4.7). --}}
            class="{{ $triggerClasses }} group-data-[collapsed]/wk-sidebar:hidden"
        >
            <span class="truncate">{{ $label }}</span>
            {{-- Chevron rotates when open; hidden in the collapsed icon rail (no room). --}}
            <svg
                class="w-3.5 h-3.5 shrink-0 transition-transform duration-[var(--transition-wk-duration)] group-data-[collapsed]/wk-sidebar:hidden"
                :class="open ? 'rotate-90' : ''"
                fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
        </button>
        {{-- Children — shown/hidden with Alpine when expanded; FORCE-SHOWN as a flat
             icon list in the collapsed rail (the item icons stay reachable), matching
             the static sidebar.group + sidebar.collapsible. The `typeof collapsed`
             guard avoids a ReferenceError when the group sits in a non-collapsible
             sidebar (no `collapsed` in Alpine scope). --}}
        <div x-show="open || (typeof collapsed !== 'undefined' && collapsed)" x-collapse x-cloak class="flex flex-col gap-[2px]">
            {{ $slot }}
        </div>
    </div>
@else
    <div role="group" @if($label) aria-label="{{ $label }}" @endif {{ $attributes->class([$groupClasses]) }}>
        @if($label)
            {{-- Visible label; also the accessible name via aria-label above.
                 We render it visually because sighted users benefit from the grouping too. --}}
            <div class="{{ $labelClasses }} group-data-[collapsed]/wk-sidebar:sr-only">{{ $label }}</div>
        @endif
        {{ $slot }}
    </div>
@endif
