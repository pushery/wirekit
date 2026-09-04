{{-- optimistic-ui: n/a — passthrough
     A navigation entry that may carry a developer action; the action's result is not this component's. --}}
@props([
    'href' => '#',
    'active' => false,
    'icon' => null,
    'submenu' => false,
    // A trailing counter/dot (an unread badge) — a count or short string renders a
    // pill AFTER the label, OUTSIDE the label span so a long name can never push it out, and
    // stays visible in the collapsed rail.
    'badge' => null,
    'scope' => null,
    // The identity of this row when the parent sidebar is in `selection` mode. Ignored
    // in the default navigation mode, where `href` is the identity.
    'value' => null,
])

{{-- Inherited from the parent <x-wirekit::sidebar>. A row cannot know on its own which
     ARIA contract it is part of, and asking the caller to repeat the mode on every row is
     the kind of duplication that goes wrong on row nine. --}}
@aware([
    'mode' => 'navigation',
    'selected' => null,
    // Whether the column this row sits in can ever become an icon rail. A sidebar that
    // cannot collapse never hides this label, so it needs no tooltip — and wrapping it
    // anyway would put a second element around every navigation row in every classic
    // sidebar in the fleet, for a state those sidebars cannot reach.
    'collapsible' => false,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('sidebar.item', $attributes->getAttributes());

    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $selectionMode = $mode === 'selection';

    // WHICH attribute marks "this is the one", and it is not cosmetic which one.
    //
    // The three variants below exist because the muted rule and the active rule have
    // EQUAL specificity and Tailwind emits the muted one last — see the block comment on
    // the first of them. In `selection` mode no row carries `aria-current` at all, so a
    // variant keyed on it matches EVERY row, and the chosen one renders muted along with
    // the rest. The mechanism is right; only the attribute it watches has to follow the
    // contract the column is actually under.
    // ⚠️ BOTH SETS ARE WRITTEN OUT IN FULL, and that is not verbosity.
    //
    // The first attempt interpolated the attribute — `"not-[".$marker."]:text-…"` — which
    // reads well and is silently wrong twice over. Tailwind scans SOURCE for complete
    // class names, so a name assembled at runtime is a name it never sees: the rules were
    // simply not generated, and every row would have rendered unstyled in the direction
    // the variant exists to control. The drift guard caught it as forward drift; nothing
    // in the markup or the ARIA would have looked wrong.
    $notCurrentClasses = $selectionMode
        ? [
            'not-[[aria-selected=true]]:text-[color:var(--color-wk-text-muted)]',
            'not-[[aria-selected=true]]:hover:bg-[var(--color-wk-bg-muted)]',
            'not-[[aria-selected=true]]:hover:text-[color:var(--color-wk-text)]',
        ]
        : [
            'not-[[aria-current]]:text-[color:var(--color-wk-text-muted)]',
            'not-[[aria-current]]:hover:bg-[var(--color-wk-bg-muted)]',
            'not-[[aria-current]]:hover:text-[color:var(--color-wk-text)]',
        ];

    $active = BooleanProp::from($active, false);
    $submenu = BooleanProp::from($submenu, false);

    // also consume `data-current` /
    // `data-current="true"` if the caller passed it via the attribute
    // bag (Livewire 4 emits this on `wire:navigate` links automatically).
    // Without this fallback the developer has to manually pass
    // `:active="request()->is('posts*')"` on every sidebar item,
    // duplicating routing knowledge that's already encoded in the
    // route file. We OR-merge: explicit `:active` always wins; if the
    // caller didn't pass `active` but did pass `data-current="true"`,
    // the item highlights.
    if (! $active) {
        $dataCurrent = $attributes->get('data-current');
        if ($dataCurrent === true || $dataCurrent === 'true' || $dataCurrent === '1' || $dataCurrent === 'page') {
            $active = true;
        }
    }

    // Individual nav link. Active items get a highlighted background and
    // aria-current="page" so AT announces "current page, <label>".
    $classes = WireKit::resolveClasses('sidebar.item', 'base', implode(' ', [
        // `relative` is the containing block the collapsed-rail counter dot
        // positions against — without it the dot would anchor to the nav.
        'relative flex items-center gap-[var(--padding-wk-x-sm)]',
        // Collapse-to-icon rail: center the lone icon when the sidebar collapses.
        'group-data-[collapsed]/wk-sidebar:justify-center',
        'px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)]',
        // ⚠️ THE ROW HEIGHT MUST NOT DEPEND ON WHICH CHILD HAPPENS TO BE TALLEST.
        //
        // Without this the row was sized by its LABEL while expanded and by its ICON while
        // collapsed, because the label carries `sr-only` in the rail and `sr-only` is a
        // 1px box. Measured on the docs site: an expanded item is 34.75px (a 22.75px line
        // box plus 6px top and bottom) and a collapsed one is 32px (a 20px icon plus the
        // same padding) — 2.75px per row, so a three-item rail changed height by 8.25px
        // when it collapsed and everything under it moved. Reported four times before
        // anybody measured it.
        //
        // `1lh` is the item's own line box, so the floor tracks the type ramp and the
        // `--font-scale-wk` accessibility bump instead of pinning a length. Chrome 109 /
        // Safari 16.4 / Firefox 120 — inside the support floor, not above it.
        //
        // The sample app could not see this: it loads no web font, so the fallback's line
        // box is shorter than the icon and both states measured 32px there.
        'min-h-[calc(1lh_+_var(--padding-wk-y-sm)_*_2)]',
        // Derived from the container rather than fixed — see dist/wirekit.css. In a card
        // sidebar the concentric answer is 4px, not the 8px this used to be.
        'rounded-[var(--radius-wk-nav-item)]',
        // The RESTING foreground is scoped to non-active items for the same reason
        // the hover below is, and it is not optional. Unscoped, this and the active
        // block's `text-[color:var(--color-wk-text)]` are both bare single-class
        // selectors — specificity (0,1,0), same layer — so the winner is decided by
        // EMISSION ORDER, and Tailwind v4 sorts arbitrary color utilities by value:
        // `--color-wk-text` comes before `--color-wk-text-muted`, so the muted rule
        // is emitted last and wins. The active item rendered muted, never the
        // emphasized foreground the block below documents.
        //
        // Worse than a no-op: a developer retinting the active state with their own
        // (0,1,0) utility loses to this one too, so the escape hatch was `!important`.
        // Do not "simplify" the variant off — equal specificity is the whole problem.
        ...$notCurrentClasses,
        // Hover is scoped to NON-active items via `:not([aria-current])`. An active item
        // already carries `aria-current="page"`, and an UNSCOPED `hover:bg` here (specificity
        // 0,2,0) would override a retinted active block (a developer's 0,1,0 utilities) the
        // instant the pointer arrives — the pill would snap back to muted mid-hover, forcing
        // the developer to reach for `!important`. Scoping matches the common expectation too:
        // the current page does not react to hover, it is already the target state.
        'focus-visible:outline-none',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
    ]), $scope);

    // Active state gets different styling — emphasized foreground and a
    // subtle background tint. Merged via $attributes->class conditional.
    // The icon's own size, as a named block rather than a literal in the render call.
    //
    // It was written straight into the `svg()` helper, which put it out of reach of
    // every personalization route at once — there was no block name to address. The
    // cost shows up only once delta personalization exists: an application that wants
    // a 16px icon has to take over the SURROUNDING block and reach the icon through a
    // descendant selector, and a taken-over block stops inheriting improvements
    // silently. It still renders; it renders the version from the day it was copied.
    //
    // So the literal was not merely untidy — it forced the one outcome the delta form
    // was built to avoid.
    $iconClasses = WireKit::resolveClasses('sidebar.item', 'icon', 'w-5 h-5', $scope);

    $activeClasses = WireKit::resolveClasses('sidebar.item', 'active', implode(' ', [
        'bg-[var(--color-wk-bg-muted)]',
        'text-[color:var(--color-wk-text)]',
        'font-[number:var(--font-wk-body-weight)]',
    ]), $scope);

    // Auto-inject rel="noopener noreferrer" and SR hint when target="_blank".
    // CAREFUL: $attributes->merge(['rel' => ...]) treats rel as a DEFAULT —
    // if the caller passed their own rel (even rel="prev"), theirs wins and
    // our auto-injection would silently fail, re-introducing tabnabbing.
    // To force-override, we remove rel from the bag and render it separately
    // whenever we have a computed value.
    $targetAttr = $attributes->get('target', '');
    $opensNewTab = str_contains($targetAttr, '_blank');
    $relAttr = $attributes->get('rel', '');
    $finalRel = $opensNewTab && ! str_contains($relAttr, 'noopener')
        ? trim($relAttr.' noopener noreferrer')
        : $relAttr;
    $computedRel = $opensNewTab ? $finalRel : ($relAttr ?: null);
@endphp

@php
    // Server-rendered selection, so the column is correct before Alpine boots. Alpine
    // then owns it: `x-bind` below wins over this literal the moment it initializes.
    $isSelected = $selectionMode && $value !== null && (string) $value === (string) $selected;

    // A stable id, because `aria-activedescendant` points at one and a random id per
    // render would break the pointer on every Livewire morph — the marker would name an
    // element that no longer exists, and the announcement would simply stop.
    $optionId = $selectionMode
        ? \Pushery\WireKit\Support\DomId::unique(
            $value !== null ? 'wk-sidebar-option-'.\Illuminate\Support\Str::slug((string) $value) : null,
            'wk-sidebar-option-'
        )
        : null;

    // `href` is refused rather than ignored. A link inside a listbox is not a smaller
    // problem than a missing role: the row would be in the tab order, Enter would
    // navigate instead of choosing, and the one-tab-stop promise of the pattern would be
    // broken by a row the caller thought was decorative.
    // `$href` rather than the attribute bag: `href` is a declared prop, so Blade has
    // already lifted it out of the bag and `$attributes->has('href')` is false for every
    // caller — the check would have been dead in exactly the case it exists for.
    if ($selectionMode && $href !== '#' && $href !== null && config('app.debug')) {
        $hrefInOptionWarning = '[wirekit] sidebar.item: `href` is ignored inside a '
            .'`mode="selection"` sidebar. A listbox option is a choice, not a destination — '
            .'a link here would put the row in the tab order and make Enter navigate instead '
            .'of select. Use `value` and your own click handler.';
    }
@endphp

{{-- The tooltip is the LABEL for a reader holding a pointer, and only then.

     `disabled` is bound to live state rather than decided at render: the sidebar
     collapses in the browser, so a render-time decision would either show the bubble
     beside a perfectly visible caption or never show it at all. Same binding the app
     rail uses, for the same reason.

     `focusable-trigger="false"` because the row is already focusable and already named;
     a second tab stop in front of every navigation entry would be the cure being worse.

     `block w-full` overrides the tooltip's own `inline-block` so the wrapper does not
     narrow the row it wraps. --}}
@if($collapsible)
    <x-wirekit::tooltip
        :text="trim(strip_tags((string) $slot))"
        placement="right"
        focusable-trigger="false"
        class="block w-full"
        {{-- `$data`, not a bare `collapsed`. The rail state lives on the sidebar, and this row
             only reaches it when the SIDEBAR is the collapsible one. `@aware` cannot tell that
             apart: `sidebar.group` declares a `collapsible` prop of its own -- meaning "this
             group folds its children", something else entirely -- and the nearest ancestor wins.
             So a plain sidebar holding a collapsible group wrapped every row in a tooltip bound
             to an identifier no scope defines, and Alpine threw on each one. Reading through
             `$data` yields `undefined` there instead, which disables the tooltip -- the right
             answer for a column that can never hide its label. --}}
        x-bind:data-wk-tooltip-disabled="! $data.collapsed"
    >@include('wirekit::components.partials.sidebar-item-link')</x-wirekit::tooltip>
@else
    @include('wirekit::components.partials.sidebar-item-link')
@endif

@if(isset($hrefInOptionWarning))
    <div x-data="wirekitDevWarning({ message: {{ \Pushery\WireKit\Support\AlpinePayload::from($hrefInOptionWarning) }} })" hidden></div>
@endif
