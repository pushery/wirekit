{{-- optimistic-ui: n/a — client-only
     Its state is disclosure state. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. --}}
@props([
    'label' => null,
    // When set, the group heading becomes a disclosure button that folds its items.
    // `open` is the initial state (default open — a section keeps showing its children
    // until the user folds it), `persist` is an optional localStorage key so the fold
    // state survives a reload (same semantics as the sidebar's own `persist`).
    'collapsible' => false,
    'open' => true,
    'persist' => null,
    // Open on every load whatever `persist` stored, for the section that holds the current page:
    // `:force-open="$entry->isActive()"`. A reader who folds it keeps it folded for this visit.
    // False leaves the stored state in charge, as without the prop.
    'forceOpen' => false,
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('sidebar.group', $attributes->getAttributes());

    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\Support\DomId;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — normalize
    // against each prop's own default so a cast never flips a feature that was on.
    $collapsible = BooleanProp::from($collapsible, false);
    $open = BooleanProp::from($open, true);
    $forceOpen = BooleanProp::from($forceOpen, false);
    $open = $open || $forceOpen;

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
        'flex items-center justify-between w-full min-w-0 gap-[var(--padding-wk-x-sm)]',
        'px-[var(--padding-wk-x-sm)] pt-[var(--padding-wk-y-sm)] pb-[2px]',
        'text-[length:var(--text-wk-xs)]',
        'font-[number:var(--font-wk-heading-weight)]',
        'uppercase tracking-wider',
        'text-[color:var(--color-wk-text-subtle)]',
        'hover:text-[color:var(--color-wk-text-muted)]',
        'focus-visible:outline-hidden',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'rounded-[var(--radius-wk-md)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'cursor-pointer',
    ]), $scope);

    // Read THROUGH the bag rather than written before it. A hardcoded attribute followed
    // by the bag's own emits the attribute twice and the FIRST one wins, so a developer's
    // own name was present in the markup and still never reached the reader — the shape
    // sidebar.toggle was corrected to, which this component had not caught up with.
    $groupLabel = $attributes->has('aria-labelledby')
        ? null
        : ($attributes->get('aria-label') ?: ((string) $label !== '' ? $label : null));

    // The role is coupled to the NAME, which is the house rule rather than a preference:
    // a role that names a boundary and then cannot say what the boundary is for is noise
    // in the accessibility tree of every page that uses one, and it is repeated once per
    // group. bento-grid, accordion and `sidebar.collapsible` each decided this with their
    // own red-proof; this file was the last one emitting the role unconditionally, so the
    // same markup announced a group here and nothing there.
    //
    // A caller who wants a role anyway still gets it — the bag is read first, so an
    // explicit `role` on the tag wins over both branches.
    $groupRole = $attributes->get(
        'role',
        $groupLabel !== null || $attributes->has('aria-labelledby') ? 'group' : null
    );

    // The disclosed region's id, so the trigger's `aria-controls` can name it. Seeded:
    // a per-render id would make Livewire's morph replace the live region with a clone,
    // which replays the collapse transition.
    // Suffixed because the bag emits a caller-supplied `id` on the ROOT, and two
    // elements sharing one id is a defect of its own.
    // Only the collapsible branch has a region to name, so a static group computes
    // nothing — the id would be a value nothing reads.
    //
    // The seed falls back past the label, because an unlabeled collapsible group is a
    // shipped shape here rather than an edge case: the trigger below carries a generic
    // name for exactly it. `persist` comes next because two unlabeled groups that both
    // persist their fold state must already carry distinct keys to keep distinct state.
    // With neither, `stableId` counts, which is page-unique and the same on the next
    // render.
    //
    // A seed can still repeat on one page: the same label in two sidebars, or in two
    // previews of one documentation page. The deduper keeps the first id as it is and
    // appends `-2` to a repeat, so each trigger names its own region. An id the caller
    // chose is used as given.
    $panelId = $collapsible
        ? ($attributes->get('id') ?: DomId::unique(WireKit::stableId(
            'wk-sidebar-group',
            (string) $label !== ''
                ? (string) $label
                : (is_string($persist) && $persist !== '' ? $persist : null)
        ), 'wk-sidebar-group-')).'-panel'
        : null;
@endphp

