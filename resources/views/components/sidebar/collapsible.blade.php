{{-- optimistic-ui: n/a — client-only
     Its state is disclosure state. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. --}}
@props([
    'label' => '',
    'icon' => null,
    'open' => false,
    // Optional localStorage key. When set, the open/closed state survives a reload —
    // same semantics as the sidebar component's own `persist`. Null keeps it ephemeral.
    'persist' => null,
    // Trigger styling. 'default' looks like a sidebar.item (nav row). 'heading' makes
    // it a small uppercase tracked section label (matching a collapsible sidebar.group)
    // for designs that treat the group title as a section heading rather than a nav row.
    'variant' => 'default',
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $open = BooleanProp::from($open, false);

    $variant = WireKit::validateProp('sidebar.collapsible', 'variant', $variant, ['default', 'heading']);

    // Collapsible sidebar group — a disclosure widget that toggles child items.
    // The default trigger looks like a sidebar item but acts as an expand/collapse
    // toggle. Uses aria-expanded for AT, and indents child content by one level.
    $triggerClasses = WireKit::resolveClasses('sidebar.collapsible', 'trigger', implode(' ', [
        'flex items-center gap-[var(--padding-wk-x-sm)] w-full',
        'group-data-[collapsed]/wk-sidebar:justify-center',
        'px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)]',
        'rounded-[var(--radius-wk-md)]',
        'text-[color:var(--color-wk-text-muted)]',
        'hover:bg-[var(--color-wk-bg-muted)]',
        'hover:text-[color:var(--color-wk-text)]',
        'focus-visible:outline-none',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'cursor-pointer',
    ]), $scope);

    // The 'heading' variant lives in its OWN resolvable block so a theme can restyle
    // just the heading typography WITHOUT copying the ~12 default-trigger classes and
    // drifting on the next release. Mirrors sidebar.group's collapsible-trigger tokens
    // (small uppercase tracked label) while keeping the icon + label + chevron layout.
    $headingTriggerClasses = WireKit::resolveClasses('sidebar.collapsible', 'trigger-heading', implode(' ', [
        'flex items-center gap-[var(--padding-wk-x-sm)] w-full',
        'group-data-[collapsed]/wk-sidebar:justify-center',
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

    $triggerClasses = $variant === 'heading' ? $headingTriggerClasses : $triggerClasses;

    // Child container — indented to show hierarchy.
    $childClasses = WireKit::resolveClasses('sidebar.collapsible', 'children', implode(' ', [
        'flex flex-col gap-[2px]',
        'pl-[var(--padding-wk-x-md)]',
    ]), $scope);
@endphp

<div
    x-data="wirekitSidebarDisclosure({ open: {{ $open ? 'true' : 'false' }}, persist: {{ $persist === null ? 'null' : \Pushery\WireKit\Support\AlpinePayload::from($persist) }} })"
    {{ $attributes }}
>
    {{-- Trigger button — toggles the child items. aria-expanded announces
         the current state to screen readers. --}}
    {{-- In the collapsed icon rail the disclosure trigger is `hidden`: the heading
         (icon + label + chevron) is unreadable at 3.5rem and there is nothing to
         disclose there — the children are force-shown as a flat icon list below,
         matching the static sidebar.group's rail behavior. --}}
    <button
        type="button"
        x-on:click="toggle()"
        :aria-expanded="open ? 'true' : 'false'"
        class="{{ $triggerClasses }} group-data-[collapsed]/wk-sidebar:hidden"
    >
        @if($icon)
            {{-- Icon — decorative, hidden from AT. A bare name string resolves
                 via the WireKit icon system (consistent with sidebar.item /
                 dropdown.item); a <x-slot:icon> or inline markup (non-string
                 ComponentSlot, Htmlable) renders verbatim. --}}
            <span class="shrink-0" aria-hidden="true">
                @if(is_string($icon) && ! str_contains($icon, '<') && function_exists('svg'))
                    {{ svg(\Pushery\WireKit\WireKit::icon($icon), ['class' => 'w-5 h-5']) }}
                @else
                    {{ $icon }}
                @endif
            </span>
        @endif
        <span class="flex-1 truncate text-left group-data-[collapsed]/wk-sidebar:sr-only">{{ $label }}</span>
        {{-- Chevron indicator — rotates when open; hidden in the collapsed rail. --}}
        <svg
            class="w-3.5 h-3.5 shrink-0 transition-transform duration-[var(--transition-wk-duration)] group-data-[collapsed]/wk-sidebar:hidden"
            :class="open ? 'rotate-90' : ''"
            fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"
        >
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
        </svg>
    </button>

    {{-- Collapsible children — shown/hidden with Alpine when the sidebar is expanded.
         In the collapsed rail they are FORCE-SHOWN as a flat, centered icon list
         (the indent is dropped via pl-0) instead of being hidden: the section icons
         stay reachable, matching the static sidebar.group. The `typeof collapsed`
         guard is mandatory — a sidebar.collapsible used inside a NON-collapsible
         <x-wirekit::sidebar> has no `collapsed` in Alpine scope, so a bare
         `open || collapsed` would throw a ReferenceError there. --}}
    <div x-show="childrenVisible()" x-collapse x-cloak class="{{ $childClasses }} group-data-[collapsed]/wk-sidebar:pl-0">
        {{ $slot }}
    </div>
</div>