@if($collapsible)
    {{-- Collapsible section: the heading is a disclosure button, the children fold via
         x-collapse, and the open state optionally persists to localStorage. The outer
         container keeps role="group" + aria-label so AT still announces the labeled
         group; the button carries the expand/collapse role. --}}
    <div
        @if($groupRole) role="{{ $groupRole }}" @endif
        @if($groupLabel !== null) aria-label="{{ $groupLabel }}" @endif
        x-data="wirekitSidebarDisclosure({ open: {{ $open ? 'true' : 'false' }}, forceOpen: {{ $forceOpen ? 'true' : 'false' }}, persist: {{ $persist === null ? 'null' : \Pushery\WireKit\Support\AlpinePayload::from($persist) }} })"
        {{ $attributes->except(['role', 'aria-label'])->class([$groupClasses]) }}
    >
        {{-- The trigger and the section's own control sit side by side. The wrapper is
             only emitted when there IS an action, so a group without one keeps the exact
             DOM it had — a button that was a direct child stays a direct child. --}}
        @isset($action)<div class="flex items-center gap-[var(--padding-wk-x-xs)]">@endisset
        <button
            type="button"
            x-on:click="toggle()"
            :aria-expanded="isOpen ? 'true' : 'false'"
            aria-controls="{{ $panelId }}"
            {{-- No visible label to name the button? fall back to a generic name so the
                 disclosure control is never nameless (WCAG 4.1.2). --}}
            @unless($label) aria-label="{{ __('wirekit::Section') }}" @endunless
            {{-- In the collapsed icon rail the button is `hidden` (not sr-only): the group
                 has no icon to show at rail width and its children are hidden too, so a
                 focusable-but-invisible sr-only control would be a keyboard focus trap with
                 no visible focus indicator (WCAG 2.4.7). --}}
            class="{{ $triggerClasses }} group-data-[collapsed]/wk-sidebar:hidden group-data-[settling]/wk-sidebar:hidden"
        >
            {{-- Wraps rather than truncating — see sidebar.item for the rule. --}}
            <span class="break-words">{{ $label }}</span>
            {{-- Chevron rotates when open; hidden in the collapsed icon rail (no room). --}}
            <svg
                class="w-3.5 h-3.5 shrink-0 transition-transform duration-[var(--transition-wk-duration)] group-data-[collapsed]/wk-sidebar:hidden group-data-[settling]/wk-sidebar:hidden"
                :class="isOpen ? 'rotate-90' : ''"
                fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
        </button>
        @isset($action)<div class="shrink-0 group-data-[collapsed]/wk-sidebar:hidden group-data-[settling]/wk-sidebar:hidden">{{ $action }}</div></div>@endisset
        {{-- Children — shown/hidden with Alpine when expanded; FORCE-SHOWN as a flat
             icon list in the collapsed rail (the item icons stay reachable), matching
             the static sidebar.group + sidebar.collapsible. The `typeof collapsed`
             guard avoids a ReferenceError when the group sits in a non-collapsible
             sidebar (no `collapsed` in Alpine scope). --}}
        {{-- `x-cloak` only on a section the server renders closed. An open one paints with the
             page instead of appearing once Alpine starts; a closed one stays hidden until then,
             so it never shows its entries for a frame. --}}
        <div id="{{ $panelId }}" x-show="childrenVisible()" x-collapse @unless($open) x-cloak @endunless class="flex flex-col gap-[2px]">
            {{ $slot }}
        </div>
        {{-- Directly after the panel, so the script's previous sibling is this panel. Only with
             `persist`, and not with `force-open`, which opens the section whatever was stored. --}}
        @if(is_string($persist) && $persist !== '' && ! $forceOpen)
            @include('wirekit::components.partials.disclosure-persist-seed', ['seedKey' => $persist, 'seedOn' => $open])
        @endif
    </div>
@else
    <div @if($groupRole) role="{{ $groupRole }}" @endif @if($groupLabel !== null) aria-label="{{ $groupLabel }}" @endif {{ $attributes->except(['role', 'aria-label'])->class([$groupClasses]) }}>
        {{-- The `action` slot is the small control that belongs TO the section — the
             "add a team" plus beside a Teams heading. It is a SIBLING of the label, and
             in the collapsible branch a sibling of the disclosure BUTTON, because an
             interactive control nested inside another one is invalid and unreachable by
             keyboard in practice.
             The row wrapper is emitted ONLY when there is an action, so a group without
             one keeps the exact DOM it had — including the label's `sr-only` in the
             collapsed rail, which is deliberate: the heading stays the group's accessible
             name there rather than being removed. The action is `hidden` instead, because
             a control has nothing to say to a screen reader when the section it acts on
             is not shown, and a focusable-but-invisible button is a focus trap with no
             visible indicator. --}}
        @isset($action)
            <div class="flex items-center justify-between gap-[var(--padding-wk-x-xs)]">
                @if($label)
                    <div class="{{ $labelClasses }} min-w-0 break-words wk-rail-hide">{{ $label }}</div>
                @endif
                <div class="shrink-0 group-data-[collapsed]/wk-sidebar:hidden group-data-[settling]/wk-sidebar:hidden">{{ $action }}</div>
            </div>
        @elseif($label)
            {{-- Visible label; also the accessible name via aria-label above.
                 We render it visually because sighted users benefit from the grouping too. --}}
            <div class="{{ $labelClasses }} wk-rail-hide">{{ $label }}</div>
        @endisset
        {{ $slot }}
    </div>
@endif
